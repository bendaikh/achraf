<?php

namespace App\Services;

use App\Models\Reception;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use Illuminate\Database\Eloquent\Model;

/**
 * Chaîne documentaire d’un achat : Facture ↳ BR ↳ BL ↳ BC.
 */
class PurchaseDocumentChainService
{
    /**
     * @return list<array{type:string, label:string, number:string, url:?string, stock_received:bool, reception_status:?string}>
     */
    public function forDocument(Model $document): array
    {
        return match (true) {
            $document instanceof SupplierInvoice => $this->forInvoice($document),
            $document instanceof Reception => $this->forReception($document),
            $document instanceof SupplierDeliveryNote => $this->forDeliveryNote($document),
            $document instanceof SupplierPurchaseOrder => $this->forPurchaseOrder($document),
            default => [],
        };
    }

    /**
     * @return list<array{type:string, label:string, number:string, url:?string, stock_received:bool, reception_status:?string}>
     */
    public function forInvoice(SupplierInvoice $invoice): array
    {
        $receipts = app(PurchaseReceiptService::class);

        $chain = [[
            'type' => 'supplier_invoice',
            'label' => 'Facture fournisseur',
            'number' => (string) $invoice->invoice_number,
            'url' => route('supplier-invoices.show', $invoice),
            'stock_received' => (bool) $invoice->stock_applied_at,
            'reception_status' => $receipts->documentReceptionStatusLabel($invoice),
        ]];

        foreach ($this->receptionsForFamily($invoice) as $reception) {
            $chain[] = $this->receptionNode($reception);
        }

        foreach ($this->deliveryNotesForFamily($invoice) as $note) {
            $chain[] = $this->deliveryNoteNode($note);
        }

        foreach ($this->purchaseOrdersForFamily($invoice) as $order) {
            $chain[] = $this->purchaseOrderNode($order);
        }

        return $chain;
    }

    /**
     * @return list<array{type:string, label:string, number:string, url:?string, stock_received:bool, reception_status:?string}>
     */
    public function forReception(Reception $reception): array
    {
        $chain = [$this->receptionNode($reception)];

        if ($reception->converted_supplier_invoice_id) {
            $invoice = SupplierInvoice::find($reception->converted_supplier_invoice_id);
            if ($invoice) {
                array_unshift($chain, $this->invoiceNode($invoice));
            }
        } elseif ($reception->source_supplier_invoice_id) {
            $invoice = SupplierInvoice::find($reception->source_supplier_invoice_id);
            if ($invoice) {
                array_unshift($chain, $this->invoiceNode($invoice));
            }
        }

        if ($reception->converted_supplier_delivery_note_id) {
            $note = SupplierDeliveryNote::find($reception->converted_supplier_delivery_note_id);
            if ($note) {
                $chain[] = $this->deliveryNoteNode($note);
            }
        }

        if ($reception->supplier_delivery_note_id
            && (int) $reception->supplier_delivery_note_id !== (int) $reception->converted_supplier_delivery_note_id) {
            $note = SupplierDeliveryNote::find($reception->supplier_delivery_note_id);
            if ($note) {
                $chain[] = $this->deliveryNoteNode($note);
            }
        }

        if ($reception->supplier_purchase_order_id) {
            $order = SupplierPurchaseOrder::find($reception->supplier_purchase_order_id);
            if ($order) {
                $chain[] = $this->purchaseOrderNode($order);
            }
        }

        return $chain;
    }

    /**
     * @return list<array{type:string, label:string, number:string, url:?string, stock_received:bool, reception_status:?string}>
     */
    public function forDeliveryNote(SupplierDeliveryNote $note): array
    {
        $receipts = app(PurchaseReceiptService::class);

        $chain = [$this->deliveryNoteNode($note)];

        foreach ($this->receptionsForFamily($note) as $reception) {
            if ((int) $reception->id !== (int) ($note->receptions()->where('id', $reception->id)->value('id') ?? $reception->id)) {
                // always include family receptions
            }
            if (! collect($chain)->contains(fn ($n) => $n['type'] === 'reception' && $n['number'] === $reception->reception_number)) {
                $chain[] = $this->receptionNode($reception);
            }
        }

        if ($note->converted_supplier_invoice_id) {
            $invoice = SupplierInvoice::find($note->converted_supplier_invoice_id);
            if ($invoice) {
                array_unshift($chain, $this->invoiceNode($invoice));
            }
        }

        if ($note->supplier_purchase_order_id) {
            $order = SupplierPurchaseOrder::find($note->supplier_purchase_order_id);
            if ($order) {
                $chain[] = $this->purchaseOrderNode($order);
            }
        }

        return $chain;
    }

