#!/usr/bin/env python3
"""
Build the Story 9.2 parity-evidence triplets.

For every entry in PAGES below, copy the lovable source PNG and the current Nuxt
Playwright source PNG into `_bmad-output/parity-evidence/<area>/<page>/`, then
generate `side-by-side.png` via Pillow (lovable on the left, current on the
right, matched height).

Rows where one side is missing are still materialized: the existing PNG is
copied, a stub side-by-side is generated (the available image + a same-height
"MISSING" placeholder), and the matrix records the gap.

Run from repo root:
    python3 tools/build-parity-evidence.py
"""
from __future__ import annotations

import shutil
import sys
from pathlib import Path
from typing import Optional

from PIL import Image, ImageDraw, ImageFont

REPO = Path(__file__).resolve().parent.parent
LOVABLE_DIR = REPO / "lovable" / "screenshots"
CURRENT_DIR = REPO / "frontend" / "tests" / "screenshots"
OUT_DIR = REPO / "_bmad-output" / "parity-evidence"


# Note: some lovable subdirs include a trailing space — preserved verbatim.
PAGES: list[dict] = [
    # ── Auth (7.1) ──────────────────────────────────────────────────────────
    {"area": "auth", "page": "login",
     "lovable": "login.png",
     "current": "7-1/login-desktop-darwin.png",
     "current_mobile": "7-1/login-mobile-darwin.png"},
    {"area": "auth", "page": "login-otp",
     "lovable": "login-otp.png",
     "current": "7-1/login-otp-desktop-darwin.png"},

    # ── Dashboards (7.2) ────────────────────────────────────────────────────
    {"area": "dashboards", "page": "data-entry",
     "lovable": "DATA_ENTRY/dashboard.png",
     "current": "7-2/data-entry-desktop-darwin.png",
     "current_mobile": "7-2/data-entry-mobile-darwin.png"},
    {"area": "dashboards", "page": "bank-reviewer",
     "lovable": "BANK_REVIEWER /dashboard.png",
     "current": "7-2/bank-reviewer-desktop-darwin.png",
     "current_mobile": "7-2/bank-reviewer-mobile-darwin.png"},
    {"area": "dashboards", "page": "bank-admin",
     "lovable": "BANK-ADMIN/dashboard.png",
     "current": "7-2/bank-admin-desktop-darwin.png",
     "current_mobile": "7-2/bank-admin-mobile-darwin.png"},
    {"area": "dashboards", "page": "support-committee",
     "lovable": "SUPPORT_COMMITTEE /dashboard.png",
     "current": "7-2/support-committee-desktop-darwin.png",
     "current_mobile": "7-2/support-committee-mobile-darwin.png"},
    {"area": "dashboards", "page": "swift-officer",
     "lovable": "SWIFT_OFFICER/dashboard.png",
     "current": "7-2/swift-officer-desktop-darwin.png",
     "current_mobile": "7-2/swift-officer-mobile-darwin.png"},
    {"area": "dashboards", "page": "executive-member",
     "lovable": "EXECUTIVE_MEMBER/dashboard.png",
     "current": "7-2/executive-member-desktop-darwin.png",
     "current_mobile": "7-2/executive-member-mobile-darwin.png"},
    {"area": "dashboards", "page": "committee-director",
     "lovable": "COMMITTEE_DIRECTOR/dashboard.png",
     "current": "7-2/committee-director-desktop-darwin.png",
     "current_mobile": "7-2/committee-director-mobile-darwin.png"},
    {"area": "dashboards", "page": "cby-admin",
     "lovable": "CBY_ADMIN /dashboard.png",
     "current": "7-2/cby-admin-desktop-darwin.png",
     "current_mobile": "7-2/cby-admin-mobile-darwin.png"},

    # ── Requests list (7.3) ─────────────────────────────────────────────────
    {"area": "requests", "page": "list",
     "lovable": "CBY_ADMIN /requests.png",
     "current": "7-3/cby-admin-requests-desktop-darwin.png",
     "current_mobile": "7-3/cby-admin-requests-mobile-darwin.png"},

    # ── Request detail variants (7.4) ───────────────────────────────────────
    {"area": "requests", "page": "detail",
     "lovable": "CBY_ADMIN /requests-view-request.png",
     "current": "7-4/cby-admin-bank-approved-desktop-darwin.png"},
    {"area": "requests", "page": "detail-tabs-info",
     "lovable": "BANK-ADMIN/request-view-info-tab.png",
     "current": "7-4/data-entry-draft-desktop-darwin.png",
     "current_mobile": "7-4/data-entry-draft-mobile-darwin.png"},
    {"area": "requests", "page": "detail-tabs-parties",
     "lovable": "BANK-ADMIN/request-view-parties-tab.png",
     "current": "7-4/cby-admin-parties-desktop-darwin.png"},
    {"area": "requests", "page": "detail-tabs-documents",
     "lovable": "BANK-ADMIN/request-view-documents-tab.png",
     "current": "7-4/bank-admin-documents-desktop-darwin.png"},
    {"area": "requests", "page": "detail-voting",
     "lovable": "EXECUTIVE_MEMBER/request-view-voting-open-cast-vote.png",
     "current": "7-4/executive-member-voting-open-desktop-darwin.png",
     "current_mobile": "7-4/executive-member-voting-open-mobile-darwin.png"},
    {"area": "requests", "page": "detail-swift",
     "lovable": "SWIFT_OFFICER/request-view-pending-swift.png",
     "current": "7-4/swift-officer-waiting-swift-desktop-darwin.png"},
    {"area": "requests", "page": "detail-customs",
     "lovable": "COMMITTEE_DIRECTOR/request-view-waiting-customs.png",
     "current": "7-4/committee-director-waiting-customs-desktop-darwin.png",
     "current_mobile": "7-4/committee-director-waiting-customs-mobile-darwin.png"},
    {"area": "requests", "page": "detail-rejected",
     "lovable": "DATA_ENTRY/request-view-rejected.png",
     "current": "7-4/data-entry-rejected-desktop-darwin.png",
     "current_mobile": "7-4/data-entry-rejected-mobile-darwin.png"},
    {"area": "requests", "page": "detail-completed",
     "lovable": "DATA_ENTRY/request-view-completed.png",
     "current": "7-4/data-entry-completed-desktop-darwin.png"},

    # ── Request wizard (7.5) ────────────────────────────────────────────────
    {"area": "requests", "page": "new-step-1",
     "lovable": "BANK-ADMIN/new-request-step1-basic-info.png",
     "current": "7-5/bank-admin-new-request-step1-desktop-darwin.png",
     "current_mobile": "7-5/bank-admin-new-request-step1-mobile-darwin.png"},
    {"area": "requests", "page": "new-step-2",
     "lovable": "BANK-ADMIN/new-request-step2-supplier.png",
     "current": "7-5/bank-admin-new-request-step2-desktop-darwin.png",
     "current_mobile": "7-5/bank-admin-new-request-step2-mobile-darwin.png"},
    {"area": "requests", "page": "new-step-3",
     "lovable": "BANK-ADMIN/new-request-step3-documents.png",
     "current": "7-5/bank-admin-new-request-step3-desktop-darwin.png",
     "current_mobile": "7-5/bank-admin-new-request-step3-mobile-darwin.png"},
    {"area": "requests", "page": "new-step-4",
     "lovable": "BANK-ADMIN/new-request-step4-review-submit.png",
     "current": "7-5/bank-admin-new-request-step4-desktop-darwin.png",
     "current_mobile": "7-5/bank-admin-new-request-step4-mobile-darwin.png"},

    # ── Merchants (7.6) ─────────────────────────────────────────────────────
    {"area": "merchants", "page": "list",
     "lovable": "BANK-ADMIN/merchants-list-cards.png",
     "current": "7-6-bank-admin-merchants-list-desktop-darwin.png",
     "current_mobile": "7-6-bank-admin-merchants-list-mobile-darwin.png"},
    {"area": "merchants", "page": "view",
     "lovable": "CBY_ADMIN /merchants-view-merchant.png",
     "current": "7-6-cby-admin-merchants-view-modal-desktop-darwin.png",
     "current_mobile": "7-6-cby-admin-merchants-view-modal-mobile-darwin.png"},
    {"area": "merchants", "page": "add-modal",
     "lovable": "BANK-ADMIN/merchants-add-modal.png",
     "current": "7-6-bank-admin-merchants-add-modal-desktop-darwin.png",
     "current_mobile": "7-6-bank-admin-merchants-add-modal-mobile-darwin.png"},
    {"area": "merchants", "page": "edit-modal",
     "lovable": "BANK-ADMIN/merchants-edit-modal.png",
     "current": "7-6-bank-admin-merchants-edit-modal-desktop-darwin.png",
     "current_mobile": "7-6-bank-admin-merchants-edit-modal-mobile-darwin.png"},

    # ── Staff + admin (7.7) ─────────────────────────────────────────────────
    {"area": "staff", "page": "list",
     "lovable": "BANK-ADMIN/staff-list.png",
     "current": "7-7-bank-admin-staff-list-desktop-darwin.png",
     "current_mobile": "7-7-bank-admin-staff-list-mobile-darwin.png"},
    {"area": "staff", "page": "edit-modal",
     "lovable": "BANK-ADMIN/staff-edit-modal.png",
     "current": "7-7-bank-admin-staff-edit-modal-desktop-darwin.png"},
    {"area": "admin", "page": "banks",
     "lovable": "CBY_ADMIN /banks.png",
     # No standalone banks-list current capture exists; nearest match is the add-bank modal.
     "current": None},
    {"area": "admin", "page": "banks-view",
     "lovable": "CBY_ADMIN /banks-view-bank.png",
     "current": "7-7-cby-admin-view-bank-modal-desktop-darwin.png"},
    {"area": "admin", "page": "banks-add",
     "lovable": "CBY_ADMIN /banks-add-bank.png",
     "current": "7-7-cby-admin-add-bank-modal-desktop-darwin.png"},
    {"area": "admin", "page": "users",
     "lovable": "CBY_ADMIN /staff.png",
     "current": "7-7-cby-admin-system-users-list-desktop-darwin.png",
     "current_mobile": "7-7-cby-admin-system-users-list-mobile-darwin.png"},
    {"area": "admin", "page": "cby-staff",
     "lovable": "CBY_ADMIN /staff-add-member.png",
     "current": "7-7-cby-admin-add-user-modal-desktop-darwin.png"},
    {"area": "admin", "page": "entities",
     "lovable": "CBY_ADMIN /banks2.png",
     "current": "7-7-cby-admin-entities-list-desktop-darwin.png",
     "current_mobile": "7-7-cby-admin-entities-list-mobile-darwin.png"},
    {"area": "admin", "page": "roles",
     "lovable": "CBY_ADMIN /roles.png",
     "current": "7-7-cby-admin-roles-matrix-desktop-darwin.png",
     "current_mobile": "7-7-cby-admin-roles-matrix-mobile-darwin.png"},
    {"area": "admin", "page": "workflow-docs",
     "lovable": "CBY_ADMIN /workflow-docs.png",
     # No Playwright spec for workflow-docs yet — Story 5.7 added the page; 7.7 parity spec doesn't cover it.
     "current": None},

    # ── Reports (7.8) — no Playwright captures exist yet ────────────────────
    {"area": "reports", "page": "index",
     "lovable": "CBY_ADMIN /reports.png",
     "current": None},

    # ── Audit (7.9) — no Playwright captures exist yet ──────────────────────
    {"area": "audit", "page": "index",
     "lovable": "CBY_ADMIN /audit.png",
     "current": None},
    {"area": "audit", "page": "tab-2",
     "lovable": "CBY_ADMIN /audit-tab2.png",
     "current": None},
    {"area": "audit", "page": "tab-3",
     "lovable": "CBY_ADMIN /audit-tab3.png",
     "current": None},

    # ── Settings + profile (7.10) — no Playwright captures exist yet ───────
    {"area": "settings", "page": "index",
     "lovable": "CBY_ADMIN /settings.png",
     "current": None},
    {"area": "settings", "page": "tab-2",
     "lovable": "CBY_ADMIN /settings2.png",
     "current": None},
    {"area": "settings", "page": "tab-3",
     "lovable": "CBY_ADMIN /settings3.png",
     "current": None},
    {"area": "settings", "page": "tab-4",
     "lovable": "CBY_ADMIN /settings4.png",
     "current": None},
    {"area": "settings", "page": "tab-5",
     "lovable": "CBY_ADMIN /settings5.png",
     "current": None},
    {"area": "settings", "page": "tab-6",
     "lovable": "CBY_ADMIN /settings6.png",
     "current": None},
    {"area": "profile", "page": "index",
     "lovable": "CBY_ADMIN /profile.png",
     "current": None},

    # ── Notifications (Story 5.3, covered in nav/header) ────────────────────
    {"area": "notifications", "page": "index",
     "lovable": "CBY_ADMIN /notifications.png",
     "current": None},
    {"area": "notifications", "page": "dropdown",
     "lovable": "CBY_ADMIN /notifications-dropdown.png",
     "current": None},
    {"area": "notifications", "page": "empty",
     "lovable": "CBY_ADMIN /notifications-empty.png",
     "current": None},

    # ── Customs (Story 7.x — separate page) ─────────────────────────────────
    {"area": "customs", "page": "issue",
     "lovable": "COMMITTEE_DIRECTOR/customs-issue-page.png",
     "current": None},

    # ── Misc lovable rows captured for AC5 coverage (drift detail variants) ─
    {"area": "requests", "page": "detail-voting-pending",
     "lovable": "EXECUTIVE_MEMBER/request-view-voting-pending.png",
     "current": "7-4/executive-member-voting-pending-desktop-darwin.png"},
    {"area": "requests", "page": "detail-voting-open-director",
     "lovable": "COMMITTEE_DIRECTOR/request-view-voting-open-director.png",
     "current": "7-4/committee-director-voting-open-desktop-darwin.png"},
    {"area": "requests", "page": "detail-voting-pending-open",
     "lovable": "COMMITTEE_DIRECTOR/request-view-voting-pending-open.png",
     "current": "7-4/committee-director-voting-pending-desktop-darwin.png"},
    {"area": "requests", "page": "detail-voting-duplicate-invoice",
     "lovable": "COMMITTEE_DIRECTOR/request-view-voting-open-duplicate-invoice.png",
     "current": None},
    {"area": "requests", "page": "detail-customs-documents",
     "lovable": "COMMITTEE_DIRECTOR/request-view-documents-tab-customs.png",
     "current": "7-4/committee-director-documents-customs-desktop-darwin.png"},
    {"area": "requests", "page": "detail-customs-parties",
     "lovable": "COMMITTEE_DIRECTOR/request-view-parties-tab-customs.png",
     "current": None},
    {"area": "requests", "page": "detail-bank-internal-review",
     "lovable": "BANK_REVIEWER /request-view-internal-review.png",
     "current": "7-4/bank-reviewer-review-desktop-darwin.png",
     "current_mobile": "7-4/bank-reviewer-review-mobile-darwin.png"},
    {"area": "requests", "page": "detail-bank-actions-expanded",
     "lovable": "BANK_REVIEWER /request-view-actions-expanded.png",
     "current": None},
    {"area": "requests", "page": "detail-support-claimed",
     "lovable": "SUPPORT_COMMITTEE /request-view-claimed-actions.png",
     "current": "7-4/support-committee-approved-desktop-darwin.png"},
    {"area": "requests", "page": "detail-support-pending-claim",
     "lovable": "SUPPORT_COMMITTEE /request-view-pending-claim.png",
     "current": "7-4/support-committee-pending-claim-desktop-darwin.png",
     "current_mobile": "7-4/support-committee-pending-claim-mobile-darwin.png"},
    {"area": "requests", "page": "detail-support-approved",
     "lovable": "SUPPORT_COMMITTEE /request-view-approved.png",
     "current": "7-4/support-committee-approved-desktop-darwin.png"},
    {"area": "requests", "page": "detail-support-returned",
     "lovable": "SUPPORT_COMMITTEE /request-view-returned-to-bank.png",
     "current": "7-4/bank-admin-support-rejected-desktop-darwin.png"},
    {"area": "requests", "page": "detail-support-rejected",
     "lovable": "BANK-ADMIN/request-view-support-rejected.png",
     "current": "7-4/bank-admin-support-rejected-desktop-darwin.png"},
    {"area": "requests", "page": "detail-completed-bank-admin",
     "lovable": "BANK-ADMIN/request-view-completed.png",
     "current": "7-4/cby-admin-completed-mobile-darwin.png"},
    {"area": "requests", "page": "detail-voting-stage",
     "lovable": "BANK-ADMIN/request-view-voting-stage.png",
     "current": "7-4/committee-director-voting-pending-desktop-darwin.png"},
    {"area": "requests", "page": "detail-waiting-swift",
     "lovable": "BANK-ADMIN/request-view-waiting-swift.png",
     "current": "7-4/swift-officer-waiting-swift-desktop-darwin.png"},
    {"area": "requests", "page": "detail-swift-uploaded",
     "lovable": "SWIFT_OFFICER/request-view-swift-uploaded.png",
     "current": "7-4/swift-officer-swift-uploaded-desktop-darwin.png"},
    {"area": "requests", "page": "detail-executive-rejected-banner",
     "lovable": "EXECUTIVE_MEMBER/request-view-rejected-banner.png",
     "current": "7-4/executive-member-rejected-desktop-darwin.png"},
    {"area": "requests", "page": "detail-executive-rejected-final",
     "lovable": "EXECUTIVE_MEMBER/request-view-rejected-final.png",
     "current": None},
    {"area": "requests", "page": "detail-executive-waiting-customs",
     "lovable": "EXECUTIVE_MEMBER/request-view-waiting-customs.png",
     "current": "7-4/committee-director-waiting-customs-mobile-darwin.png"},
    {"area": "requests", "page": "detail-data-entry-draft-actions",
     "lovable": "DATA_ENTRY/request-view-draft-actions.png",
     "current": "7-4/data-entry-draft-desktop-darwin.png"},
    {"area": "requests", "page": "detail-data-entry-submitted",
     "lovable": "DATA_ENTRY/request-view-submitted.png",
     "current": "7-4/data-entry-submitted-desktop-darwin.png"},
    {"area": "requests", "page": "list-bank-reviewer",
     "lovable": "COMMITTEE_DIRECTOR/requests-list.png",
     "current": "7-3/committee-director-requests-desktop-darwin.png",
     "current_mobile": "7-3/committee-director-requests-mobile-darwin.png"},
    {"area": "requests", "page": "list-executive",
     "lovable": "EXECUTIVE_MEMBER/requests-list.png",
     "current": "7-3/executive-member-requests-desktop-darwin.png",
     "current_mobile": "7-3/executive-member-requests-mobile-darwin.png"},
    {"area": "requests", "page": "list-support",
     "lovable": "SUPPORT_COMMITTEE /requests-list.png",
     "current": "7-3/support-committee-requests-desktop-darwin.png",
     "current_mobile": "7-3/support-committee-requests-mobile-darwin.png"},
    {"area": "requests", "page": "list-swift",
     "lovable": "SWIFT_OFFICER/requests-list.png",
     "current": "7-3/swift-officer-requests-desktop-darwin.png",
     "current_mobile": "7-3/swift-officer-requests-mobile-darwin.png"},
    {"area": "requests", "page": "list-bank-admin",
     "lovable": "BANK-ADMIN/requests-list.png",
     "current": "7-3/bank-admin-requests-desktop-darwin.png",
     "current_mobile": "7-3/bank-admin-requests-mobile-darwin.png"},

    # ── Other operational dashboards & admin (7.x) ──────────────────────────
    {"area": "dashboards", "page": "shell-collapsed",
     "lovable": "CBY_ADMIN /dashboard-sidebar-collapsed.png",
     "current": "7-1/dashboard-collapsed-desktop-darwin.png"},
    {"area": "dashboards", "page": "shell-expanded",
     "lovable": "CBY_ADMIN /dashboard.png",
     "current": "7-1/dashboard-expanded-desktop-darwin.png"},
    {"area": "dashboards", "page": "dark-mode",
     "lovable": "CBY_ADMIN /dark-mode.png",
     "current": None},

    # ── Reports per-role lovable variants (AC5 coverage) ────────────────────
    {"area": "reports", "page": "bank-admin",
     "lovable": "BANK-ADMIN/reports.png",
     "current": None},
    {"area": "reports", "page": "support-committee",
     "lovable": "SUPPORT_COMMITTEE /reports.png",
     "current": None},
    {"area": "reports", "page": "committee-director",
     "lovable": "COMMITTEE_DIRECTOR/reports.png",
     "current": None},
    {"area": "reports", "page": "executive-member",
     "lovable": "EXECUTIVE_MEMBER/reports.png",
     "current": None},

    # ── Audit per-role (AC5 coverage) ───────────────────────────────────────
    {"area": "audit", "page": "committee-director-log",
     "lovable": "COMMITTEE_DIRECTOR/audit-log-list.png",
     "current": None},

    # ── Admin variants (AC5 coverage) ───────────────────────────────────────
    {"area": "admin", "page": "roles-readonly",
     "lovable": "CBY_ADMIN /roles2-readonly-view.png",
     "current": None},
    {"area": "admin", "page": "cby-staff-edit",
     "lovable": "CBY_ADMIN /staff-edit-member.png",
     "current": None},
    {"area": "merchants", "page": "list-cby",
     "lovable": "CBY_ADMIN /merchants.png",
     "current": "7-6-cby-admin-merchants-table-desktop-darwin.png",
     "current_mobile": "7-6-cby-admin-merchants-table-mobile-darwin.png"},
    {"area": "merchants", "page": "list-suspended",
     "lovable": "BANK-ADMIN/merchants-list-suspended.png",
     "current": "7-6-bank-admin-merchants-suspended-desktop-darwin.png"},
    {"area": "staff", "page": "edit-modal-secondary",
     "lovable": "BANK-ADMIN/staff-edit-modal2.png",
     "current": "7-7-bank-admin-staff-add-modal-desktop-darwin.png"},
    {"area": "notifications", "page": "bank-admin",
     "lovable": "BANK-ADMIN/notifications.png",
     "current": None},

    # ── Notifications request-view variants (CBY) — AC5 coverage ────────────
    {"area": "requests", "page": "detail-note",
     "lovable": "CBY_ADMIN /requests-view-request-note.png",
     "current": None},
    {"area": "requests", "page": "detail-tab-cby-2",
     "lovable": "CBY_ADMIN /requests-view-request-tab.png",
     "current": None},
    {"area": "requests", "page": "detail-tab-cby-3",
     "lovable": "CBY_ADMIN /requests-view-request-tab2.png",
     "current": None},
    {"area": "requests", "page": "detail-view-file",
     "lovable": "CBY_ADMIN /requests-view-request-view-file.png",
     "current": None},
    {"area": "requests", "page": "detail-secondary",
     "lovable": "CBY_ADMIN /requests-view-request2.png",
     "current": None},

    # ── COMMITTEE_DIRECTOR access-denied — demo/role surface ────────────────
    {"area": "auth", "page": "access-denied",
     "lovable": "COMMITTEE_DIRECTOR/role-access-denied.png",
     "current": None},
]


