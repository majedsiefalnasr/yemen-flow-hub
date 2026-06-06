<?php

namespace Tests\Feature\Notifications;

use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Story 15.2 — Email Design System.
 *
 * Verifies the email presentation primitives: the token source
 * (config/email-theme.php), the six <x-email.*> components, the RTL/Arabic
 * standard layout, and the hard architectural constraints (inline styles only,
 * tokens read from config, no raw hex outside config, no Tailwind, no webfont).
 */
#[Group('email-design-system')]
class EmailThemeTest extends TestCase
{
    /**
     * Each component: variant -> the config token key whose value must appear.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function componentTokenProvider(): array
    {
        return [
            'status-badge success' => ['<x-email.status-badge variant="success" label="Approved" />', 'email-theme.success_text'],
            'status-badge voting' => ['<x-email.status-badge variant="voting">Voting</x-email.status-badge>', 'email-theme.voting_indigo'],
            'data-row' => ['<x-email.data-row label="Amount" value="100 USD" />', 'email-theme.border'],
            'info-box warning' => ['<x-email.info-box variant="warning">Note</x-email.info-box>', 'email-theme.warning_text'],
            'action-card' => ['<x-email.action-card url="https://example.test" buttonText="Open">Body</x-email.action-card>', 'email-theme.primary_blue'],
            'otp-code' => ['<x-email.otp-code code="123456" />', 'email-theme.primary_text'],
            'confidentiality-notice' => ['<x-email.confidentiality-notice />', 'email-theme.locked_gray'],
        ];
    }

    /**
     * AC6.15 — every component renders, uses inline styles, and emits the
     * theme token value read from config (NOT a hardcoded hex literal).
     */
    #[DataProvider('componentTokenProvider')]
    public function test_component_renders_with_inline_token_styles(string $template, string $tokenKey): void
    {
        $tokenValue = config($tokenKey);
        $this->assertIsString($tokenValue, "Missing config token: {$tokenKey}");

        $html = Blade::render($template);

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('style="', $html, 'Component must use inline styles.');
        $this->assertStringContainsString($tokenValue, $html, "Component must render token value from config({$tokenKey}).");
    }

    /**
     * AC6.15 — the OTP code component renders exactly the string it is given
     * (it does not generate or fetch codes).
     */
    public function test_otp_code_component_renders_provided_code(): void
    {
        $html = Blade::render('<x-email.otp-code :code="$code" />', ['code' => '987654']);

        $this->assertStringContainsString('987654', $html);
    }

    /**
     * AC6.16 — source-file guard: component sources and vendor mail overrides
     * contain NO raw hex literal. Hex may live ONLY in config/email-theme.php.
     */
    public function test_source_files_contain_no_hex_outside_config(): void
    {
        $files = array_merge(
            glob(resource_path('views/components/email/*.blade.php')),
            $this->vendorMailOverrideFiles(),
        );

        $this->assertNotEmpty($files, 'Expected email component and vendor override source files to exist.');

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            $this->assertSame(
                0,
                preg_match('/#[0-9a-fA-F]{3,8}\b/', $contents),
                "Raw hex literal found in {$file}. Move it to config/email-theme.php and read via config().",
            );
        }
    }

    /**
     * AC1 — the token source exists and is the single place hex values live.
     */
    public function test_theme_config_holds_hex_tokens(): void
    {
        $this->assertSame('#0066cc', config('email-theme.primary_blue'));
        $this->assertSame('#1c222b', config('email-theme.primary_text'));
        $this->assertSame('#cccccc', config('email-theme.border'));
        $this->assertMatchesRegularExpression('/#[0-9a-fA-F]{6}/', config('email-theme.success_text'));
    }

    /**
     * AC2 / AC6.17 — the standard layout is RTL/Arabic, uses no Tailwind utility
     * classes, includes no webfont, and inherits the confidentiality notice.
     */
    public function test_standard_layout_is_rtl_arabic_without_webfont_or_tailwind(): void
    {
        $html = (string) app(Markdown::class)->render('mail::message', [
            'slot' => new HtmlString('<p>اختبار</p>'),
        ]);

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('lang="ar"', $html);

        // No webfont of any kind.
        $this->assertStringNotContainsString('@font-face', $html);
        $this->assertStringNotContainsString('fonts.googleapis', $html);
        $this->assertStringNotContainsString('fonts.gstatic', $html);
        $this->assertDoesNotMatchRegularExpression('/Cairo|Tajawal|IBM Plex/i', $html);

        // No Tailwind/utility class usage.
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\b(?:text-|bg-|p-\d|m-\d|flex|grid|rounded-|gap-)/', $html);

        // Confidentiality notice inherited through the shared footer path (AC4).
        $this->assertStringContainsString('إشعار سرية', $html);

        // Tokens applied inline.
        $this->assertStringContainsString(config('email-theme.primary_text'), $html);
    }

    /**
     * AC5.14 — publishing the vendor mail override does not break existing
     * Epic-14 flat blade email views.
     */
    public function test_existing_email_views_still_render(): void
    {
        $this->assertNotEmpty(view('emails.mfa-otp', ['otp' => '123456', 'ttlMinutes' => 5])->render());
        $this->assertNotEmpty(view('emails.password-recovery-otp', ['otp' => '123456', 'ttlMinutes' => 5])->render());
        $this->assertNotEmpty(view('emails.test-email')->render());
    }

    /**
     * @return array<int, string>
     */
    private function vendorMailOverrideFiles(): array
    {
        $base = resource_path('views/vendor/mail/html');

        return array_filter(array_map(
            static fn (string $name): string => "{$base}/{$name}.blade.php",
            ['message', 'button', 'panel', 'layout', 'header', 'footer', 'subcopy'],
        ), 'is_file');
    }
}
