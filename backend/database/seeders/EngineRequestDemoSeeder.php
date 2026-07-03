<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\EngineRequest;
use App\Models\Merchant;
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
     * Linear stage progression used to build history hops.
     *
     * @var array<int, string>
     */
    private const PATH = ['CREATE', 'INTERNAL', 'SUPPORT', 'EXEC', 'FX', 'FX_CONFIRM', 'FINAL', 'CLOSED'];

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
            // Each bank owns a contiguous block of 20 references. Bank 0 =
            // 2001–2020 (carries the auxiliary-seeder anchors), bank 1 = 2021–2040.
            $globalIndex = 0;

            foreach ($this->banks as $bankIndex => $bank) {
                $merchants = ($merchantsByBank->get($bank->id) ?? collect())->values();

                if ($merchants->isEmpty()) {
                    continue;
                }

                $this->actors = $this->actorsFor($bank);
                $samples = $bankIndex === 0 ? $this->anchoredSamples() : $this->mirrorSamples();

                foreach ($samples as $slot => $sample) {
                    $createdAt = Carbon::create(2026, 5, ($globalIndex % 27) + 1, 9, 0, 0);
                    $merchant = $merchants[$slot % $merchants->count()];
                    $data = $this->requestData($sample, $merchant);

                    ['history' => $historyRows, 'updated_at' => $updatedAt] = $this->historyRows($sample, $createdAt);

                    $request = EngineRequest::query()->create([
                        'workflow_version_id' => $version->id,
                        'current_stage_id' => $this->stages[$sample['stage']]->id,
                        'reference' => sprintf('ENG-2026-%06d', 2001 + $globalIndex),
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

                    $globalIndex++;
                }
            }
        });
    }

    /**
     * @return array<string, User>
     */
    private function commonActors(): array
    {
        return [
            'SUPPORT' => User::query()->where('email', 'support1@cby.gov.ye')->firstOrFail(),
            'EXEC' => User::query()->where('email', 'director@cby.gov.ye')->firstOrFail(),
            'FX_CONFIRM' => User::query()->where('email', 'fxconfirm@cby.gov.ye')->firstOrFail(),
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

    /**
     * Bank 0 samples, ordered so the 2001-based references land on the exact
     * stage/status the auxiliary seeder expects:
     *   002001 CREATE (submitted notification), 002013 SUPPORT (support
     *   notification), 002017 FX_CONFIRM (fx-confirmation notification),
     *   002018/002019 CLOSED-completed (customs declarations),
     *   002020 REJECTED (rejected email).
     *
     * @return array<int, array<string, mixed>>
     */
    private function anchoredSamples(): array
    {
        return [
            $this->sample('CREATE', 'ACTIVE', 120000, 'USD', 'INV-2026-10000', 'food_beverages', 'Cargill Inc.', 'cn', 'aden_port'),
            $this->sample('CREATE', 'ACTIVE', 340000, 'USD', 'INV-2026-10011', 'construction_materials', 'Siemens AG', 'tr', 'hodeidah_port'),
            $this->sample('INTERNAL', 'ACTIVE', 510000, 'USD', 'INV-2026-10022', 'medical_pharma', 'Pfizer Ltd.', 'in', 'aden_port'),
            $this->sample('INTERNAL', 'ACTIVE', 89000, 'EUR', 'INV-2026-10033', 'medical_pharma', 'Bayer AG', 'tr', 'mukalla_port'),
            $this->sample('SUPPORT', 'ACTIVE', 720000, 'USD', 'INV-2026-10044', 'fuel_energy', 'Saudi Aramco Trading', 'ae', 'hodeidah_port'),
            $this->sample('EXEC', 'ACTIVE', 145000, 'SAR', 'INV-2026-10055', 'construction_materials', 'Siemens AG', 'tr', 'aden_port'),
            $this->sample('FX', 'ACTIVE', 275000, 'USD', 'INV-2026-10056', 'food_beverages', 'Cargill Inc.', 'cn', 'mukalla_port'),
            $this->sample('FX_CONFIRM', 'ACTIVE', 305000, 'EUR', 'INV-2026-10057', 'construction_materials', 'Siemens AG', 'tr', 'hodeidah_port'),
            $this->sample('INTERNAL', 'ACTIVE', 980000, 'USD', 'INV-2026-10066', 'food_beverages', 'Cargill Inc.', 'cn', 'aden_port', 'returned_to_internal'),
            $this->sample('CREATE', 'ACTIVE', 230000, 'EUR', 'INV-2026-10077', 'construction_materials', 'Siemens AG', 'tr', 'hodeidah_port', 'returned_to_entry'),
            $this->sample('FX', 'ACTIVE', 415000, 'USD', 'INV-2026-10088', 'medical_pharma', 'Pfizer Ltd.', 'in', 'mukalla_port'),
            $this->sample('FX_CONFIRM', 'ACTIVE', 1250000, 'USD', 'INV-2026-10099', 'fuel_energy', 'Saudi Aramco Trading', 'ae', 'aden_port'),
            $this->sample('SUPPORT', 'ACTIVE', 640000, 'USD', 'INV-2026-10110', 'food_beverages', 'Cargill Inc.', 'cn', 'hodeidah_port'),
            $this->sample('EXEC', 'ACTIVE', 1100000, 'USD', 'INV-2026-10121', 'fuel_energy', 'Saudi Aramco Trading', 'ae', 'aden_port'),
            $this->sample('FINAL', 'ACTIVE', 420000, 'EUR', 'INV-2026-10132', 'construction_materials', 'Bayer AG', 'tr', 'aden_port'),
            $this->sample('FINAL', 'ACTIVE', 540000, 'USD', 'INV-2026-10143', 'food_beverages', 'Cargill Inc.', 'cn', 'aden_port'),
            $this->sample('FX_CONFIRM', 'ACTIVE', 1280000, 'USD', 'INV-2026-10154', 'construction_materials', 'Siemens AG', 'tr', 'hodeidah_port'),
            $this->sample('CLOSED', 'CLOSED', 980000, 'USD', 'INV-2026-10165', 'fuel_energy', 'Saudi Aramco Trading', 'ae', 'mukalla_port'),
            $this->sample('CLOSED', 'CLOSED', 360000, 'USD', 'INV-2026-10176', 'medical_pharma', 'Pfizer Ltd.', 'in', 'aden_port'),
            $this->sample('CLOSED', 'REJECTED', 775000, 'EUR', 'INV-2026-10187', 'fuel_energy', 'Saudi Aramco Trading', 'ae', 'mukalla_port'),
        ];
    }

    /**
     * Bank 1 samples — same stage/status matrix as bank 0 (two per stage plus the
     * two return scenarios and a rejection) but distinct invoice numbers and no
     * auxiliary anchors.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mirrorSamples(): array
    {
        return [
            $this->sample('CREATE', 'ACTIVE', 132000, 'USD', 'INV-2026-20000', 'food_beverages', 'Cargill Inc.', 'cn', 'aden_port'),
            $this->sample('CREATE', 'ACTIVE', 358000, 'EUR', 'INV-2026-20011', 'construction_materials', 'Siemens AG', 'tr', 'hodeidah_port'),
            $this->sample('INTERNAL', 'ACTIVE', 495000, 'USD', 'INV-2026-20022', 'medical_pharma', 'Pfizer Ltd.', 'in', 'mukalla_port'),
            $this->sample('INTERNAL', 'ACTIVE', 96000, 'EUR', 'INV-2026-20033', 'medical_pharma', 'Bayer AG', 'tr', 'aden_port'),
            $this->sample('SUPPORT', 'ACTIVE', 688000, 'USD', 'INV-2026-20044', 'fuel_energy', 'Saudi Aramco Trading', 'ae', 'hodeidah_port'),
            $this->sample('EXEC', 'ACTIVE', 158000, 'SAR', 'INV-2026-20055', 'construction_materials', 'Siemens AG', 'tr', 'aden_port'),
            $this->sample('FX', 'ACTIVE', 262000, 'USD', 'INV-2026-20056', 'food_beverages', 'Cargill Inc.', 'cn', 'mukalla_port'),
            $this->sample('FX_CONFIRM', 'ACTIVE', 318000, 'EUR', 'INV-2026-20057', 'construction_materials', 'Siemens AG', 'tr', 'hodeidah_port'),
            $this->sample('INTERNAL', 'ACTIVE', 910000, 'USD', 'INV-2026-20066', 'food_beverages', 'Cargill Inc.', 'cn', 'aden_port', 'returned_to_internal'),
            $this->sample('CREATE', 'ACTIVE', 244000, 'EUR', 'INV-2026-20077', 'construction_materials', 'Siemens AG', 'tr', 'hodeidah_port', 'returned_to_entry'),
            $this->sample('FX', 'ACTIVE', 402000, 'USD', 'INV-2026-20088', 'medical_pharma', 'Pfizer Ltd.', 'in', 'mukalla_port'),
            $this->sample('FX_CONFIRM', 'ACTIVE', 1190000, 'USD', 'INV-2026-20099', 'fuel_energy', 'Saudi Aramco Trading', 'ae', 'aden_port'),
            $this->sample('SUPPORT', 'ACTIVE', 612000, 'USD', 'INV-2026-20110', 'food_beverages', 'Cargill Inc.', 'cn', 'hodeidah_port'),
            $this->sample('EXEC', 'ACTIVE', 1045000, 'USD', 'INV-2026-20121', 'fuel_energy', 'Saudi Aramco Trading', 'ae', 'aden_port'),
            $this->sample('FINAL', 'ACTIVE', 436000, 'EUR', 'INV-2026-20132', 'construction_materials', 'Bayer AG', 'tr', 'aden_port'),
            $this->sample('FINAL', 'ACTIVE', 528000, 'USD', 'INV-2026-20143', 'food_beverages', 'Cargill Inc.', 'cn', 'aden_port'),
            $this->sample('FX_CONFIRM', 'ACTIVE', 1310000, 'USD', 'INV-2026-20154', 'construction_materials', 'Siemens AG', 'tr', 'hodeidah_port'),
            $this->sample('CLOSED', 'CLOSED', 1020000, 'USD', 'INV-2026-20165', 'fuel_energy', 'Saudi Aramco Trading', 'ae', 'mukalla_port'),
            $this->sample('CLOSED', 'CLOSED', 372000, 'USD', 'INV-2026-20176', 'medical_pharma', 'Pfizer Ltd.', 'in', 'aden_port'),
            $this->sample('CLOSED', 'REJECTED', 805000, 'EUR', 'INV-2026-20187', 'fuel_energy', 'Saudi Aramco Trading', 'ae', 'mukalla_port'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sample(
        string $stage,
        string $status,
        int $amount,
        string $currency,
        string $invoiceNumber,
        string $importType,
        string $supplierName,
        string $originCountry,
        string $arrivalPort,
        ?string $scenario = null,
    ): array {
        return [
            'stage' => $stage,
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'invoice_number' => $invoiceNumber,
            'importType' => $importType,
            'supplierName' => $supplierName,
            'originCountry' => $originCountry,
            'arrivalPort' => $arrivalPort,
            'scenario' => $scenario,
        ];
    }

    /**
     * @param  array<string, mixed>  $sample
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
     * @param  array<string, mixed>  $sample
     * @return array{history: array<int, array<string, mixed>>, updated_at: Carbon}
     */
    private function historyRows(array $sample, Carbon $createdAt): array
    {
        $timestamp = $createdAt->copy();
        $rows = [$this->createRow($createdAt)];

        // Rejection: linear approvals up to EXEC, then a final rejection hop.
        if ($sample['status'] === 'REJECTED') {
            $execIndex = (int) array_search('EXEC', self::PATH, true);
            for ($i = 1; $i <= $execIndex; $i++) {
                $timestamp = $timestamp->copy()->addHours(26);
                $rows[] = $this->historyHop(self::PATH[$i - 1], self::PATH[$i], 'APPROVE', $timestamp);
            }

            $timestamp = $timestamp->copy()->addHours(26);
            $rows[] = $this->historyHop('EXEC', 'CLOSED', 'REJECT_FINAL', $timestamp);

            return ['history' => $rows, 'updated_at' => $timestamp];
        }

        // Returned to internal review: forwarded, sent back for correction, then
        // re-forwarded. Lands back on INTERNAL.
        if (($sample['scenario'] ?? null) === 'returned_to_internal') {
            $timestamp = $timestamp->copy()->addHours(26);
            $rows[] = $this->historyHop('CREATE', 'INTERNAL', 'FORWARD', $timestamp);
            $timestamp = $timestamp->copy()->addHours(26);
            $rows[] = $this->historyHop('INTERNAL', 'CREATE', 'RETURN', $timestamp);
            $timestamp = $timestamp->copy()->addHours(26);
            $rows[] = $this->historyHop('CREATE', 'INTERNAL', 'FORWARD', $timestamp);

            return ['history' => $rows, 'updated_at' => $timestamp];
        }

        // Returned to data entry: forwarded to internal review then bounced back
        // to CREATE for correction. Lands on CREATE.
        if (($sample['scenario'] ?? null) === 'returned_to_entry') {
            $timestamp = $timestamp->copy()->addHours(26);
            $rows[] = $this->historyHop('CREATE', 'INTERNAL', 'FORWARD', $timestamp);
            $timestamp = $timestamp->copy()->addHours(26);
            $rows[] = $this->historyHop('INTERNAL', 'CREATE', 'RETURN', $timestamp);

            return ['history' => $rows, 'updated_at' => $timestamp];
        }

        // Linear progression up to the sample's current stage.
        $targetIndex = (int) array_search($sample['stage'], self::PATH, true);
        for ($i = 1; $i <= $targetIndex; $i++) {
            $from = self::PATH[$i - 1];
            $to = self::PATH[$i];
            $timestamp = $timestamp->copy()->addHours(26);
            $rows[] = $this->historyHop($from, $to, $to === 'CLOSED' ? 'FINAL_APPROVE' : 'APPROVE', $timestamp);
        }

        return ['history' => $rows, 'updated_at' => $timestamp];
    }

    /**
     * @return array<string, mixed>
     */
    private function createRow(Carbon $createdAt): array
    {
        return [
            'from_stage_id' => null,
            'to_stage_id' => $this->stages['CREATE']->id,
            'action_code' => 'CREATE',
            'performed_by' => $this->actors['CREATE']->id,
            'comments' => 'إنشاء الطلب',
            'created_at' => $createdAt,
        ];
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
