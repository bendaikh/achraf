<?php

namespace App\Support;

/**
 * Public disk URLs for Hostinger-style layouts where the web root is the
 * project root (public_html) rather than Laravel's public/ directory.
 */
class PublicStorage
{
    public static function url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        foreach (['storage/app/public/', 'public/storage/', 'storage/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        return asset(self::urlPrefix().$path);
    }

    /**
     * Relative URL prefix ending with a slash (e.g. "storage/" or "public/storage/").
     */
    public static function urlPrefix(): string
    {
        // Project root is the document root (index.php next to public/), so the
        // storage symlink is reachable at /public/storage/...
        if (is_file(base_path('index.php')) && is_dir(public_path('storage'))) {
            $baseReal = realpath(base_path());
            $publicReal = realpath(public_path());
            if ($baseReal && $publicReal && $baseReal !== $publicReal) {
                return 'public/storage/';
            }
        }

        return 'storage/';
    }
}
