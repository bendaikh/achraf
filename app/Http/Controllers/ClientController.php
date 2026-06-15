<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersIndexTables;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    use FiltersIndexTables;

    public function index(Request $request)
    {
        $query = Client::query()->orderBy('created_at', 'desc');

        $this->applyTableSearch($query, $request, [
            'name', 'email', 'phone', 'code', 'ice', 'ville', 'city',
        ]);

        $clients = $query->paginate(15)->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        $clientCode = $this->generateClientCode();
        return view('clients.create', compact('clientCode'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatedClientPayload($request);
        $validated['code'] = $this->generateClientCode();

        Client::create($validated);

        return redirect()->route('clients.index')->with('success', 'Client créé avec succès.');
    }

    private function generateClientCode(): string
    {
        $prefix = 'CLT';
        $year = date('Y');
        
        // Find the last client code for this year
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
        return view('clients.show', compact('client'));
    }

    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email,' . $client->id,
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'ice' => 'nullable|string|max:255',
            'fiscal_identifier' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'ville' => 'nullable|string|max:255',
        ]);

        $client->update($validated);

        return redirect()->route('clients.index')->with('success', 'Client mis à jour avec succès.');
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('clients.index')->with('success', 'Client supprimé avec succès.');
    }

    public function quickStore(Request $request)
    {
        $validated = $this->validatedClientPayload($request);
        $validated['code'] = $this->generateClientCode();

        $client = Client::create($validated);

        return response()->json([
            'id' => $client->id,
            'text' => $client->selectLabel(),
        ]);
    }

    private function validatedClientPayload(Request $request): array
    {
        $clientType = $request->input('client_type', 'entreprise');

        $rules = [
            'client_type' => 'required|in:entreprise,particulier',
            'email' => 'nullable|email|unique:clients,email',
            'phone' => 'nullable|string|max:255',
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
        ];

        if ($clientType === 'entreprise') {
            $rules['name'] = 'required|string|max:255';
        } else {
            $rules['first_name'] = 'required|string|max:255';
            $rules['last_name'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        if ($clientType === 'particulier') {
            $validated['name'] = trim($validated['first_name'] . ' ' . $validated['last_name']);
            unset($validated['first_name'], $validated['last_name']);
        }

        return $validated;
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
