<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowHistoryEntry;
use App\Models\WorkflowStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EngineRequestDemoSeeder extends Seeder
{
    /**
     * @var Collection<string, WorkflowStage>
     */
    private Collection $stages;

    /**
     * @var array<string, User>
     */
    private array $actors = [];

    /**
     * @var Collection<int, Bank>
     */
    private Collection $banks;

    /**
     * @var array<int, array<string, User>>
     */
    private array $bankActors = [];

    /**
     * @var array<string, User>
     */
    private array $commonActors = [];

    public function run(): void
    {
        if (EngineRequest::query()->exists()) {
            $this->command?->info('Engine request demo data already exists — skipping.');

            return;
        }

        $definition = WorkflowDefinition::query()->where('code', 'IMPORT_FINANCING')->firstOrFail();
        $version = $definition->versions()->firstOrFail();
        $this->stages = WorkflowStage::query()
            ->where('workflow_version_id', $version->id)
            ->get()
            ->keyBy('code');

        $this->banks = Bank::query()->where('is_active', true)->orderBy('id')->get()->values();
        $merchantsByBank = Merchant::query()
            ->with(['companies', 'owners'])
            ->whereIn('bank_id', $this->banks->pluck('id'))
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->get()
            ->groupBy('bank_id');

        if ($this->banks->isEmpty() || $merchantsByBank->isEmpty()) {
            $this->command?->warn('No active banks or merchants exist — engine request demo data skipped.');

            return;
        }

        $this->commonActors = $this->commonActors();
        $this->bankActors = $this->bankActors();

        DB::transaction(function () use ($version, $merchantsByBank): void {
            foreach ($this->samples() as $index => $sample) {
                $bank = $this->banks[$index % $this->banks->count()];
                $merchants = ($merchantsByBank->get($bank->id) ?? collect())->values();

                if ($merchants->isEmpty()) {
                    continue;
                }

                $createdAt = Carbon::create(2026, 5, ($index % 27) + 1, 9, 0, 0);
                $merchant = $merchants[$index % $merchants->count()];
                $data = $this->requestData($sample, $merchant);
                $this->actors = $this->actorsFor($bank);

                ['history' => $historyRows, 'updated_at' => $updatedAt] = $this->historyRows($sample, $createdAt);

                $request = EngineRequest::query()->create([
                    'workflow_version_id' => $version->id,
                    'current_stage_id' => $this->stages[$sample['stage']]->id,
                    'reference' => sprintf('ENG-2026-%06d', 2001 + $index),
                    'status' => $sample['status'],
                    'created_by' => $this->actors['CREATE']->id,
                    'bank_id' => $bank->id,
                    'merchant_id' => $merchant->id,
                    'data' => $data,
                    'version' => 1,
                    'amount' => $sample['amount'],
                    'currency' => $sample['currency'],
                    'invoice_number' => $sample['invoice_number'],
                    'request_percentage' => 100,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);

                foreach ($historyRows as $row) {
                    WorkflowHistoryEntry::query()->create($row + ['request_id' => $request->id]);
                }
            }
        });
    }

    /**
     * @return array<string, User>
     */
    private function commonActors(): array
    {
        $fxConfirm = User::query()->where('email', 'exec3@cby.gov.ye')->firstOrFail();
        $this->grantFxConfirmationIdentity($fxConfirm);

        return [
            'SUPPORT' => User::query()->where('email', 'support1@cby.gov.ye')->firstOrFail(),
            'EXEC' => User::query()->where('email', 'director@cby.gov.ye')->firstOrFail(),
            'FX_CONFIRM' => $fxConfirm,
            'FINAL' => User::query()->where('email', 'director@cby.gov.ye')->firstOrFail(),
            'CLOSED' => User::query()->where('email', 'director@cby.gov.ye')->firstOrFail(),
        ];
    }

    /**
     * @return array<int, array<string, User>>
     */
    private function bankActors(): array
    {
        return $this->banks
            ->mapWithKeys(function (Bank $bank): array {
                $code = strtolower($bank->code);

                return [
                    $bank->id => [
                        'CREATE' => User::query()->where('email', "entry@{$code}.com.ye")->firstOrFail(),
                        'INTERNAL' => User::query()->where('email', "reviewer@{$code}.com.ye")->firstOrFail(),
                        'FX' => User::query()->where('email', "swift@{$code}.com.ye")->firstOrFail(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, User>
     */
    private function actorsFor(Bank $bank): array
    {
        return $this->bankActors[$bank->id] + $this->commonActors;
    }

    private function grantFxConfirmationIdentity(User $user): void
    {
        $teamId = Team::query()->where('code', 'fx_confirmation')->value('id');
        $roleId = Role::query()->where('code', 'fx_confirm')->value('id');

        if ($teamId !== null) {
            $user->teams()->syncWithoutDetaching([$teamId]);
        }

        if ($roleId !== null) {
            $user->roles()->syncWithoutDetaching([$roleId]);
        }
    }

    /**
     * @return array<int, array{stage: string, status: string, amount: int, currency: string, invoice_number: string, importType: string, supplierName: string, originCountry: string, arrivalPort: string}>
     */
    private function samples(): array
    {
        return [
            ['stage' => 'CREATE', 'status' => 'ACTIVE', 'amount' => 120000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10000', 'importType' => 'food_beverages', 'supplierName' => 'Cargill Inc.', 'originCountry' => 'cn', 'arrivalPort' => 'aden_port'],
            ['stage' => 'CREATE', 'status' => 'ACTIVE', 'amount' => 340000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10011', 'importType' => 'construction_materials', 'supplierName' => 'Siemens AG', 'originCountry' => 'tr', 'arrivalPort' => 'hodeidah_port'],
            ['stage' => 'CREATE', 'status' => 'ACTIVE', 'amount' => 510000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10022', 'importType' => 'medical_pharma', 'supplierName' => 'Pfizer Ltd.', 'originCountry' => 'in', 'arrivalPort' => 'aden_port'],
            ['stage' => 'CREATE', 'status' => 'ACTIVE', 'amount' => 89000, 'currency' => 'EUR', 'invoice_number' => 'INV-2026-10033', 'importType' => 'medical_pharma', 'supplierName' => 'Bayer AG', 'originCountry' => 'tr', 'arrivalPort' => 'mukalla_port'],
            ['stage' => 'INTERNAL', 'status' => 'ACTIVE', 'amount' => 720000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10044', 'importType' => 'fuel_energy', 'supplierName' => 'Saudi Aramco Trading', 'originCountry' => 'ae', 'arrivalPort' => 'hodeidah_port'],
            ['stage' => 'INTERNAL', 'status' => 'ACTIVE', 'amount' => 145000, 'currency' => 'SAR', 'invoice_number' => 'INV-2026-10055', 'importType' => 'construction_materials', 'supplierName' => 'Siemens AG', 'originCountry' => 'tr', 'arrivalPort' => 'aden_port'],
            ['stage' => 'INTERNAL', 'status' => 'ACTIVE', 'amount' => 275000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10056', 'importType' => 'food_beverages', 'supplierName' => 'Cargill Inc.', 'originCountry' => 'cn', 'arrivalPort' => 'mukalla_port'],
            ['stage' => 'INTERNAL', 'status' => 'ACTIVE', 'amount' => 305000, 'currency' => 'EUR', 'invoice_number' => 'INV-2026-10057', 'importType' => 'construction_materials', 'supplierName' => 'Siemens AG', 'originCountry' => 'tr', 'arrivalPort' => 'hodeidah_port'],
            ['stage' => 'FX', 'status' => 'ACTIVE', 'amount' => 980000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10066', 'importType' => 'food_beverages', 'supplierName' => 'Cargill Inc.', 'originCountry' => 'cn', 'arrivalPort' => 'aden_port'],
            ['stage' => 'FX', 'status' => 'ACTIVE', 'amount' => 230000, 'currency' => 'EUR', 'invoice_number' => 'INV-2026-10077', 'importType' => 'construction_materials', 'supplierName' => 'Siemens AG', 'originCountry' => 'tr', 'arrivalPort' => 'hodeidah_port'],
            ['stage' => 'FX', 'status' => 'ACTIVE', 'amount' => 415000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10088', 'importType' => 'medical_pharma', 'supplierName' => 'Pfizer Ltd.', 'originCountry' => 'in', 'arrivalPort' => 'mukalla_port'],
            ['stage' => 'FX', 'status' => 'ACTIVE', 'amount' => 1250000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10099', 'importType' => 'fuel_energy', 'supplierName' => 'Saudi Aramco Trading', 'originCountry' => 'ae', 'arrivalPort' => 'aden_port'],
            ['stage' => 'SUPPORT', 'status' => 'ACTIVE', 'amount' => 640000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10110', 'importType' => 'food_beverages', 'supplierName' => 'Cargill Inc.', 'originCountry' => 'cn', 'arrivalPort' => 'hodeidah_port'],
            ['stage' => 'SUPPORT', 'status' => 'ACTIVE', 'amount' => 1100000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10121', 'importType' => 'fuel_energy', 'supplierName' => 'Saudi Aramco Trading', 'originCountry' => 'ae', 'arrivalPort' => 'aden_port'],
            ['stage' => 'EXEC', 'status' => 'ACTIVE', 'amount' => 420000, 'currency' => 'EUR', 'invoice_number' => 'INV-2026-10132', 'importType' => 'construction_materials', 'supplierName' => 'Bayer AG', 'originCountry' => 'tr', 'arrivalPort' => 'aden_port'],
            ['stage' => 'EXEC', 'status' => 'ACTIVE', 'amount' => 540000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10143', 'importType' => 'food_beverages', 'supplierName' => 'Cargill Inc.', 'originCountry' => 'cn', 'arrivalPort' => 'aden_port'],
            ['stage' => 'FX_CONFIRM', 'status' => 'ACTIVE', 'amount' => 1280000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10154', 'importType' => 'construction_materials', 'supplierName' => 'Siemens AG', 'originCountry' => 'tr', 'arrivalPort' => 'hodeidah_port'],
            ['stage' => 'FINAL', 'status' => 'ACTIVE', 'amount' => 980000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10165', 'importType' => 'fuel_energy', 'supplierName' => 'Saudi Aramco Trading', 'originCountry' => 'ae', 'arrivalPort' => 'mukalla_port'],
            ['stage' => 'CLOSED', 'status' => 'CLOSED', 'amount' => 360000, 'currency' => 'USD', 'invoice_number' => 'INV-2026-10176', 'importType' => 'medical_pharma', 'supplierName' => 'Pfizer Ltd.', 'originCountry' => 'in', 'arrivalPort' => 'aden_port'],
            ['stage' => 'CLOSED', 'status' => 'REJECTED', 'amount' => 775000, 'currency' => 'EUR', 'invoice_number' => 'INV-2026-10187', 'importType' => 'fuel_energy', 'supplierName' => 'Saudi Aramco Trading', 'originCountry' => 'ae', 'arrivalPort' => 'mukalla_port'],
        ];
    }

    /**
     * @param  array{stage: string, status: string, amount: int, currency: string, invoice_number: string, importType: string, supplierName: string, originCountry: string, arrivalPort: string}  $sample
     * @return array<string, mixed>
     */
    private function requestData(array $sample, Merchant $merchant): array
    {
        $company = $merchant->companies->first();
        $owners = $merchant->owners
            ->map(fn ($owner): string => "{$owner->name} - {$owner->ownership_percentage}%")
            ->implode("\n");

        return [
            'taxNumber' => $merchant->tax_number,
            'importerName' => $merchant->id,
            'linkedCompany' => $company?->id,
            'taxCardExpiry' => $merchant->tax_card_expiry?->toDateString(),
            'commercialRegistration' => $company?->commercial_registration_number,
            'commercialRegistrationExpiry' => $company?->commercial_registration_expiry?->toDateString(),
            'owners' => $owners,
            'requestType' => 'طلب مصارفة وتحويل خارجي',
            'coverageType' => 'اعتماد مستندي',
            'foreignCurrencySource' => 'حساب العميل',
            'paymentTerms' => 'كلي',
            'requestCurrency' => $sample['currency'],
            'request_percentage' => 100,
            'invoiceType' => 'فاتورة تجارية',
            'amount' => $sample['amount'],
            'currency' => $sample['currency'],
            'invoice_number' => $sample['invoice_number'],
            'invoiceDate' => '2026-06-16',
            'quantity' => 1,
            'unit' => 'كرتون',
            'invoiceTotal' => $sample['amount'],
            'importType' => $sample['importType'],
            'supplierName' => $sample['supplierName'],
            'supplierLocation' => 'المدينة / الدولة',
            'originCountry' => $sample['originCountry'],
            'shippingDate' => '2026-06-16',
            'arrivalDate' => '2026-06-16',
            'shippingPort' => 'ميناء الشحن',
            'arrivalPort' => $sample['arrivalPort'],
            'deliveryTerms' => 'CIF',
            'finalDestination' => 'المدينة / المخزن الوجهة',
            'docYemeniRialSharia' => ['seeded-doc-yemeni-rial'],
            'docSaudiRialSharia' => ['seeded-doc-saudi-rial'],
            'docUsdSharia' => ['seeded-doc-usd'],
            'docTaxAndCr' => ['seeded-doc-tax-cr'],
            'docCommercialInvoice' => ['seeded-doc-invoice'],
            'docLicenses' => [],
            'docExtra' => [],
        ];
    }

    /**
     * @param  array{stage: string, status: string}  $sample
     * @return array{history: array<int, array<string, mixed>>, updated_at: Carbon}
     */
    private function historyRows(array $sample, Carbon $createdAt): array
    {
        $path = ['CREATE', 'INTERNAL', 'SUPPORT', 'EXEC', 'FX', 'FX_CONFIRM', 'FINAL', 'CLOSED'];
        $targetIndex = array_search($sample['stage'], $path, true);
        $timestamp = $createdAt->copy();
        $rows = [[
            'from_stage_id' => null,
            'to_stage_id' => $this->stages['CREATE']->id,
            'action_code' => 'CREATE',
            'performed_by' => $this->actors['CREATE']->id,
            'comments' => 'إنشاء الطلب',
            'created_at' => $createdAt,
        ]];

        if ($sample['status'] === 'REJECTED') {
            $execIndex = array_search('EXEC', $path, true);
            for ($i = 1; $i <= $execIndex; $i++) {
                $timestamp = $timestamp->copy()->addHours(26);
                $rows[] = $this->historyHop($path[$i - 1], $path[$i], 'APPROVE', $timestamp);
            }

            $timestamp = $timestamp->copy()->addHours(26);
            $rows[] = $this->historyHop('EXEC', 'CLOSED', 'REJECT_FINAL', $timestamp);

            return ['history' => $rows, 'updated_at' => $timestamp];
        }

        for ($i = 1; $i <= $targetIndex; $i++) {
            $from = $path[$i - 1];
            $to = $path[$i];
            $timestamp = $timestamp->copy()->addHours(26);
            $rows[] = $this->historyHop($from, $to, $to === 'CLOSED' ? 'FINAL_APPROVE' : 'APPROVE', $timestamp);
        }

        return ['history' => $rows, 'updated_at' => $timestamp];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyHop(string $from, string $to, string $actionCode, Carbon $timestamp): array
    {
        return [
            'from_stage_id' => $this->stages[$from]->id,
            'to_stage_id' => $this->stages[$to]->id,
            'action_code' => $actionCode,
            'performed_by' => $this->actors[$from]->id,
            'comments' => null,
            'created_at' => $timestamp,
        ];
    }
}
