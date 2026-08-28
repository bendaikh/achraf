<?php

namespace App\Services;

use App\Models\ClientRefund;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClientRefundService
{
    public function __construct(
        protected InvoiceSituationService $situation
    ) {}

    /**
     * @param  array{
     *   client_id: int,
     *   invoice_id?: int|null,
     *   credit_note_id?: int|null,
     *   pos_sale_id?: int|null,
     *   source?: string|null,
     *   refund_date: string,
     *   amount: float,
     *   payment_method: string,
     *   payment_reference?: string|null,
     *   notes?: string|null,
     *   external_id?: string|null,
     *   payment_file?: UploadedFile|null,
     * }  $data
     */
    public function record(array $data, ?User $actor = null): ClientRefund
    {
        return DB::transaction(function () use ($data, $actor) {
            if (! empty($data['external_id']) && ! empty($data['source'])) {
                $existing = ClientRefund::query()
                    ->where('source', $data['source'])
                    ->where('external_id', $data['external_id'])
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $invoice = isset($data['invoice_id'])
                ? Invoice::query()->find($data['invoice_id'])
                : null;

            $filePath = null;
            if (! empty($data['payment_file']) && $data['payment_file'] instanceof UploadedFile) {
                $filePath = $data['payment_file']->store('client-refunds', 'public');
            }

            $refund = ClientRefund::create([
                'refund_number' => DocumentNumberService::generate('remboursement'),
                'client_id' => $data['client_id'],
                'invoice_id' => $data['invoice_id'] ?? $invoice?->id,
                'credit_note_id' => $data['credit_note_id'] ?? null,
                'pos_sale_id' => $data['pos_sale_id'] ?? $invoice?->pos_sale_id,
                'source' => $data['source'] ?? ClientRefund::SOURCE_MANUAL,
                'refund_date' => $data['refund_date'],
                'amount' => round((float) $data['amount'], 2),
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_file_path' => $filePath,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->id,
                'external_id' => $data['external_id'] ?? null,
            ]);

            if ($invoice) {
                $invoice->recordActivity(
                    'refund_recorded',
                    'Remboursement client '.$refund->refund_number.' enregistré ('.number_format($refund->amount, 2).' '.$invoice->currency.')',
                    $actor?->id,
                    ['client_refund_id' => $refund->id, 'amount' => $refund->amount]
                );
            }

            return $refund;
        });
    }

    public function delete(ClientRefund $refund): void
    {
        DB::transaction(function () use ($refund) {
            if ($refund->payment_file_path) {
                Storage::disk('public')->delete($refund->payment_file_path);
            }

            $refund->delete();
        });
    }
}
