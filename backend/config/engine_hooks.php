<?php

/*
|--------------------------------------------------------------------------
| Engine DI-4 Stage Hooks
|--------------------------------------------------------------------------
|
| Maps engine stage CODES to the domain side-effects bound on stage entry.
| Config-driven so re-seeding or renaming a workflow's stages does not require
| code edits. Codes reference the seeded IMPORT_FINANCING workflow
| (CREATE → INTERNAL → SUPPORT → EXEC → FX → FX_CONFIRM → FINAL → CLOSED).
|
*/

return [
    // Stage entry that triggers the financing-ledger capacity check/reserve.
    // Default: the executive-approval stage where the allocation is committed.
    'financing_reserve_stage' => env('ENGINE_HOOK_FINANCING_STAGE', 'EXEC'),

    // Stage entry that triggers external FX-confirmation (customs) PDF generation.
    'fx_pdf_stage' => env('ENGINE_HOOK_FX_PDF_STAGE', 'FX_CONFIRM'),
];
