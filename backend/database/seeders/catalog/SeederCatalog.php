<?php

namespace Database\Seeders\Catalog;

/**
 * Central constants for engine demo seeder catalogs.
 *
 * Anchors: 56 fixed references (28 YBRD + 28 CAC).
 * Bulk: 250 requests (125 YBRD + 125 CAC).
 * Total: 306 engine requests.
 */
class SeederCatalog
{
    public const DEMO_YEAR = 2026;
    public const ANCHOR_COUNT = 56;
    public const BULK_COUNT = 250;
    public const TOTAL_COUNT = 306;
    public const ANCHOR_SPEC_VERSION = 1;

    // ─── Anchor Hook Constants (reference anchors for tests, Playwright, QA) ───

    // Base Lovable A001 — submitted state for notification tests
    public const ANCHOR_SUBMITTED_NOTIFICATION = 'ENG-2026-YBRD-A001';
    
    // A016 — completed state (base Lovable sample)
    public const ANCHOR_FX_CONFIRM_COMPLETED_PRIMARY = 'ENG-2026-YBRD-A016';
    public const ANCHOR_FX_CONFIRM_COMPLETED_SECONDARY = 'ENG-2026-CAC-A016';

    // A017 — FX_CONFIRM active (base Lovable sample for panel tests)
    public const ANCHOR_FX_CONFIRM_PANEL = 'ENG-2026-YBRD-A017';

    // A018 — returned to entry (CREATE after INTERNAL reject)
    public const ANCHOR_RETURNED_TO_ENTRY = 'ENG-2026-YBRD-A018';

    // A019 — returned to FX
    public const ANCHOR_RETURNED_TO_FX = 'ENG-2026-YBRD-A019';

    // A020 — returned to FX_CONFIRM
    public const ANCHOR_RETURNED_TO_FX_CONFIRM = 'ENG-2026-YBRD-A020';

    // A021 — support claim active
    public const ANCHOR_SUPPORT_CLAIM_ACTIVE = 'ENG-2026-YBRD-A021';

    // A022 — support claim expired
    public const ANCHOR_SUPPORT_CLAIM_EXPIRED = 'ENG-2026-YBRD-A022';

    // A023 — duplicate invoice pair
    public const ANCHOR_DUPLICATE_YBRD = 'ENG-2026-YBRD-A023';
    public const ANCHOR_DUPLICATE_CAC = 'ENG-2026-CAC-A023';

    // A024 — document scan pending
    public const ANCHOR_SCAN_PENDING = 'ENG-2026-YBRD-A024';

    // A025 — document scan failed
    public const ANCHOR_SCAN_FAILED = 'ENG-2026-YBRD-A025';

    // A026 — document scan infected
    public const ANCHOR_SCAN_INFECTED = 'ENG-2026-YBRD-A026';

    // A027 — claim released after transition away
    public const ANCHOR_CLAIM_RELEASED = 'ENG-2026-YBRD-A027';

    // A028 — document replaced (superseded + active version)
    public const ANCHOR_DOCUMENT_REPLACED = 'ENG-2026-YBRD-A028';

    /**
     * Reference builder helper for anchor specs.
     *
     * @param string $bank 'YBRD' | 'CAC'
     * @param int $seq 1–28
     * @return string e.g. 'ENG-2026-YBRD-A001'
     */
    public static function anchorRef(string $bank, int $seq): string
    {
        return sprintf('ENG-%d-%s-A%03d', self::DEMO_YEAR, $bank, $seq);
    }

    /**
     * Reference builder helper for bulk specs.
     *
     * @param string $bank 'YBRD' | 'CAC'
     * @param int $seq 1–125 per bank
     * @return string e.g. 'ENG-2026-YBRD-B001'
     */
    public static function bulkRef(string $bank, int $seq): string
    {
        return sprintf('ENG-%d-%s-B%03d', self::DEMO_YEAR, $bank, $seq);
    }

    /**
     * Validate reference format.
     *
     * @return bool true if matches ^ENG-2026-(YBRD|CAC)-[AB][0-9]{3}$
     */
    public static function isValidReference(string $ref): bool
    {
        return preg_match('/^ENG-2026-(YBRD|CAC)-[AB]\d{3}$/', $ref) === 1;
    }
}
