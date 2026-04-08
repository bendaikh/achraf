<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::orderBy('created_at', 'desc')->paginate(15);
        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:suppliers,email',
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

        Supplier::create($validated);

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur créé avec succès.');
    }

    public function show(Supplier $supplier)
    {
        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:suppliers,email,' . $supplier->id,
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

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur mis à jour avec succès.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return redirect()->route('suppliers.index')->with('success', 'Fournisseur supprimé avec succès.');
    }
}
