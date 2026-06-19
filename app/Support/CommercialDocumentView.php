<?php

namespace App\Support;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Client;
use App\Support\LineItemCalculator;
use Illuminate\Support\Collection;

class CommercialDocumentView
{
    /**
     * @return array<string, mixed>
     */
    public static function forInvoice(Invoice $invoice, array $taxes): array
    {
        $invoice->loadMissing('client', 'items');
        $client = $invoice->client;

        return self::base(
            title: 'FACTURE',
            number: $invoice->invoice_number,
            dates: array_filter([
                ['label' => 'DATE', 'value' => $invoice->invoice_date->format('d/m/Y')],
                $invoice->due_date ? ['label' => 'ÉCHÉANCE', 'value' => $invoice->due_date->format('d/m/Y')] : null,
            ]),
            partyTab: 'Informations client',
            partyName: $client->name,
            partyLines: self::clientLines($client),
            partyLegal: self::clientLegal($client),
            items: $invoice->items,
            taxes: $taxes,
            currency: $invoice->currency,
            remarks: $invoice->remarks,
            priceMode: LineItemCalculator::priceModeForDocument($invoice),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function forQuote(Quote $quote, array $taxes): array
    {
        $quote->loadMissing('client', 'items');

        return self::base(
            title: 'DEVIS',
            number: $quote->quote_number,
            dates: array_filter([
                ['label' => 'DATE', 'value' => $quote->quote_date->format('d/m/Y')],
                $quote->expiry_date ? ['label' => 'VALIDITÉ', 'value' => $quote->expiry_date->format('d/m/Y')] : null,
            ]),
            partyTab: 'Informations client',
            partyName: $quote->client->name,
            partyLines: array_merge(self::clientLines($quote->client), [
                ['label' => 'DEVISE', 'value' => $quote->currency],
                ['label' => 'STOCK', 'value' => $quote->stock_location],
            ]),
            partyLegal: self::clientLegal($quote->client),
            items: $quote->items,
            taxes: $taxes,
            currency: $quote->currency,
            remarks: trim(collect([$quote->remarks, $quote->conditions])->filter()->implode("\n\n")) ?: null,
            priceMode: LineItemCalculator::priceModeForDocument($quote),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function forPurchaseOrder(PurchaseOrder $order, array $taxes): array
    {
        $order->loadMissing('client', 'items');

        return self::base(
            title: 'BON DE COMMANDE',
            number: $order->reference,
            dates: array_filter([
                ['label' => 'DATE', 'value' => $order->order_date->format('d/m/Y')],
                $order->expiry_date ? ['label' => 'ÉCHÉANCE', 'value' => $order->expiry_date->format('d/m/Y')] : null,
            ]),
            partyTab: 'Informations client',
            partyName: $order->client->name,
            partyLines: array_merge(self::clientLines($order->client), [
                ['label' => 'DEVISE', 'value' => $order->currency ?? 'MAD'],
            ]),
            partyLegal: self::clientLegal($order->client),
            items: $order->items,
            taxes: $taxes,
            currency: $order->currency ?? 'dh - MAD',
            remarks: trim(collect([$order->remarks, $order->conditions])->filter()->implode("\n\n")) ?: null,
            priceMode: LineItemCalculator::priceModeForDocument($order),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function forCreditNote(CreditNote $creditNote, array $taxes): array
    {
        $creditNote->loadMissing('client', 'invoice', 'items');

        $lines = self::clientLines($creditNote->client);
        $lines[] = ['label' => 'STOCK', 'value' => $creditNote->stock_location];
        if ($creditNote->invoice) {
            $lines[] = ['label' => 'FACTURE LIÉE', 'value' => $creditNote->invoice->invoice_number];
        }

        return self::base(
            title: 'AVOIR',
            number: $creditNote->credit_note_number,
            dates: [
                ['label' => 'DATE', 'value' => $creditNote->credit_note_date->format('d/m/Y')],
            ],
            partyTab: 'Informations client',
            partyName: $creditNote->client->name,
            partyLines: $lines,
            partyLegal: self::clientLegal($creditNote->client),
            items: $creditNote->items,
            taxes: $taxes,
            currency: $creditNote->currency,
            remarks: trim(collect([$creditNote->remarks, $creditNote->conditions])->filter()->implode("\n\n")) ?: null,
            priceMode: LineItemCalculator::priceModeForDocument($creditNote),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function forSupplierInvoice(SupplierInvoice $invoice, array $taxes): array
    {
        $invoice->loadMissing('supplier', 'items');

        $lines = self::supplierLines($invoice->supplier);
        $lines[] = ['label' => 'DEVISE', 'value' => $invoice->currency];
        $lines[] = ['label' => 'STOCK', 'value' => $invoice->stock_location];
        if ($invoice->commercial_contact) {
            $lines[] = ['label' => 'CONTACT', 'value' => $invoice->commercial_contact];
        }

        return self::base(
            title: 'FACTURE FOURNISSEUR',
            number: $invoice->invoice_number,
            dates: array_filter([
                ['label' => 'DATE', 'value' => $invoice->invoice_date->format('d/m/Y')],
                $invoice->due_date ? ['label' => 'ÉCHÉANCE', 'value' => $invoice->due_date->format('d/m/Y')] : null,
            ]),
            partyTab: 'Informations fournisseur',
            partyName: $invoice->supplier->name,
            partyLines: $lines,
            partyLegal: self::supplierLegal($invoice->supplier),
            items: $invoice->items,
            taxes: $taxes,
            currency: $invoice->currency,
            remarks: trim(collect([$invoice->remarks, $invoice->conditions])->filter()->implode("\n\n")) ?: null,
            priceMode: LineItemCalculator::priceModeForDocument($invoice),
        );
    }

    /**
     * @param  list<array{label: string, value: string}>  $dates
     * @param  list<array{label: string, value: string}>  $partyLines
     * @param  array<string, string|null>  $partyLegal
     * @return array<string, mixed>
     */
    protected static function base(
        string $title,
        string $number,
        array $dates,
        string $partyTab,
        string $partyName,
        array $partyLines,
        array $partyLegal,
        Collection $items,
        array $taxes,
        string $currency,
        ?string $remarks,
        string $priceMode = 'sale',
    ): array {
        $currencyLabel = str_contains(strtolower($currency), 'mad') ? 'MAD' : $currency;

        return [
            'doc' => [
                'title' => $title,
                'number' => $number,
                'dates' => array_values($dates),
                'party_tab' => $partyTab,
                'party_name' => $partyName,
                'party_lines' => $partyLines,
                'party_legal' => array_filter($partyLegal),
                'items' => $items,
                'taxes' => $taxes,
                'currency_label' => $currencyLabel,
                'remarks' => $remarks,
                'show_amount_in_words' => true,
                'price_mode' => $priceMode,
            ],
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    protected static function clientLines(Client $client): array
    {
        $city = $client->ville ?? $client->city ?? null;
        $address = collect([
            $client->address,
            trim(collect([$client->postal_code, $city])->filter()->implode(' ')),
            $client->region,
            $client->country,
        ])->filter()->implode(', ');

        $lines = [];
        if ($address) {
            $lines[] = ['label' => 'ADRESSE', 'value' => strtoupper($address)];
        }
        if ($client->phone) {
            $lines[] = ['label' => 'Tél', 'value' => $client->phone];
        }
        if ($client->email) {
            $lines[] = ['label' => 'Email', 'value' => strtoupper($client->email)];
        }

        return $lines;
    }

    /**
     * @return array<string, string|null>
     */
    protected static function clientLegal(Client $client): array
    {
        if (($client->client_type ?? 'entreprise') !== 'entreprise') {
            return [];
        }

        return [
            'ICE' => $client->ice,
            'IF' => $client->fiscal_identifier,
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    protected static function supplierLines(Supplier $supplier): array
    {
        $city = $supplier->ville ?? $supplier->city ?? null;
        $address = collect([
            $supplier->address,
            trim(collect([$supplier->postal_code, $city])->filter()->implode(' ')),
            $supplier->region,
            $supplier->country,
        ])->filter()->implode(', ');

        $lines = [];
        if ($address) {
            $lines[] = ['label' => 'ADRESSE', 'value' => strtoupper($address)];
        }
        if ($supplier->phone) {
            $lines[] = ['label' => 'Tél', 'value' => $supplier->phone];
        }
        if ($supplier->email) {
            $lines[] = ['label' => 'Email', 'value' => strtoupper($supplier->email)];
        }

        return $lines;
    }

    /**
     * @return array<string, string|null>
     */
    protected static function supplierLegal(Supplier $supplier): array
    {
        return array_filter([
            'ICE' => $supplier->ice,
            'IF' => $supplier->fiscal_identifier,
        ]);
    }
}
