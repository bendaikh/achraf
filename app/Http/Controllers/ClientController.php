<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = Client::query()->orderBy('created_at', 'desc');

        $this->applyTableSearch($query, $request, [
            'name', 'email', 'phone', 'code', 'ice', 'ville', 'city', 'first_name', 'last_name', 'cin',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $clients = $this->paginateTable($query, $request);

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        return view('clients.create', [
            'clientCode' => $this->generateClientCode(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedClientPayload($request);
        $validated['code'] = $this->generateClientCode();

        $client = Client::create($validated);
        $this->storeClientDocuments($request, $client);

        if ($request->boolean('save_and_another')) {
            return redirect()->route('clients.create')->with('success', 'Client créé avec succès. Vous pouvez en ajouter un autre.');
        }

        return redirect()->route('clients.show', $client)->with('success', 'Client créé avec succès.');
    }

    private function generateClientCode(): string
    {
        $prefix = 'CLT';
        $year = date('Y');

        $lastClient = Client::where('code', 'like', $prefix . $year . '%')
            ->orderBy('code', 'desc')
            ->first();

        if ($lastClient && preg_match('/' . $prefix . $year . '(\d+)/', $lastClient->code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . $year . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function show(Client $client)
    {
        $client->load([
            'documents',
            'invoices' => fn ($q) => $q->latest('invoice_date')->limit(20),
            'quotes' => fn ($q) => $q->latest('quote_date')->limit(20),
            'purchaseOrders' => fn ($q) => $q->latest('order_date')->limit(20),
            'creditNotes' => fn ($q) => $q->latest('credit_note_date')->limit(20),
            'posSales' => fn ($q) => $q->latest('sold_at')->limit(20),
        ]);

        $stats = [
            'invoices_count' => $client->invoices()->count(),
            'invoices_total' => (float) $client->invoices()->sum('total'),
            'quotes_count' => $client->quotes()->count(),
            'pos_count' => $client->posSales()->count(),
            'pos_total' => (float) $client->posSales()->sum('total'),
            'documents_count' => $client->documents->count(),
        ];

        return view('clients.show', compact('client', 'stats'));
    }

    public function edit(Client $client)
    {
        $client->load('documents');

        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $validated = $this->validatedClientPayload($request, client: $client);
        unset($validated['code']);

        $client->update($validated);
        $this->storeClientDocuments($request, $client);

        return redirect()->route('clients.show', $client)->with('success', 'Client mis à jour avec succès.');
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client supprimé avec succès.');
    }

    public function destroyDocument(Client $client, ClientDocument $document)
    {
        abort_unless($document->client_id === $client->id, 404);

        Storage::disk('public')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document supprimé.');
    }

    public function quickStore(Request $request)
    {
        $validated = $this->validatedClientPayload($request, quick: true);
        $validated['code'] = $this->generateClientCode();
        $validated['status'] = $validated['status'] ?? 'actif';
        $validated['currency'] = $validated['currency'] ?? 'MAD';
        $validated['country'] = $validated['country'] ?? 'Maroc';

        $client = Client::create($validated);

        return response()->json([
            'id' => $client->id,
            'text' => $client->selectLabel(),
        ]);
    }

    private function validatedClientPayload(Request $request, bool $quick = false, ?Client $client = null): array
    {
        $clientType = $request->input('client_type', $client?->client_type ?? 'entreprise');
        $emailRule = Rule::unique('clients', 'email')->ignore($client?->id);

        $rules = [
            'client_type' => 'required|in:entreprise,particulier',
            'status' => ['nullable', 'string', Rule::in(array_keys(Client::STATUSES))],
            'email' => ['nullable', 'email', 'max:255', $emailRule],
            'phone' => 'nullable|string|max:255',
            'whatsapp' => 'nullable|string|max:255',
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
            'date_of_birth' => 'nullable|date|before:today',
            'cin' => 'nullable|string|max:255',
            'cin_issue_city' => 'nullable|string|max:255',
            'rc' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'category' => ['nullable', 'string', Rule::in(array_keys(Client::CATEGORIES))],
            'acquisition_source' => ['nullable', 'string', Rule::in(array_keys(Client::ACQUISITION_SOURCES))],
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'loyalty_points' => 'nullable|integer|min:0',
            'is_vip' => 'nullable|boolean',
            'preferred_payment_method' => ['nullable', 'string', Rule::in(array_keys(Client::PAYMENT_METHODS))],
            'preferred_delivery_mode' => ['nullable', 'string', Rule::in(array_keys(Client::DELIVERY_MODES))],
            'currency' => 'nullable|string|max:10',
            'purchase_frequency' => ['nullable', 'string', Rule::in(array_keys(Client::PURCHASE_FREQUENCIES))],
            'order_ceiling' => 'nullable|numeric|min:0',
        ];

        if ($clientType === 'entreprise') {
            $rules['name'] = 'required|string|max:255';
            $rules['first_name'] = 'nullable|string|max:255';
            $rules['last_name'] = 'nullable|string|max:255';
        } else {
            $rules['first_name'] = 'required|string|max:255';
            $rules['last_name'] = 'required|string|max:255';
            $rules['name'] = 'nullable|string|max:255';
        }

        if (! $quick) {
            $rules['email_confirmation'] = 'nullable|required_with:email|email|same:email';
            $rules['documents'] = 'nullable|array';
            $rules['documents.*'] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120';
        }

        $validated = $request->validate($rules);

        if ($clientType === 'particulier') {
            $validated['first_name'] = trim($validated['first_name'] ?? '');
            $validated['last_name'] = trim($validated['last_name'] ?? '');
            $validated['name'] = trim($validated['first_name'] . ' ' . $validated['last_name']);
        } else {
            $validated['first_name'] = null;
            $validated['last_name'] = null;
        }

        $validated['is_vip'] = $request->boolean('is_vip');
        $validated['loyalty_points'] = (int) ($validated['loyalty_points'] ?? 0);
        $validated['status'] = $validated['status'] ?? 'actif';
        $validated['currency'] = $validated['currency'] ?? 'MAD';
        $validated['country'] = $validated['country'] ?? 'Maroc';

        if (! empty($validated['ville']) && empty($validated['city'])) {
            $validated['city'] = $validated['ville'];
        }

        unset($validated['email_confirmation'], $validated['documents']);

        return $validated;
    }

    private function storeClientDocuments(Request $request, Client $client): void
    {
        if (! $request->hasFile('documents')) {
            return;
        }

        foreach ($request->file('documents') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('clients/documents', 'public');

            $client->documents()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;

        $query = Client::query()->orderBy('name');

        if ($term !== '') {
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            });
        }

        $paginator = $query->paginate($perPage, ['id', 'name', 'email'], 'page', $page);

        return response()->json([
            'results' => $paginator->getCollection()->map(fn (Client $client) => [
                'id' => $client->id,
                'text' => $client->selectLabel(),
            ])->values(),
            'pagination' => [
                'more' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