    /**
     * @return list<array{type:string, label:string, number:string, url:?string, stock_received:bool, reception_status:?string}>
     */
    public function forPurchaseOrder(SupplierPurchaseOrder $order): array
    {
        $receipts = app(PurchaseReceiptService::class);

        $chain = [[
            'type' => 'supplier_purchase_order',
            'label' => 'Bon de commande',
            'number' => (string) $order->order_number,
            'url' => route('supplier-purchase-orders.show', $order),
            'stock_received' => $receipts->documentReceptionStatus($order) === PurchaseReceiptService::STATUS_COMPLETE,
            'reception_status' => $receipts->documentReceptionStatusLabel($order),
        ]];

        foreach ($this->receptionsForFamily($order) as $reception) {
            $chain[] = $this->receptionNode($reception);
        }

        foreach ($this->deliveryNotesForFamily($order) as $note) {
            $chain[] = $this->deliveryNoteNode($note);
        }

        foreach ($this->supplierInvoicesForFamily($order) as $invoice) {
            $chain[] = $this->invoiceNode($invoice);
        }

        return $chain;
    }

    /**
     * @return list<array{type:string, label:string, number:string, url:?string, stock_received:bool, reception_status:?string}>
     */
    protected function receptionNode(Reception $reception): array
    {
        return [
            'type' => 'reception',
            'label' => 'Bon de réception',
            'number' => (string) $reception->reception_number,
            'url' => route('receptions.show', $reception),
            'stock_received' => (bool) $reception->stock_applied_at,
            'reception_status' => $reception->stock_applied_at ? 'Réceptionné' : 'En attente',
        ];
    }

    /**
     * @return array{type:string, label:string, number:string, url:?string, stock_received:bool, reception_status:?string}
     */
    protected function deliveryNoteNode(SupplierDeliveryNote $note): array
    {
        $receipts = app(PurchaseReceiptService::class);

        return [
            'type' => 'supplier_delivery_note',
            'label' => 'Bon de livraison',
            'number' => (string) $note->delivery_number,
            'url' => route('supplier-delivery-notes.show', $note),
            'stock_received' => $receipts->documentReceptionStatus($note) === PurchaseReceiptService::STATUS_COMPLETE,
            'reception_status' => $receipts->documentReceptionStatusLabel($note),
        ];
    }

    /**
     * @return array{type:string, label:string, number:string, url:?string, stock_received:bool, reception_status:?string}
     */
    protected function invoiceNode(SupplierInvoice $invoice): array
    {
        $receipts = app(PurchaseReceiptService::class);

        return [
            'type' => 'supplier_invoice',
            'label' => 'Facture fournisseur',
            'number' => (string) $invoice->invoice_number,
            'url' => route('supplier-invoices.show', $invoice),
            'stock_received' => (bool) $invoice->stock_applied_at,
            'reception_status' => $receipts->documentReceptionStatusLabel($invoice),
        ];
    }

    /**
     * @return array{type:string, label:string, number:string, url:?string, stock_received:bool, reception_status:?string}
     */
    protected function purchaseOrderNode(SupplierPurchaseOrder $order): array
    {
        $receipts = app(PurchaseReceiptService::class);

        return [
            'type' => 'supplier_purchase_order',
            'label' => 'Bon de commande',
            'number' => (string) $order->order_number,
            'url' => route('supplier-purchase-orders.show', $order),
            'stock_received' => $receipts->documentReceptionStatus($order) === PurchaseReceiptService::STATUS_COMPLETE,
            'reception_status' => $receipts->documentReceptionStatusLabel($order),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Reception> */
    protected function receptionsForFamily(Model $document): \Illuminate\Support\Collection
    {
        return app(PurchaseReceiptService::class)->linkedReceptions($document);
    }

    /** @return \Illuminate\Support\Collection<int, SupplierDeliveryNote> */
    protected function deliveryNotesForFamily(Model $document): \Illuminate\Support\Collection
    {
        $family = app(PurchaseReceiptService::class)->collectFamilyIds($document);

        if ($family['delivery_note_ids']->isEmpty()) {
            return collect();
        }

        return SupplierDeliveryNote::query()
            ->whereIn('id', $family['delivery_note_ids'])
            ->orderBy('delivery_date')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, SupplierPurchaseOrder> */
    protected function purchaseOrdersForFamily(Model $document): \Illuminate\Support\Collection
    {
        $family = app(PurchaseReceiptService::class)->collectFamilyIds($document);

        if ($family['purchase_order_ids']->isEmpty()) {
            return collect();
        }

        return SupplierPurchaseOrder::query()
            ->whereIn('id', $family['purchase_order_ids'])
            ->orderBy('order_date')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, SupplierInvoice> */
    protected function supplierInvoicesForFamily(Model $document): \Illuminate\Support\Collection
    {
        $family = app(PurchaseReceiptService::class)->collectFamilyIds($document);

        if ($family['invoice_ids']->isEmpty()) {
            return collect();
        }

        return SupplierInvoice::query()
            ->whereIn('id', $family['invoice_ids'])
            ->orderBy('invoice_date')
            ->get();
    }
}
