<?php

/**
 * Bulk engine request scenarios matrix.
 *
 * Each row: [scenario_key, count, days_ago_min, days_ago_max]
 * Sum of counts must equal exactly 250.
 *
 * Split evenly: 125 YBRD + 125 CAC.
 * Date ranges control age spread for dashboard analytics.
 */
return [
    ['create_active', 24, 1, 14],
    ['internal_active', 20, 3, 21],
    ['returned_to_entry', 8, 7, 35],
    ['support_active', 24, 14, 50],
    ['support_claim_active', 14, 14, 50],
    ['support_claim_expired', 4, 21, 60],
    ['support_returned', 6, 21, 60],
    ['exec_active', 16, 45, 120],
    ['fx_active', 20, 21, 70],
    ['fx_confirm_active', 12, 30, 90],
    ['final_active', 8, 35, 100],
    ['completed_closed', 20, 180, 365],
    ['rejected_terminal', 12, 90, 210],
    ['claim_released', 6, 14, 45],
    ['document_replaced', 6, 10, 40],
    ['abandoned_via_api', 6, 5, 30],
    ['scan_pending', 8, 5, 30],
    ['scan_failed', 4, 10, 40],
    ['scope_cross_bank_mask', 4, 30, 90],
    ['analytics_volume', 28, 90, 365],
];
