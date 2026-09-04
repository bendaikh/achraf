<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Services\Access\CommercialAttributionService;
use App\Support\AccessPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CommercialReassignmentController extends Controller
{
    public function __construct(
        protected CommercialAttributionService $attribution,
    ) {}

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user?->isSuperAdmin()
            && ! AccessPermission::allows($user, 'sensible.reattribuer_commercial')
            && ! $user?->hasRole('admin')
            && ! $user?->hasRole('administrateur')
            && ! $user?->hasRole('responsable-commercial')
        ) {
            abort(403, 'Vous n\'avez pas le droit de réattribuer un commercial.');
        }

        $validated = $request->validate([
            'document_type' => ['required', 'in:quote,purchase_order,delivery_note,invoice,credit_note'],
            'document_id' => ['required', 'integer'],
            'collaborator_id' => ['required', 'exists:collaborators,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $document = $this->resolveDocument($validated['document_type'], (int) $validated['document_id']);

        $this->attribution->reassign(
            $document,
            (int) $validated['collaborator_id'],
            $validated['reason'] ?? null,
            $user,
        );

        return back()->with('success', 'Commercial réattribué. L\'historique a été conservé.');
    }

    private function resolveDocument(string $type, int $id): Model
    {
        return match ($type) {
            'quote' => Quote::query()->findOrFail($id),
            'purchase_order' => PurchaseOrder::query()->findOrFail($id),
            'delivery_note' => DeliveryNote::query()->findOrFail($id),
            'invoice' => Invoice::query()->findOrFail($id),
            'credit_note' => CreditNote::query()->findOrFail($id),
            default => abort(404),
        };
    }
}