def find_default_font() -> Optional[ImageFont.FreeTypeFont]:
    candidates = [
        "/System/Library/Fonts/Supplemental/Arial.ttf",
        "/System/Library/Fonts/Helvetica.ttc",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
    ]
    for c in candidates:
        if Path(c).exists():
            try:
                return ImageFont.truetype(c, 48)
            except Exception:
                continue
    return None


def render_missing_placeholder(width: int, height: int, label: str) -> Image.Image:
    img = Image.new("RGB", (width, height), color=(245, 245, 247))
    draw = ImageDraw.Draw(img)
    # Draw diagonal hatch to make MISSING obvious at a glance.
    for i in range(-height, width, 24):
        draw.line([(i, 0), (i + height, height)], fill=(230, 230, 235), width=2)
    font = find_default_font()
    text = f"MISSING — {label}"
    if font is not None:
        bbox = draw.textbbox((0, 0), text, font=font)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
        draw.text(((width - tw) / 2, (height - th) / 2), text, fill=(120, 120, 130), font=font)
    else:
        draw.text((20, height // 2), text, fill=(120, 120, 130))
    return img


def composite_side_by_side(left: Optional[Path], right: Optional[Path], out: Path) -> tuple[str, str]:
    """Return a tuple of (left_status, right_status) — 'ok' or 'missing'."""
    if left is None and right is None:
        return ("missing", "missing")

    if left is not None and left.exists():
        left_img = Image.open(left).convert("RGB")
        left_status = "ok"
    else:
        left_img = None
        left_status = "missing"

    if right is not None and right.exists():
        right_img = Image.open(right).convert("RGB")
        right_status = "ok"
    else:
        right_img = None
        right_status = "missing"

    # Normalize heights so we can place them side-by-side at the same height.
    if left_img is not None and right_img is not None:
        target_h = min(left_img.height, right_img.height, 1200)
    elif left_img is not None:
        target_h = min(left_img.height, 1200)
    elif right_img is not None:
        target_h = min(right_img.height, 1200)
    else:
        target_h = 900

    def resize_to_height(img: Image.Image, h: int) -> Image.Image:
        if img.height == h:
            return img
        scale = h / img.height
        return img.resize((max(1, int(img.width * scale)), h), Image.LANCZOS)

    if left_img is not None:
        left_img = resize_to_height(left_img, target_h)
    if right_img is not None:
        right_img = resize_to_height(right_img, target_h)

    if left_img is None:
        # Use right's width as a hint
        left_img = render_missing_placeholder(right_img.width, target_h, "lovable")
    if right_img is None:
        right_img = render_missing_placeholder(left_img.width, target_h, "current")

    gap = 16
    composite = Image.new("RGB", (left_img.width + gap + right_img.width, target_h), color=(255, 255, 255))
    composite.paste(left_img, (0, 0))
    composite.paste(right_img, (left_img.width + gap, 0))

    out.parent.mkdir(parents=True, exist_ok=True)
    composite.save(out, format="PNG", optimize=True)
    return (left_status, right_status)


def main() -> int:
    rows_written = 0
    rows_lovable_missing = 0
    rows_current_missing = 0
    for entry in PAGES:
        area = entry["area"]
        page = entry["page"]
        dest = OUT_DIR / area / page
        dest.mkdir(parents=True, exist_ok=True)

        lovable_src = entry.get("lovable")
        current_src = entry.get("current")
        current_mobile_src = entry.get("current_mobile")

        lovable_path: Optional[Path] = None
        current_path: Optional[Path] = None
        current_mobile_path: Optional[Path] = None

        if lovable_src is not None:
            candidate = LOVABLE_DIR / lovable_src
            if candidate.exists():
                lovable_path = dest / "lovable.png"
                shutil.copy2(candidate, lovable_path)
            else:
                print(f"  ! lovable source missing: {lovable_src}", file=sys.stderr)

        if current_src is not None:
            candidate = CURRENT_DIR / current_src
            if candidate.exists():
                current_path = dest / "current.png"
                shutil.copy2(candidate, current_path)
            else:
                print(f"  ! current source missing: {current_src}", file=sys.stderr)

        if current_mobile_src is not None:
            candidate = CURRENT_DIR / current_mobile_src
            if candidate.exists():
                current_mobile_path = dest / "current-mobile.png"
                shutil.copy2(candidate, current_mobile_path)

        side = dest / "side-by-side.png"
        left_status, right_status = composite_side_by_side(lovable_path, current_path, side)

        if left_status == "missing":
            rows_lovable_missing += 1
        if right_status == "missing":
            rows_current_missing += 1
        rows_written += 1
        print(f"  + {area}/{page}  (lovable={left_status} current={right_status} mobile={'ok' if current_mobile_path else 'n/a'})")

    print()
    print(f"Wrote {rows_written} evidence directories.")
    print(f"  Lovable-side missing: {rows_lovable_missing}")
    print(f"  Current-side missing: {rows_current_missing}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
