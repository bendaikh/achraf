<?php

namespace App\Services\Jumia;

class JumiaStatusMapper
{
    /**
     * Map Jumia order/item status to local pos_sales statuses.
     *
     * @return array{payment_status: string, fulfillment_status: string, status: string}
     */
    public function fromJumia(string $jumiaStatus): array
    {
        $status = strtolower(trim(str_replace('_', ' ', $jumiaStatus)));

        return match ($status) {
            'shipped' => [
                'payment_status' => 'paid',
                'fulfillment_status' => 'partial',
                'status' => 'completed',
            ],
            'delivered' => [
                'payment_status' => 'paid',
                'fulfillment_status' => 'fulfilled',
                'status' => 'completed',
            ],
            'canceled', 'cancelled' => [
                'payment_status' => 'voided',
                'fulfillment_status' => 'unfulfilled',
                'status' => 'cancelled',
            ],
            'failed', 'returned', 'returned to seller' => [
                'payment_status' => 'refunded',
                'fulfillment_status' => 'unfulfilled',
                'status' => 'cancelled',
            ],
            'pending', 'ready to ship' => [
                'payment_status' => 'paid',
                'fulfillment_status' => 'unfulfilled',
                'status' => 'completed',
            ],
            default => [
                'payment_status' => 'paid',
                'fulfillment_status' => 'unfulfilled',
                'status' => 'completed',
            ],
        };
    }

    /**
     * Map local fulfillment status to a Jumia SetStatus* action when possible.
     */
    public function toJumiaAction(string $fulfillmentStatus, string $orderStatus): ?string
    {
        if ($orderStatus === 'cancelled') {
            return 'SetStatusToCanceled';
        }

        return match ($fulfillmentStatus) {
            'unfulfilled' => 'SetStatusToReadyToShip',
            'partial' => 'SetStatusToShipped',
            'fulfilled' => 'SetStatusToDelivered',
            default => null,
        };
    }
}
