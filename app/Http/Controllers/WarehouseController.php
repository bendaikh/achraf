<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Support\StockSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_primary' => 'nullable|boolean',
            'kind' => ['nullable', Rule::in(['physical', 'online'])],
            'comment' => 'nullable|string|max:1000',
        ]);

        $validated['is_primary'] = $request->boolean('is_primary');
        $validated['is_fulfillment_default'] = $request->boolean('is_fulfillment_default');
        $validated['available_for_shopify'] = $request->boolean('available_for_shopify');
        $validated['kind'] = $validated['kind'] ?? 'physical';
        $validated['archived_at'] = null;

        if (($validated['kind'] ?? 'physical') === 'online') {
            $validated['available_for_shopify'] = false;
        }

        if ($validated['is_primary']) {
            Warehouse::query()->update(['is_primary' => false]);
        }
        if ($validated['is_fulfillment_default']) {
            Warehouse::query()->update(['is_fulfillment_default' => false]);
        }

        Warehouse::create($validated);

        return redirect()->route('settings.stock', ['tab' => 'depots'])
            ->with('success', 'Dépôt créé avec succès.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        if ($warehouse->isArchived()) {
            return redirect()->route('settings.stock', ['tab' => 'depots'])
                ->with('error', 'Ce dépôt est archivé et ne peut plus être modifié.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($warehouse->id)],
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_primary' => 'nullable|boolean',
            'comment' => 'nullable|string|max:1000',
        ]);

        $validated['is_primary'] = $request->boolean('is_primary');
        $validated['is_fulfillment_default'] = $request->boolean('is_fulfillment_default');
        $validated['available_for_shopify'] = $request->boolean('available_for_shopify');

        if ($warehouse->isOnline()) {
            $validated['available_for_shopify'] = false;
        }

        if ($validated['is_primary']) {
            Warehouse::query()->where('id', '!=', $warehouse->id)->update(['is_primary' => false]);
        }
        if ($validated['is_fulfillment_default']) {
            Warehouse::query()->where('id', '!=', $warehouse->id)->update(['is_fulfillment_default' => false]);
        }

        $warehouse->update($validated);

        return redirect()->route('settings.stock', ['tab' => 'depots'])
            ->with('success', 'Dépôt mis à jour.');
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->hasPhysicalStock()) {
            return redirect()->route('settings.stock', ['tab' => 'depots'])
                ->with('error', 'Impossible de supprimer « '.$warehouse->name.' » : il contient encore du stock. Transférez le stock ou mettez-le à 0 via inventaire.');
        }

        if ($warehouse->hasStockHistory()) {
            $warehouse->archive('suppression refusée — historique de mouvements conservé');

            return redirect()->route('settings.stock', ['tab' => 'depots'])
                ->with('success', 'Le dépôt « '.$warehouse->name.' » a été archivé (historique conservé). Il n’apparaît plus dans les listes actives ni pour de nouveaux mouvements.');
        }

        $warehouse->locations()->delete();
        $warehouse->productStocks()->delete();
        $warehouse->delete();

        return redirect()->route('settings.stock', ['tab' => 'depots'])
            ->with('success', 'Dépôt supprimé.');
    }

    public function archive(Warehouse $warehouse)
    {
        if ($warehouse->hasPhysicalStock()) {
            return redirect()->route('settings.stock', ['tab' => 'depots'])
                ->with('error', 'Impossible d’archiver « '.$warehouse->name.' » : il contient encore du stock.');
        }

        if ($warehouse->isArchived()) {
            return redirect()->route('settings.stock', ['tab' => 'depots'])
                ->with('error', 'Ce dépôt est déjà archivé.');
        }

        $warehouse->archive('archivage manuel');

        return redirect()->route('settings.stock', ['tab' => 'depots'])
            ->with('success', 'Dépôt « '.$warehouse->name.' » archivé.');
    }

    public function storeLocation(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('warehouse_locations', 'code')->where(fn ($q) => $q->where('warehouse_id', $request->input('warehouse_id'))),
            ],
            'name' => 'nullable|string|max:255',
            'zone' => 'nullable|string|max:100',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $warehouse = Warehouse::query()->findOrFail($validated['warehouse_id']);
        if ($warehouse->isArchived()) {
            return redirect()->route('settings.stock', ['tab' => 'emplacements'])
                ->with('error', 'Impossible d’ajouter un emplacement sur un dépôt archivé.');
        }

        WarehouseLocation::create($validated);

        return redirect()->route('settings.stock', ['tab' => 'emplacements'])
            ->with('success', 'Emplacement créé avec succès.');
    }

    public function updateLocation(Request $request, WarehouseLocation $location)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('warehouse_locations', 'code')
                    ->where(fn ($q) => $q->where('warehouse_id', $request->input('warehouse_id')))
                    ->ignore($location->id),
            ],
            'name' => 'nullable|string|max:255',
            'zone' => 'nullable|string|max:100',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $location->update($validated);

        return redirect()->route('settings.stock', ['tab' => 'emplacements'])
            ->with('success', 'Emplacement mis à jour.');
    }

    public function destroyLocation(WarehouseLocation $location)
    {
        if ($location->productStocks()->where('quantity', '!=', 0)->exists()) {
            return redirect()->route('settings.stock', ['tab' => 'emplacements'])
                ->with('error', 'Impossible de supprimer un emplacement qui contient encore du stock.');
        }

        $location->productStocks()->delete();
        $location->delete();

        return redirect()->route('settings.stock', ['tab' => 'emplacements'])
            ->with('success', 'Emplacement supprimé.');
    }

    /**
     * JSON: locations for a warehouse (cascade selects).
     */
    public function locationsJson(Request $request)
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $query = WarehouseLocation::query()->active()->orderBy('code');
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return response()->json(
            $query->get(['id', 'warehouse_id', 'code', 'name', 'zone'])
                ->map(fn (WarehouseLocation $loc) => [
                    'id' => $loc->id,
                    'warehouse_id' => $loc->warehouse_id,
                    'label' => $loc->displayLabel(),
                    'code' => $loc->code,
                ])
        );
    }
}
