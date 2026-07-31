<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
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

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $root = $dom->getElementById('app-page-root');
        if (! $root instanceof DOMElement) {
            return null;
        }

        $pageTitleEl = $dom->getElementById('app-page-title');
        $tabsEl = $dom->getElementById('app-module-tabs');

        $title = '';
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            $title = trim($titles->item(0)?->textContent ?? '');
        }

        $tabsHtml = '';
        if ($tabsEl instanceof DOMElement && ! $tabsEl->hasAttribute('hidden')) {
            $tabsHtml = trim(self::innerHtml($tabsEl));
        }

        return [
            'title' => $title !== '' ? $title : 'LAV\'FAST',
            'page_title' => trim($pageTitleEl?->textContent ?? '') ?: 'hsabati',
            'url' => $request->fullUrlWithoutQuery(['soft_nav']),
            'html' => self::innerHtml($root),
            'module' => self::moduleKey($request),
            'tabs_html' => $tabsHtml,
            'assets' => [],
        ];
    }

    private static function innerHtml(DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }
}
