<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Soft navigation protocol for keeping the app shell mounted.
 *
 * Controllers may return SoftNavigation::response() directly (Dashboard),
 * or render a normal with-sidebar view — ConvertSoftNavigationResponse
 * extracts #app-page-root into JSON. Client: public/js/soft-nav.js.
 */
class SoftNavigation
{
    public const HEADER = 'X-Soft-Navigation';

    public const PAGE_START = '<!--soft-nav:page:start-->';

    public const PAGE_END = '<!--soft-nav:page:end-->';

    public const TABS_START = '<!--soft-nav:tabs:start-->';

    public const TABS_END = '<!--soft-nav:tabs:end-->';

    public static function wants(Request $request): bool
    {
        return $request->headers->get(self::HEADER) === '1'
            || $request->boolean('soft_nav');
    }

    /**
     * @param  array{
     *     title: string,
     *     page_title: string,
     *     url: string,
     *     html: string,
     *     module?: string|null,
     *     tabs_html?: string,
     *     assets?: list<array{type: string, src?: string, content?: string}>
     * }  $payload
     */
    public static function response(array $payload): JsonResponse
    {
        return response()->json([
            'title' => $payload['title'],
            'page_title' => $payload['page_title'],
            'url' => $payload['url'],
            'html' => $payload['html'],
            'module' => $payload['module'] ?? null,
            'tabs_html' => $payload['tabs_html'] ?? '',
            'assets' => $payload['assets'] ?? [],
        ]);
    }

    public static function moduleKey(Request $request): ?string
    {
        $modules = Navigation::modules(Auth::user());
        $active = Navigation::activeModule($modules, $request);

        if (! $active) {
            return null;
        }

        return $active['key'] ?? $active['route'] ?? null;
    }

    /**
     * Extract soft-nav payload from a full with-sidebar HTML document.
     *
     * Uses comment markers (not DOMDocument) so inline scripts and Alpine
     * attributes that contain HTML-like strings stay intact.
     *
     * @return array<string, mixed>|null
     */
    public static function extractFromHtml(string $html, Request $request): ?array
    {
        if (! str_contains($html, self::PAGE_START) || ! str_contains($html, 'app-page-root')) {
            return null;
        }

        $pageHtml = self::between($html, self::PAGE_START, self::PAGE_END);
        if ($pageHtml === null) {
            return null;
        }

        $tabsHtml = '';
        $tabsOpen = self::matchFirst('/<div[^>]*\bid=(["\'])app-module-tabs\1[^>]*>/i', $html);
        if ($tabsOpen !== null && ! preg_match('/\bhidden\b/i', $tabsOpen)) {
            $tabsHtml = trim((string) self::between($html, self::TABS_START, self::TABS_END));
        }

        $title = trim((string) self::matchFirst('/<title[^>]*>(.*?)<\/title>/is', $html, 1));
        $pageTitle = trim(html_entity_decode(
            strip_tags((string) self::matchFirst('/<[^>]*\bid=(["\'])app-page-title\1[^>]*>(.*?)<\//is', $html, 2)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));

        return [
            'title' => $title !== '' ? $title : 'LAV\'FAST',
            'page_title' => $pageTitle !== '' ? $pageTitle : 'hsabati',
            'url' => $request->fullUrlWithoutQuery(['soft_nav']),
            'html' => $pageHtml,
            'module' => self::moduleKey($request),
            'tabs_html' => $tabsHtml,
            'assets' => [],
        ];
    }

    private static function between(string $html, string $start, string $end): ?string
    {
        $startPos = strpos($html, $start);
        if ($startPos === false) {
            return null;
        }

        $contentPos = $startPos + strlen($start);
        $endPos = strpos($html, $end, $contentPos);
        if ($endPos === false) {
            return null;
        }

        return substr($html, $contentPos, $endPos - $contentPos);
    }

    private static function matchFirst(string $pattern, string $html, int $group = 0): ?string
    {
        if (! preg_match($pattern, $html, $matches)) {
            return null;
        }

        return $matches[$group] ?? null;
    }
}
