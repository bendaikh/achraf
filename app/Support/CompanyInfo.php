<?php

namespace App\Support;

use App\Models\Setting;
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

  public static function cachetFilePath(): ?string
  {
    return static::storedFilePath(Setting::get('company_cachet'));
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
