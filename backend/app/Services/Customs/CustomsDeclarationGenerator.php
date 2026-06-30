<?php

namespace App\Services\Customs;

use App\Models\CustomsDeclaration;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Model-agnostic external FX-confirmation (customs) PDF generator. Accepts a normalized
 * snapshot array — built from either an ImportRequest (legacy CustomsService) or an
 * EngineRequest (engine CustomsFxPdfEffect) — renders the RTL DomPDF, stores it on the
 * private disk, and returns the artifacts the caller needs to persist a CustomsDeclaration.
 *
 * Extracted from CustomsService::generate() so the legacy and engine paths share one
 * render + numbering + storage implementation. No model coupling here.
 */
class CustomsDeclarationGenerator
{
    /**
     * Snapshot keys (1:1 with CustomsService::snapshot()):
     *   reference_number, bank{id,name,code}, supplier_name, amount, currency,
     *   goods_description, port_of_entry, bank_approved_at, support_approved_at,
     *   executive_decided_at.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  string  $storageKey  identifies the storage sub-path (e.g. the request id)
     * @return array{declaration_number: string, pdf_path: string, stored_path: string, issued_at: CarbonInterface, snapshot: array<string, mixed>}
     */
    public function generate(array $snapshot, User $issuer, int|string $storageKey): array
    {
        $issuedAt = now();
        $declarationNumber = $this->nextDeclarationNumber();

        $pdf = Pdf::loadView('pdf.customs-declaration', [
            'declarationNumber' => $declarationNumber,
            'issuedAt' => $issuedAt,
            'snapshot' => $snapshot,
            'issuer' => $issuer,
        ])->setPaper('a4');

        $relativePath = "customs/{$storageKey}/{$declarationNumber}.pdf";
        $storedPath = 'private/'.$relativePath;
        Storage::disk('local')->put($storedPath, $pdf->output());

        return [
            'declaration_number' => $declarationNumber,
            'pdf_path' => $relativePath,
            'stored_path' => $storedPath,
            'issued_at' => $issuedAt,
            'snapshot' => $snapshot,
        ];
    }

    public function deleteStored(string $storedPath): void
    {
        Storage::disk('local')->delete($storedPath);
    }

    private function nextDeclarationNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "CD-{$year}-";
        $isMySQL = DB::connection()->getDriverName() === 'mysql';

        if ($isMySQL) {
            DB::statement("SELECT GET_LOCK('customs_declaration_number', 10)");
        }

        try {
            $latest = CustomsDeclaration::query()
                ->where('declaration_number', 'like', $prefix.'%')
                ->latest('id')
                ->value('declaration_number');

            $next = 1;
            if ($latest) {
                $parts = explode('-', $latest);
                $next = ((int) ($parts[2] ?? 0)) + 1;
            }

            return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        } finally {
            if ($isMySQL) {
                DB::statement("SELECT RELEASE_LOCK('customs_declaration_number')");
            }
        }
    }
}
