<?php

namespace App\Services;

use App\Models\EmailDispatchLog;
use App\Models\User;
use App\Support\SmtpSettings;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class DocumentMailService
{
    /**
     * Send a PDF (or any attachment) via the central SMTP configuration.
     * Failures never throw to the HTTP layer as 500 — callers get a result array.
     *
     * @param  array{to:string,subject:string,body?:string,attachment_path?:?string,attachment_name?:?string,document_type?:?string,document_id?:?int}  $payload
     * @return array{ok:bool,message:string,log_id?:int}
     */
    public function send(array $payload): array
    {
        try {
            SmtpSettings::applyToConfig();

            if (! SmtpSettings::isConfigured()) {
                throw new RuntimeException('SMTP non configuré. Renseignez Paramètres → E-mail / SMTP.');
            }

            $to = trim((string) ($payload['to'] ?? ''));
            if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Adresse destinataire invalide.');
            }

            $subject = (string) ($payload['subject'] ?? 'Document Libromart');
            $body = (string) ($payload['body'] ?? 'Veuillez trouver ci-joint votre document.');
            $path = $payload['attachment_path'] ?? null;
            $name = $payload['attachment_name'] ?? 'document.pdf';

            Mail::raw($body, function (Message $message) use ($to, $subject, $path, $name) {
                $message->to($to)->subject($subject);
                if ($path && is_string($path) && is_file($path)) {
                    $message->attach($path, ['as' => $name]);
                }
            });

            $log = $this->log($payload, $to, true, null);

            return ['ok' => true, 'message' => 'E-mail envoyé.', 'log_id' => $log->id];
        } catch (Throwable $e) {
            $log = $this->log($payload, (string) ($payload['to'] ?? ''), false, $e->getMessage());

            return [
                'ok' => false,
                'message' => 'Échec d’envoi : '.$e->getMessage(),
                'log_id' => $log->id,
            ];
        }
    }

    public function sendTest(string $to): array
    {
        return $this->send([
            'to' => $to,
            'subject' => 'Test SMTP Libromart',
            'body' => "Ceci est un e-mail de test depuis Libromart.\nSi vous le recevez, la configuration SMTP est opérationnelle.",
            'document_type' => 'smtp_test',
            'document_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function log(array $payload, string $to, bool $ok, ?string $error): EmailDispatchLog
    {
        return EmailDispatchLog::create([
            'document_type' => $payload['document_type'] ?? null,
            'document_id' => $payload['document_id'] ?? null,
            'recipient' => $to,
            'subject' => (string) ($payload['subject'] ?? ''),
            'status' => $ok ? 'success' : 'failed',
            'error_message' => $error,
            'user_id' => Auth::id(),
            'sent_at' => now(),
        ]);
    }
}
