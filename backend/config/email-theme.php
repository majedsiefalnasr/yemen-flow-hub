<?php

/*
|--------------------------------------------------------------------------
| Email Design System — Token Source
|--------------------------------------------------------------------------
|
| This file MIRRORS the canonical design tokens defined in DESIGN.md (and
| summarised in AGENTS.md#Design Rules). It is the SINGLE SOURCE OF TRUTH for
| all email presentation primitives (vendor mail overrides + <x-email.*>
| components). Email clients strip <style>/external CSS and rarely honor
| webfonts, so these tokens are consumed as INLINE STYLES ONLY — never as
| Tailwind classes, CSS class names, or runtime CSS variables.
|
| KEEP IN SYNC WITH DESIGN.md. If a token changes in DESIGN.md, update it here.
| Hex literals are permitted ONLY in this file — component/override source
| files must read values via config('email-theme.*').
|
*/

return [
    // Surfaces
    'background' => '#ffffff',
    'surface' => '#ffffff',

    // Text
    'primary_text' => '#1c222b',

    // Lines
    'border' => '#cccccc',

    // Brand / action
    'primary_blue' => '#0066cc',

    // Semantic state colors (non-interactive surfaces: badges, banners, icons)
    'success_text' => '#1b5e20',
    'error_text' => '#c62828',
    'warning_text' => '#f57f17',
    'voting_indigo' => '#5856d6',
    'swift_cyan' => '#32ade6',
    'locked_gray' => '#8e8e93',

    /*
    | Font stack — system Arabic fallback ONLY. No webfont (@font-face,
    | Google Fonts, Cairo/Tajawal/IBM Plex links) is used in email because
    | mail clients do not reliably load them. RTL Arabic must survive
    | Outlook/Gmail using fonts already installed on the recipient's device.
    */
    'font_family' => "'Segoe UI', Tahoma, Arial, sans-serif",
];
