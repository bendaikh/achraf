<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $shopifyPriceType = Setting::getShopifyPriceType();
        return view('settings.index', compact('shopifyPriceType'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'shopify_price_type' => 'required|in:ttc,ht',
        ]);

        Setting::set(
            'shopify_price_type',
            $validated['shopify_price_type'],
            'Détermine si les prix des produits Shopify sont TTC ou HT'
        );

        return redirect()->route('settings.index')->with('success', 'Paramètres mis à jour avec succès.');
    }
}
