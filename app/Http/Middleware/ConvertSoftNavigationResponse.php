<?php

namespace App\Http\Middleware;

use App\Support\SoftNavigation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConvertSoftNavigationResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        self::appendVaryHeader($response);

        if (! SoftNavigation::wants($request)) {
            return $response;
        }

        if ($response instanceof JsonResponse
            || $response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse) {
            return $response;
        }

        if ($response->isRedirection() || $response->getStatusCode() !== 200) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $html = $response->getContent();
        if (! is_string($html) || $html === '') {
            return $response;
        }

        $payload = SoftNavigation::extractFromHtml($html, $request);
        if ($payload === null) {
            return $response;
        }

        return SoftNavigation::response($payload);
    }

    private static function appendVaryHeader(Response $response): void
    {
        $existing = trim((string) $response->headers->get('Vary', ''));
        $required = SoftNavigation::HEADER.', Accept, X-Requested-With';

        if ($existing === '') {
            $response->headers->set('Vary', $required);

            return;
        }

        foreach (explode(',', $required) as $token) {
            $token = trim($token);
            if ($token !== '' && ! str_contains($existing, $token)) {
                $existing .= ', '.$token;
            }
        }

        $response->headers->set('Vary', $existing);
    }
}
