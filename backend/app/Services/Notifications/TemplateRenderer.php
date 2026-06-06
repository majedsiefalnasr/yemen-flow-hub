<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use Illuminate\Mail\Markdown;
use Illuminate\Support\HtmlString;
use League\CommonMark\CommonMarkConverter;

class TemplateRenderer
{
    private CommonMarkConverter $converter;

    public function __construct(
        private readonly TemplateResolver $resolver,
        private readonly NotificationRegistry $registry,
        private readonly Markdown $markdown,
    ) {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Render a multipart email payload.
     *
     * The plaintext alternative is derived here from the substituted Markdown/HTML
     * source, so callers always receive text alongside the themed HTML body.
     *
     * @return array{
     *     subject: string,
     *     html: string,
     *     text: string,
     *     source: 'db'|'blade',
     *     template_version_id: int|null,
     *     locale: string
     * }
     */
    public function render(NotificationType $type, array $variables, string $locale = 'ar'): array
    {
        app()->setLocale($locale);

        $variables = $this->normalizeVariables($variables);
        $resolved = $this->resolver->resolve($type);

        if ($resolved['source'] === 'blade') {
            $html = view($resolved['view'], $variables)->render();

            return [
                'subject' => $this->substitute($resolved['subject'], $resolved['allowed_variables'], $variables, false),
                'html' => $html,
                'text' => $this->htmlToText($html),
                'source' => 'blade',
                'template_version_id' => null,
                'locale' => $locale,
            ];
        }

        return $this->renderMarkdownSource(
            $resolved['subject'],
            $resolved['body'] ?? '',
            $resolved['allowed_variables'],
            $variables,
            $resolved['template_version_id'],
            $locale,
        );
    }

    /**
     * Render sanitized, unsaved Markdown through the same themed email path as
     * persisted database templates. Used by admin preview before committing a
     * new template version.
     *
     * @return array{
     *     subject: string,
     *     html: string,
     *     text: string,
     *     source: 'preview',
     *     template_version_id: null,
     *     locale: string
     * }
     */
    public function renderSource(
        NotificationType $type,
        string $subject,
        string $body,
        array $variables,
        string $locale = 'ar'
    ): array {
        app()->setLocale($locale);

        return $this->renderMarkdownSource(
            $subject,
            $body,
            $this->registry->for($type)['allowed_variables'],
            $this->normalizeVariables($variables),
            null,
            $locale,
            'preview',
        );
    }

    /**
     * @param  array<int, string>  $allowedVariables
     * @param  array<string, mixed>  $variables
     * @return array{
     *     subject: string,
     *     html: string,
     *     text: string,
     *     source: 'db'|'preview',
     *     template_version_id: int|null,
     *     locale: string
     * }
     */
    private function renderMarkdownSource(
        string $subjectTemplate,
        string $bodyTemplate,
        array $allowedVariables,
        array $variables,
        ?int $templateVersionId,
        string $locale,
        string $source = 'db',
    ): array {
        $subject = $this->substitute($subjectTemplate, $allowedVariables, $variables, false);
        $markdownBody = $this->substitute($bodyTemplate, $allowedVariables, $variables, true);
        $htmlFragment = (string) $this->converter->convert($markdownBody);
        $html = (string) $this->markdown->render('mail::message', [
            'slot' => new HtmlString($htmlFragment),
        ]);

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $this->htmlToText($htmlFragment),
            'source' => $source,
            'template_version_id' => $templateVersionId,
            'locale' => $locale,
        ];
    }

    /**
     * @param  array<int, string>  $allowedVariables
     * @param  array<string, mixed>  $variables
     */
    private function substitute(string $template, array $allowedVariables, array $variables, bool $escapeHtml): string
    {
        $map = [];

        foreach ($allowedVariables as $variable) {
            $value = $variables[$variable] ?? '';
            $value = is_scalar($value) ? (string) $value : '';
            $map['{{'.$variable.'}}'] = $escapeHtml ? e($value) : $value;
        }

        $rendered = strtr($template, $map);

        return preg_replace('/\{\{\s*[^}]+\s*\}\}/', '', $rendered) ?? $rendered;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function normalizeVariables(array $variables): array
    {
        if (! array_key_exists('reference_number', $variables) && array_key_exists('request_reference', $variables)) {
            $variables['reference_number'] = $variables['request_reference'];
        }

        return $variables;
    }

    private function htmlToText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
