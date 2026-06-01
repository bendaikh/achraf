<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CompanyInfo
{
  /**
   * @return array<string, mixed>
   */
  public static function all(): array
  {
    $logoPath = Setting::get('company_logo');

    return [
      'name' => Setting::get('company_name', "LAV'FAST"),
      'subtitle' => Setting::get('company_subtitle'),
      'address' => Setting::get('company_address'),
      'city' => Setting::get('company_city'),
      'postal_code' => Setting::get('company_postal_code'),
      'country' => Setting::get('company_country', 'Maroc'),
      'phone' => Setting::get('company_phone'),
      'email' => Setting::get('company_email'),
      'ice' => Setting::get('company_ice'),
      'patente' => Setting::get('company_patente'),
      'rc' => Setting::get('company_rc'),
      'if' => Setting::get('company_if'),
      'cnss' => Setting::get('company_cnss'),
      'logo_url' => static::publicAssetUrl($logoPath),
      'logo_path' => static::logoFilePath(),
      'cachet_url' => static::publicAssetUrl(Setting::get('company_cachet')),
      'cachet_path' => static::cachetFilePath(),
    ];
  }

  public static function logoFilePath(): ?string
  {
    return static::storedFilePath(Setting::get('company_logo'));
  }

  public static function logoAssetUrl(): ?string
  {
    $path = Setting::get('company_logo');
    if ($path && Storage::disk('public')->exists($path)) {
      return asset('storage/'.$path);
    }

    return null;
  }

  public static function logoFilePathForPdf(): ?string
  {
    $path = static::logoFilePath();
    if (! $path) {
      return null;
    }

    $realPath = realpath($path) ?: $path;

    return str_replace('\\', '/', $realPath);
  }

  public static function cachetFilePath(): ?string
  {
    return static::storedFilePath(Setting::get('company_cachet'));
  }

  /** Must match .facture-signature-box in facture-styles (240px column, 130px box). */
  public const INVOICE_CACHET_BOX_WIDTH = 228;

  public const INVOICE_CACHET_BOX_HEIGHT = 124;

  /**
   * Cachet source and display size for invoices (browser + DomPDF).
   * Fills the signature box; boosts scale when the file has extra white margins.
   *
   * @return array{src: string, width: int, height: int}|null
   */
  public static function cachetForPrint(bool $forPdf = false, ?int $boxWidth = null, ?int $boxHeight = null): ?array
  {
    $storedPath = Setting::get('company_cachet');
    if (! $storedPath || ! Storage::disk('public')->exists($storedPath)) {
      return null;
    }

    $absolutePath = Storage::disk('public')->path($storedPath);
    $info = @getimagesize($absolutePath);
    if (! $info) {
      return null;
    }

    [$imgW, $imgH] = [$info[0], $info[1]];
    $ratio = $imgW / max(1, $imgH);

    $innerW = $boxWidth ?? static::INVOICE_CACHET_BOX_WIDTH;
    $innerH = $boxHeight ?? static::INVOICE_CACHET_BOX_HEIGHT;

    $width = $innerW;
    $height = (int) round($width / $ratio);
    if ($height > $innerH) {
      $height = $innerH;
      $width = (int) round($height * $ratio);
    }

    $boost = static::cachetWhitespaceBoost(
      $absolutePath,
      $info[2] ?? IMAGETYPE_JPEG,
      filemtime($absolutePath) ?: 0
    );
    $width = (int) round($width * $boost);
    $height = (int) round($height * $boost);

    if ($width > $innerW || $height > $innerH) {
      $scale = min($innerW / max(1, $width), $innerH / max(1, $height));
      $width = max(1, (int) round($width * $scale));
      $height = max(1, (int) round($height * $scale));
    }

    if ($forPdf) {
      $realPath = realpath($absolutePath) ?: $absolutePath;
      $src = str_replace('\\', '/', $realPath);
    } else {
      $src = asset('storage/'.$storedPath);
    }

    return [
      'src' => $src,
      'width' => $width,
      'height' => $height,
    ];
  }

  /**
   * Scale factor when the cachet file has large white borders around the ink.
   */
  public static function forgetCachetBoostCache(): void
  {
    $path = static::cachetFilePath();
    if (! $path) {
      return;
    }

    $mtime = filemtime($path) ?: 0;
    Cache::forget('company_cachet_boost:'.md5($path.'|'.$mtime));
  }

  protected static function cachetWhitespaceBoost(string $absolutePath, int $imageType, int $mtime): float
  {
    if (! extension_loaded('gd')) {
      return 1.35;
    }

    $cacheKey = 'company_cachet_boost:'.md5($absolutePath.'|'.$mtime);

    return Cache::remember($cacheKey, now()->addDays(30), function () use ($absolutePath, $imageType) {
      return static::detectCachetWhitespaceBoost($absolutePath, $imageType);
    });
  }

  protected static function detectCachetWhitespaceBoost(string $absolutePath, int $imageType): float
  {
    $image = match ($imageType) {
      IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
      IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
      IMAGETYPE_GIF => @imagecreatefromgif($absolutePath),
      default => @imagecreatefromstring((string) file_get_contents($absolutePath)),
    };

    if ($image === false) {
      return 1.35;
    }

    $fullW = imagesx($image);
    $fullH = imagesy($image);
    $thumbW = 200;
    $thumbH = max(1, (int) round($fullH * ($thumbW / max(1, $fullW))));
    $thumb = imagescale($image, $thumbW, $thumbH, IMG_BILINEAR_FIXED);
    imagedestroy($image);

    if ($thumb === false) {
      return 1.35;
    }

    $width = imagesx($thumb);
    $height = imagesy($thumb);
    $step = 2;

    $minX = $width;
    $minY = $height;
    $maxX = 0;
    $maxY = 0;
    $found = false;

    for ($y = 0; $y < $height; $y += $step) {
      for ($x = 0; $x < $width; $x += $step) {
        if (! static::cachetPixelIsBackground($thumb, $x, $y)) {
          $found = true;
          $minX = min($minX, $x);
          $minY = min($minY, $y);
          $maxX = max($maxX, $x);
          $maxY = max($maxY, $y);
        }
      }
    }

    imagedestroy($thumb);

    if (! $found) {
      return 1.35;
    }

    $contentW = max($step, $maxX - $minX + $step);
    $contentH = max($step, $maxY - $minY + $step);

    $boost = min($width / $contentW, $height / $contentH);

    return min(2.6, max(1.35, $boost));
  }

  /**
   * @param  \GdImage  $image
   */
  protected static function cachetPixelIsBackground($image, int $x, int $y): bool
  {
    $rgb = imagecolorat($image, $x, $y);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;

    return $r >= 242 && $g >= 242 && $b >= 242;
  }

  protected static function storedFilePath(?string $path): ?string
  {
    if ($path && Storage::disk('public')->exists($path)) {
      return Storage::disk('public')->path($path);
    }

    return null;
  }

  protected static function publicAssetUrl(?string $path): ?string
  {
    if ($path && Storage::disk('public')->exists($path)) {
      return Storage::disk('public')->url($path);
    }

    return null;
  }

  public static function formattedAddress(): string
  {
    $parts = array_filter([
      static::all()['address'] ?? null,
      trim(implode(' ', array_filter([
        static::all()['postal_code'] ?? null,
        static::all()['city'] ?? null,
      ]))),
      static::all()['country'] ?? null,
    ]);

    return implode("\n", $parts) ?: '';
  }

  public static function legalLines(): array
  {
    $info = static::all();
    $lines = [];

    if ($info['ice']) {
      $lines[] = 'ICE: '.$info['ice'];
    }
    if ($info['rc']) {
      $lines[] = 'RC: '.$info['rc'];
    }
    if ($info['if']) {
      $lines[] = 'IF: '.$info['if'];
    }
    if ($info['patente']) {
      $lines[] = 'Patente: '.$info['patente'];
    }
    if ($info['cnss']) {
      $lines[] = 'CNSS: '.$info['cnss'];
    }

    return $lines;
  }
}
