<?php

namespace App\Services;

use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Model;

/**
 * Chaîne documentaire ventes : Devis ↳ BC ↳ BL ↳ Facture.
 */
class SalesDocumentChainService
{
    /**
     * @return list<array{type:string, label:string, number:string, url:?string}>
     */
    public function forDocument(Model $document): array
    {
        return match (true) {
            $document instanceof Quote => $this->forQuote($document),
            $document instanceof PurchaseOrder => $this->forPurchaseOrder($document),
            $document instanceof DeliveryNote => $this->forDeliveryNote($document),
            $document instanceof Invoice => $this->forInvoice($document),
            default => [],
        };
    }

    /**
     * @return list<array{type:string, label:string, number:string, url:?string}>
     */
    public function forQuote(Quote $quote): array
    {
        $chain = [$this->quoteNode($quote)];

        if ($quote->converted_purchase_order_id && $quote->convertedPurchaseOrder) {
            $chain[] = $this->purchaseOrderNode($quote->convertedPurchaseOrder);
        }

        if ($quote->converted_delivery_note_id && $quote->convertedDeliveryNote) {
            $chain[] = $this->deliveryNoteNode($quote->convertedDeliveryNote);
        }

        if ($quote->converted_invoice_id && $quote->convertedInvoice) {
            $chain[] = $this->invoiceNode($quote->convertedInvoice);
        }

        return $chain;
    }

    /**
     * @return list<array{type:string, label:string, number:string, url:?string}>
     */
    public function forPurchaseOrder(PurchaseOrder $order): array
    {
        $chain = [];

        foreach ($order->sourceQuotes as $quote) {
            $chain[] = $this->quoteNode($quote);
        }

        $chain[] = $this->purchaseOrderNode($order);

        if ($order->converted_delivery_note_id && $order->convertedDeliveryNote) {
            $chain[] = $this->deliveryNoteNode($order->convertedDeliveryNote);
        }

        if ($order->converted_invoice_id && $order->convertedInvoice) {
            $chain[] = $this->invoiceNode($order->convertedInvoice);
        }

        return $chain;
    }

    /**
     * @return list<array{type:string, label:string, number:string, url:?string}>
     */
    public function forDeliveryNote(DeliveryNote $note): array
    {
        $chain = [];

        foreach ($note->sourceQuotes as $quote) {
            $chain[] = $this->quoteNode($quote);
        }

        foreach ($note->sourcePurchaseOrders as $order) {
            $chain[] = $this->purchaseOrderNode($order);
        }

        $chain[] = $this->deliveryNoteNode($note);

        if ($note->converted_invoice_id && $note->convertedInvoice) {
            $chain[] = $this->invoiceNode($note->convertedInvoice);
        }

        return $chain;
    }

    /**
     * @return list<array{type:string, label:string, number:string, url:?string}>
     */
    public function forInvoice(Invoice $invoice): array
    {
        $chain = [];

        foreach ($invoice->sourceQuotes as $quote) {
            $chain[] = $this->quoteNode($quote);
        }

        foreach ($invoice->sourcePurchaseOrders as $order) {
            $chain[] = $this->purchaseOrderNode($order);
        }

        foreach ($invoice->sourceDeliveryNotes as $note) {
            $chain[] = $this->deliveryNoteNode($note);
        }

        $chain[] = $this->invoiceNode($invoice);

        return $chain;
    }

    /**
     * @return array{type:string, label:string, number:string, url:?string}
     */
    protected function quoteNode(Quote $quote): array
    {
        return [
            'type' => 'quote',
            'label' => 'Devis',
            'number' => (string) $quote->quote_number,
            'url' => route('quotes.show', $quote),
        ];
    }

    /**
     * @return array{type:string, label:string, number:string, url:?string}
     */
    protected function purchaseOrderNode(PurchaseOrder $order): array
    {
        return [
            'type' => 'purchase_order',
            'label' => 'Bon de commande',
            'number' => (string) $order->reference,
            'url' => route('purchase-orders.show', $order),
        ];
    }

    /**
     * @return array{type:string, label:string, number:string, url:?string}
     */
    protected function deliveryNoteNode(DeliveryNote $note): array
    {
        return [
            'type' => 'delivery_note',
            'label' => 'Bon de livraison',
            'number' => (string) $note->delivery_number,
            'url' => route('delivery-notes.show', $note),
        ];
    }

    /**
     * @return array{type:string, label:string, number:string, url:?string}
     */
    protected function invoiceNode(Invoice $invoice): array
    {
        return [
            'type' => 'invoice',
            'label' => 'Facture',
            'number' => (string) $invoice->invoice_number,
            'url' => route('invoices.show', $invoice),
        ];
    }
}
