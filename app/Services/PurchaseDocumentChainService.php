<?php

namespace App\Services;

use App\Models\Reception;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use Illuminate\Support\Collection;

/**
 * Chaîne documentaire d’un achat : Facture ↳ BR ↳ BL ↳ BC.
 */
class PurchaseDocumentChainService
{
    /**
     * @return list<array{type:string, label:string, number:string, url:?string, stock_received:bool}>
     */
    public function forInvoice(SupplierInvoice $invoice): array
    {
        $chain = [[
            'type' => 'supplier_invoice',
            'label' => 'Facture fournisseur',
            'number' => (string) $invoice->invoice_number,
            'url' => route('supplier-invoices.show', $invoice),
            'stock_received' => (bool) $invoice->stock_applied_at,
        ]];

        $receptions = Reception::query()
            ->where('converted_supplier_invoice_id', $invoice->id)
            ->orderBy('reception_date')
            ->get();

        foreach ($receptions as $reception) {
            $chain[] = [
                'type' => 'reception',
                'label' => 'Bon de réception',
                'number' => (string) $reception->reception_number,
                'url' => route('receptions.show', $reception),
                'stock_received' => (bool) $reception->stock_applied_at,
            ];
        }

        $notes = SupplierDeliveryNote::query()
            ->where('converted_supplier_invoice_id', $invoice->id)
            ->orderBy('delivery_date')
            ->get();

        foreach ($notes as $note) {
            $chain[] = [
                'type' => 'supplier_delivery_note',
                'label' => 'Bon de livraison',
                'number' => (string) $note->delivery_number,
                'url' => route('supplier-delivery-notes.show', $note),
                'stock_received' => (bool) $note->stock_applied_at,
            ];
        }

        $poIds = collect([$invoice->supplier_purchase_order_id])
            ->merge($receptions->pluck('supplier_purchase_order_id'))
            ->merge($notes->pluck('supplier_purchase_order_id'))
            ->filter()
            ->unique()
            ->values();

        if ($poIds->isNotEmpty()) {
            $orders = SupplierPurchaseOrder::query()->whereIn('id', $poIds)->orderBy('order_date')->get();
            foreach ($orders as $order) {
                $chain[] = [
                    'type' => 'supplier_purchase_order',
                    'label' => 'Bon de commande',
                    'number' => (string) $order->order_number,
                    'url' => route('supplier-purchase-orders.show', $order),
                    'stock_received' => false,
                ];
            }
        }

        return $chain;
    }

    /**
     * @return list<array{type:string, label:string, number:string, url:?string, stock_received:bool}>
     */
    public function forReception(Reception $reception): array
    {
        $chain = [[
            'type' => 'reception',
            'label' => 'Bon de réception',
            'number' => (string) $reception->reception_number,
            'url' => route('receptions.show', $reception),
            'stock_received' => (bool) $reception->stock_applied_at,
        ]];

        if ($reception->converted_supplier_invoice_id) {
            $invoice = SupplierInvoice::find($reception->converted_supplier_invoice_id);
            if ($invoice) {
                array_unshift($chain, [
                    'type' => 'supplier_invoice',
                    'label' => 'Facture fournisseur',
                    'number' => (string) $invoice->invoice_number,
                    'url' => route('supplier-invoices.show', $invoice),
                    'stock_received' => (bool) $invoice->stock_applied_at,
                ]);
            }
        }

        if ($reception->supplier_purchase_order_id) {
            $order = SupplierPurchaseOrder::find($reception->supplier_purchase_order_id);
            if ($order) {
                $chain[] = [
                    'type' => 'supplier_purchase_order',
                    'label' => 'Bon de commande',
                    'number' => (string) $order->order_number,
                    'url' => route('supplier-purchase-orders.show', $order),
                    'stock_received' => false,
                ];
            }
        }

        return $chain;
    }
}
