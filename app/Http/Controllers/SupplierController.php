<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = Supplier::query()->orderBy('created_at', 'desc');

        $this->applyTableSearch($query, $request, [
            'name', 'email', 'phone', 'code', 'ice', 'ville', 'city', 'legal_name', 'trade_name', 'rc',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $suppliers = $this->paginateTable($query, $request);

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create', [
            'supplierCode' => $this->generateSupplierCode(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedSupplierPayload($request);
        $validated['code'] = $this->generateSupplierCode();
        $validated = $this->storeSupplierDocuments($request, $validated);

        Supplier::create($validated);

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur créé avec succès.');
    }

    public function quickStore(Request $request)
    {
        $validated = $this->validatedSupplierPayload($request, quick: true);
        $validated['code'] = $this->generateSupplierCode();
        $validated['status'] = $validated['status'] ?? 'actif';
        $validated['currency'] = $validated['currency'] ?? 'MAD';
        $validated['country'] = $validated['country'] ?? 'Maroc';

        $supplier = Supplier::create($validated);

        return response()->json([
            'id' => $supplier->id,
            'text' => $supplier->selectLabel(),
        ]);
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;

        $query = Supplier::query()->orderBy('name');

        if ($term !== '') {
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('ice', 'like', "%{$term}%")
                    ->orWhere('legal_name', 'like', "%{$term}%")
                    ->orWhere('trade_name', 'like', "%{$term}%");
            });
        }

        $paginator = $query->paginate($perPage, ['id', 'name', 'email'], 'page', $page);

        return response()->json([
            'results' => $paginator->getCollection()->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'text' => $supplier->selectLabel(),
            ])->values(),
            'pagination' => [
                'more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function show(Supplier $supplier)
    {
        $supplier->load('internalOwner');

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', [
            'supplier' => $supplier,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $this->validatedSupplierPayload($request, supplier: $supplier);
        unset($validated['code']);
        $validated = $this->storeSupplierDocuments($request, $validated, $supplier);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur mis à jour avec succès.');
    }

    public function destroy(Supplier $supplier)
    {
        foreach (array_keys(Supplier::DOCUMENT_FIELDS) as $field) {
            if ($supplier->{$field}) {
                Storage::disk('public')->delete($supplier->{$field});
            }
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur supprimé avec succès.');
    }

    private function generateSupplierCode(): string
    {
        $prefix = 'FRN';
        $year = date('Y');

        $lastSupplier = Supplier::where('code', 'like', $prefix . $year . '%')
            ->orderBy('code', 'desc')
            ->first();

        if ($lastSupplier && preg_match('/' . $prefix . $year . '(\d+)/', $lastSupplier->code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . $year . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function validatedSupplierPayload(Request $request, bool $quick = false, ?Supplier $supplier = null): array
    {
        $emailRule = Rule::unique('suppliers', 'email')->ignore($supplier?->id);

        $rules = [
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', 'max:255', $emailRule],
            'phone' => 'nullable|string|max:255',
            'whatsapp' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'ice' => 'nullable|string|max:255',
            'fiscal_identifier' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'ville' => 'nullable|string|max:255',
            'rc' => 'nullable|string|max:255',
            'rc_city' => 'nullable|string|max:255',
            'tp' => 'nullable|string|max:255',
            'legal_form' => ['nullable', 'string', Rule::in(array_keys(Supplier::LEGAL_FORMS))],
            'company_created_at' => 'nullable|date',
            'contact_name' => 'nullable|string|max:255',
            'contact_role' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:255',
            'contact_mobile' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_holder' => 'nullable|string|max:255',
            'rib' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'swift_bic' => 'nullable|string|max:255',
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(Supplier::PAYMENT_METHODS))],
            'payment_terms' => ['nullable', 'string', Rule::in(array_keys(Supplier::PAYMENT_TERMS))],
            'currency' => 'nullable|string|max:10',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'min_order_amount' => 'nullable|numeric|min:0',
            'delivery_lead_days' => 'nullable|integer|min:0',
            'status' => ['nullable', 'string', Rule::in(array_keys(Supplier::STATUSES))],
            'category' => 'nullable|string|max:255',
            'internal_owner_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ];

        if (! $quick) {
            foreach (array_keys(Supplier::DOCUMENT_FIELDS) as $field) {
                $rules[$field] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
            }
        }

        $validated = $request->validate($rules);

        if (empty($validated['status'])) {
            $validated['status'] = 'actif';
        }

        if (empty($validated['currency'])) {
            $validated['currency'] = 'MAD';
        }

        if (empty($validated['country'])) {
            $validated['country'] = 'Maroc';
        }

        // Sync legacy city column with ville when provided
        if (! empty($validated['ville']) && empty($validated['city'])) {
            $validated['city'] = $validated['ville'];
        }

        // Strip file fields — handled separately
        foreach (array_keys(Supplier::DOCUMENT_FIELDS) as $field) {
            unset($validated[$field]);
        }

        return $validated;
    }

    private function storeSupplierDocuments(Request $request, array $validated, ?Supplier $supplier = null): array
    {
        foreach (array_keys(Supplier::DOCUMENT_FIELDS) as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            if ($supplier && $supplier->{$field}) {
                Storage::disk('public')->delete($supplier->{$field});
            }

            $validated[$field] = $request->file($field)->store('suppliers/documents', 'public');
        }

        return $validated;
    }
}
