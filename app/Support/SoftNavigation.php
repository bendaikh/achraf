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
 *
 * Extraction is regex/balanced-tag based (not DOMDocument) so Alpine.js
 * attributes like @click / @keydown are preserved.
 */
class SoftNavigation
{
    public const HEADER = 'X-Soft-Navigation';

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
     * @return array<string, mixed>|null
     */
    public static function extractFromHtml(string $html, Request $request): ?array
    {
        if (! str_contains($html, 'app-page-root')) {
            return null;
        }

        $rootHtml = self::extractInnerById($html, 'app-page-root');
        if ($rootHtml === null) {
            return null;
        }

        $pageTitle = self::extractInnerById($html, 'app-page-title');
        $pageTitle = trim(html_entity_decode(strip_tags((string) $pageTitle), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $tabsHidden = (bool) preg_match(
            '/<[^>]*\bid=["\']app-module-tabs["\'][^>]*\bhidden\b/i',
            $html
        );
        $tabsHtml = '';
        if (! $tabsHidden) {
            $tabsHtml = trim((string) self::extractInnerById($html, 'app-module-tabs'));
        }

        $title = 'LAV\'FAST';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titleMatch)) {
            $extracted = trim(html_entity_decode(strip_tags($titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($extracted !== '') {
                $title = $extracted;
            }
        }

        return [
            'title' => $title,
            'page_title' => $pageTitle !== '' ? $pageTitle : 'hsabati',
            'url' => $request->fullUrlWithoutQuery(['soft_nav']),
            'html' => $rootHtml,
            'module' => self::moduleKey($request),
            'tabs_html' => $tabsHtml,
            'assets' => [],
        ];
    }

    /**
     * Return inner HTML of the first element with the given id, preserving raw attributes.
     */
    private static function extractInnerById(string $html, string $id): ?string
    {
        if (! preg_match(
            '/<([a-z][a-z0-9]*)\b[^>]*\bid=(["\'])'.preg_quote($id, '/').'\2[^>]*>/i',
            $html,
            $match,
            PREG_OFFSET_CAPTURE
        )) {
            return null;
        }

        $tag = $match[1][0];
        $openEnd = $match[0][1] + strlen($match[0][0]);
        $close = self::findMatchingCloseTag($html, $tag, $openEnd);

        if ($close === null) {
            return null;
        }

        return substr($html, $openEnd, $close - $openEnd);
    }

    private static function findMatchingCloseTag(string $html, string $tag, int $from): ?int
    {
        $tagLower = strtolower($tag);
        $openNeedle = '<'.$tagLower;
        $closeNeedle = '</'.$tagLower.'>';
        $length = strlen($html);
        $pos = $from;
        $depth = 1;

        while ($depth > 0 && $pos < $length) {
            $nextOpen = self::findNextOpenTag($html, $openNeedle, $pos);
            $nextClose = stripos($html, $closeNeedle, $pos);

            if ($nextClose === false) {
                return null;
            }

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + strlen($openNeedle);

                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $nextClose;
            }
            $pos = $nextClose + strlen($closeNeedle);
        }

        return null;
    }

    private static function findNextOpenTag(string $html, string $openNeedle, int $from): int|false
    {
        $pos = $from;
        $length = strlen($html);
        $needleLen = strlen($openNeedle);

        while ($pos < $length) {
            $found = stripos($html, $openNeedle, $pos);
            if ($found === false) {
                return false;
            }

            $after = $html[$found + $needleLen] ?? '';
            if ($after === '>' || ctype_space($after) || $after === '/') {
                return $found;
            }

            $pos = $found + $needleLen;
        }

        return false;
    }
}
