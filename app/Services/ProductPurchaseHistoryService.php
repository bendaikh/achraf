<?php

namespace App\Services;

use App\Models\Reception;
use App\Models\SupplierDeliveryNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPurchaseOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductPurchaseHistoryService
{
    /**
     * Purchase document types that feed product purchase history
     * (same sources as last purchase price sync, excluding credit notes).
     *
     * @return list<class-string>
     */
    public function purchaseableTypes(): array
    {
        return [
            SupplierInvoice::class,
            SupplierDeliveryNote::class,
            Reception::class,
            SupplierPurchaseOrder::class,
        ];
    }

    /**
     * Last supplier per product, derived from the most recent purchase document line.
     *
     * @param  list<int|string>  $productIds
     * @return array<int, array{id: int, name: string}>
     */
    public function lastSuppliersForProducts(iterable $productIds): array
    {
        $ids = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $rows = $this->purchaseRowsQuery($ids)
            ->orderByDesc('doc_date')
            ->orderByDesc('item_id')
            ->get();

        $last = [];
        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            if (isset($last[$productId])) {
                continue;
            }
            if (! $row->supplier_id || ! $row->supplier_name) {
                continue;
            }
            $last[$productId] = [
                'id' => (int) $row->supplier_id,
                'name' => (string) $row->supplier_name,
            ];
        }

        return $last;
    }

    /**
     * Full purchase / supplier history for one product (newest first).
     *
     * @return list<array{
     *     date: string|null,
     *     date_formatted: string,
     *     supplier_id: int|null,
     *     supplier_name: string,
     *     quantity: int,
     *     unit_price: float,
     *     unit_price_formatted: string,
     *     document_number: string,
     *     document_url: string|null,
     *     document_type: string,
     *     document_type_label: string
     * }>
     */
    public function historyForProduct(int $productId): array
    {
        $rows = $this->purchaseRowsQuery([$productId])
            ->orderByDesc('doc_date')
            ->orderByDesc('item_id')
            ->get();

        return $rows->map(function ($row) {
            $meta = $this->documentMeta(
                (string) $row->purchaseable_type,
                (int) $row->purchaseable_id,
                (string) ($row->document_number ?? '')
            );

            $date = $row->doc_date ? substr((string) $row->doc_date, 0, 10) : null;

            return [
                'date' => $date,
                'date_formatted' => $date ? date('d/m/Y', strtotime($date)) : '—',
                'supplier_id' => $row->supplier_id ? (int) $row->supplier_id : null,
                'supplier_name' => (string) ($row->supplier_name ?: '—'),
                'quantity' => (int) $row->quantity,
                'unit_price' => round((float) $row->unit_price, 2),
                'unit_price_formatted' => number_format((float) $row->unit_price, 2, ',', ' ').' DH',
                'document_number' => $meta['number'],
                'document_url' => $meta['url'],
                'document_type' => $meta['type'],
                'document_type_label' => $meta['label'],
            ];
        })->values()->all();
    }

    /**
     * Attach last-purchase supplier info onto a collection of products.
     *
     * @param  Collection<int, \App\Models\Product>  $products
     */
    public function attachLastSuppliers(Collection $products): void
    {
        $map = $this->lastSuppliersForProducts($products->pluck('id'));

        foreach ($products as $product) {
            $info = $map[(int) $product->id] ?? null;
            $product->setAttribute('last_purchase_supplier_id', $info['id'] ?? null);
            $product->setAttribute('last_purchase_supplier_name', $info['name'] ?? null);
        }
    }

    /**
     * Constraint: product appears on at least one purchase document for this supplier.
     */
    public function constrainProductsBoughtFromSupplier($query, int $supplierId): void
    {
        $types = $this->purchaseableTypes();

        $query->whereExists(function ($sub) use ($supplierId, $types) {
            $sub->select(DB::raw(1))
                ->from('purchase_items as pi')
                ->whereColumn('pi.product_id', 'products.id')
                ->whereIn('pi.purchaseable_type', $types)
                ->where(function ($docs) use ($supplierId) {
                    $docs->whereExists(function ($q) use ($supplierId) {
                        $q->select(DB::raw(1))
                            ->from('supplier_invoices as si')
                            ->whereColumn('si.id', 'pi.purchaseable_id')
                            ->where('pi.purchaseable_type', SupplierInvoice::class)
                            ->where('si.supplier_id', $supplierId);
                    })->orWhereExists(function ($q) use ($supplierId) {
                        $q->select(DB::raw(1))
                            ->from('supplier_delivery_notes as sdn')
                            ->whereColumn('sdn.id', 'pi.purchaseable_id')
                            ->where('pi.purchaseable_type', SupplierDeliveryNote::class)
                            ->where('sdn.supplier_id', $supplierId);
                    })->orWhereExists(function ($q) use ($supplierId) {
                        $q->select(DB::raw(1))
                            ->from('receptions as r')
                            ->whereColumn('r.id', 'pi.purchaseable_id')
                            ->where('pi.purchaseable_type', Reception::class)
                            ->where('r.supplier_id', $supplierId);
                    })->orWhereExists(function ($q) use ($supplierId) {
                        $q->select(DB::raw(1))
                            ->from('supplier_purchase_orders as spo')
                            ->whereColumn('spo.id', 'pi.purchaseable_id')
                            ->where('pi.purchaseable_type', SupplierPurchaseOrder::class)
                            ->where('spo.supplier_id', $supplierId);
                    });
                });
        });
    }

    /**
     * Unified purchase rows for the given products.
     *
     * @param  list<int>  $productIds
     */
    protected function purchaseRowsQuery(array $productIds)
    {
        $invoice = DB::table('purchase_items as pi')
            ->join('supplier_invoices as d', function ($join) {
                $join->on('d.id', '=', 'pi.purchaseable_id')
                    ->where('pi.purchaseable_type', '=', SupplierInvoice::class);
            })
            ->leftJoin('suppliers as s', 's.id', '=', 'd.supplier_id')
            ->whereIn('pi.product_id', $productIds)
            ->select([
                'pi.id as item_id',
                'pi.product_id',
                'pi.quantity',
                'pi.unit_price',
                'pi.purchaseable_type',
                'pi.purchaseable_id',
                'd.invoice_date as doc_date',
                'd.invoice_number as document_number',
                's.id as supplier_id',
                's.name as supplier_name',
            ]);

        $delivery = DB::table('purchase_items as pi')
            ->join('supplier_delivery_notes as d', function ($join) {
                $join->on('d.id', '=', 'pi.purchaseable_id')
                    ->where('pi.purchaseable_type', '=', SupplierDeliveryNote::class);
            })
            ->leftJoin('suppliers as s', 's.id', '=', 'd.supplier_id')
            ->whereIn('pi.product_id', $productIds)
            ->select([
                'pi.id as item_id',
                'pi.product_id',
                'pi.quantity',
                'pi.unit_price',
                'pi.purchaseable_type',
                'pi.purchaseable_id',
                'd.delivery_date as doc_date',
                'd.delivery_number as document_number',
                's.id as supplier_id',
                's.name as supplier_name',
            ]);

        $reception = DB::table('purchase_items as pi')
            ->join('receptions as d', function ($join) {
                $join->on('d.id', '=', 'pi.purchaseable_id')
                    ->where('pi.purchaseable_type', '=', Reception::class);
            })
            ->leftJoin('suppliers as s', 's.id', '=', 'd.supplier_id')
            ->whereIn('pi.product_id', $productIds)
            ->select([
                'pi.id as item_id',
                'pi.product_id',
                'pi.quantity',
                'pi.unit_price',
                'pi.purchaseable_type',
                'pi.purchaseable_id',
                'd.reception_date as doc_date',
                'd.reception_number as document_number',
                's.id as supplier_id',
                's.name as supplier_name',
            ]);

        $order = DB::table('purchase_items as pi')
            ->join('supplier_purchase_orders as d', function ($join) {
                $join->on('d.id', '=', 'pi.purchaseable_id')
                    ->where('pi.purchaseable_type', '=', SupplierPurchaseOrder::class);
            })
            ->leftJoin('suppliers as s', 's.id', '=', 'd.supplier_id')
            ->whereIn('pi.product_id', $productIds)
            ->select([
                'pi.id as item_id',
                'pi.product_id',
                'pi.quantity',
                'pi.unit_price',
                'pi.purchaseable_type',
                'pi.purchaseable_id',
                'd.order_date as doc_date',
                'd.order_number as document_number',
                's.id as supplier_id',
                's.name as supplier_name',
            ]);

        return $invoice
            ->unionAll($delivery)
            ->unionAll($reception)
            ->unionAll($order);
    }

    /**
     * @return array{number: string, url: string|null, type: string, label: string}
     */
    protected function documentMeta(string $type, int $id, string $number): array
    {
        return match ($type) {
            SupplierInvoice::class => [
                'number' => $number !== '' ? $number : 'FSI-'.$id,
                'url' => route('supplier-invoices.show', $id),
                'type' => 'invoice',
                'label' => 'Facture fournisseur',
            ],
            SupplierDeliveryNote::class => [
                'number' => $number !== '' ? $number : 'BL-'.$id,
                'url' => route('supplier-delivery-notes.show', $id),
                'type' => 'delivery_note',
                'label' => 'BL fournisseur',
            ],
            Reception::class => [
                'number' => $number !== '' ? $number : 'BR-'.$id,
                'url' => route('receptions.show', $id),
                'type' => 'reception',
                'label' => 'Bon de réception',
            ],
            SupplierPurchaseOrder::class => [
                'number' => $number !== '' ? $number : 'BC-'.$id,
                'url' => route('supplier-purchase-orders.show', $id),
                'type' => 'purchase_order',
                'label' => 'BC fournisseur',
            ],
            default => [
                'number' => $number !== '' ? $number : '#'.$id,
                'url' => null,
                'type' => 'unknown',
                'label' => 'Document',
            ],
        };
    }
}
