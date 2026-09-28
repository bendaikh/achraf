<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class SmtpSettings
{
    public const KEYS = [
        'mail_from_name',
        'mail_from_address',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_last_test_status',
        'mail_last_test_at',
        'mail_last_test_message',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'mail_from_name' => (string) Setting::get('mail_from_name', config('mail.from.name', 'Libromart')),
            'mail_from_address' => (string) Setting::get('mail_from_address', config('mail.from.address', '')),
            'mail_host' => (string) Setting::get('mail_host', config('mail.mailers.smtp.host', '')),
            'mail_port' => (string) Setting::get('mail_port', (string) config('mail.mailers.smtp.port', '587')),
            'mail_encryption' => (string) Setting::get('mail_encryption', 'tls'),
            'mail_username' => (string) Setting::get('mail_username', ''),
            'mail_password_set' => (string) Setting::get('mail_password', '') !== '',
            'mail_last_test_status' => (string) Setting::get('mail_last_test_status', ''),
            'mail_last_test_at' => Setting::get('mail_last_test_at'),
            'mail_last_test_message' => Setting::get('mail_last_test_message'),
        ];
    }

    public static function isConfigured(): bool
    {
        $host = (string) Setting::get('mail_host', '');
        $from = (string) Setting::get('mail_from_address', '');

        return $host !== '' && $from !== '';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function save(array $input): void
    {
        Setting::set('mail_from_name', (string) ($input['mail_from_name'] ?? ''), 'Nom expéditeur SMTP');
        Setting::set('mail_from_address', (string) ($input['mail_from_address'] ?? ''), 'Adresse e-mail professionnelle');
        Setting::set('mail_host', (string) ($input['mail_host'] ?? ''), 'Serveur SMTP');
        Setting::set('mail_port', (string) ($input['mail_port'] ?? '587'), 'Port SMTP');
        Setting::set('mail_encryption', (string) ($input['mail_encryption'] ?? 'tls'), 'Chiffrement SMTP');
        Setting::set('mail_username', (string) ($input['mail_username'] ?? ''), 'Identifiant SMTP');

        if (array_key_exists('mail_password', $input) && (string) $input['mail_password'] !== '') {
            Setting::set(
                'mail_password',
                Crypt::encryptString((string) $input['mail_password']),
                'Mot de passe SMTP (chiffré)'
            );
        }
    }

    public static function decryptedPassword(): ?string
    {
        $raw = Setting::get('mail_password');
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Crypt::decryptString((string) $raw);
        } catch (Throwable) {
            // Legacy plain value — migrate on next save.
            return (string) $raw;
        }
    }

    public static function applyToConfig(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        $encryption = Setting::get('mail_encryption', 'tls');
        $scheme = in_array($encryption, ['ssl', 'tls'], true) ? $encryption : null;

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => Setting::get('mail_host'),
            'mail.mailers.smtp.port' => (int) Setting::get('mail_port', 587),
            'mail.mailers.smtp.username' => Setting::get('mail_username') ?: null,
            'mail.mailers.smtp.password' => self::decryptedPassword(),
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.encryption' => $scheme,
            'mail.from.address' => Setting::get('mail_from_address'),
            'mail.from.name' => Setting::get('mail_from_name', 'Libromart'),
        ]);
    }

    public static function recordTestResult(bool $ok, string $message): void
    {
        Setting::set('mail_last_test_status', $ok ? 'connected' : 'error', 'Dernier test SMTP');
        Setting::set('mail_last_test_at', now()->toDateTimeString());
        Setting::set('mail_last_test_message', mb_substr($message, 0, 500));
    }
}
