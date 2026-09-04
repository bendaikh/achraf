<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommercialDashboardController extends Controller
{
    public function mine(Request $request)
    {
        $user = $request->user();
        $collaboratorId = $user?->collaborator_id;

        if (! $collaboratorId && ! $user?->isSuperAdmin()) {
            return view('access.dashboards.commercial', [
                'collaborator' => null,
                'stats' => $this->emptyStats(),
                'from' => $request->input('date_from', now()->startOfMonth()->toDateString()),
                'to' => $request->input('date_to', now()->toDateString()),
            ]);
        }

        // Admin can pick a commercial
        if ($request->filled('collaborator_id') && ($user->isSuperAdmin() || $user->hasRole('responsable-commercial') || $user->hasRole('admin'))) {
            $collaboratorId = (int) $request->input('collaborator_id');
        }

        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', now()->toDateString());

        $collaborator = $collaboratorId
            ? Collaborator::query()->find($collaboratorId)
            : null;

        $stats = $collaborator
            ? $this->statsFor($collaborator->id, $from, $to)
            : $this->emptyStats();

        return view('access.dashboards.commercial', [
            'collaborator' => $collaborator,
            'stats' => $stats,
            'from' => $from,
            'to' => $to,
            'commercials' => Collaborator::query()->where('is_commercial', true)->orderBy('last_name')->get(),
        ]);
    }

    public function team(Request $request)
    {
        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', now()->toDateString());

        $rows = Collaborator::query()
            ->where('is_commercial', true)
            ->orderBy('last_name')
            ->get()
            ->map(function (Collaborator $c) use ($from, $to) {
                $stats = $this->statsFor($c->id, $from, $to);

                return [
                    'collaborator' => $c,
                    'type' => $c->typeLabel(),
                    'orders' => $stats['commandes'],
                    'ca' => $stats['ca_mois'],
                    'delivered' => $stats['livrees_amount'],
                    'collected' => $stats['encaissees_amount'],
                    'returns' => $stats['retours_amount'],
                    'commission' => $stats['commission_total'],
                ];
            });

        return view('access.dashboards.team', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * @return array<string, float|int>
     */
    private function statsFor(int $collaboratorId, string $from, string $to): array
    {
        $quotes = Quote::query()->where('collaborator_id', $collaboratorId)->whereBetween('quote_date', [$from, $to]);
        $orders = PurchaseOrder::query()->where('collaborator_id', $collaboratorId)->whereBetween('order_date', [$from, $to]);
        $dns = DeliveryNote::query()->where('collaborator_id', $collaboratorId)->whereBetween('delivery_date', [$from, $to]);
        $invoices = Invoice::query()->where('collaborator_id', $collaboratorId)->whereBetween('invoice_date', [$from, $to]);

        $commissions = Commission::query()
            ->where('collaborator_id', $collaboratorId)
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);

        $sumStatus = fn (string $status) => (clone $commissions)->where('status', $status)->sum('amount');

        return [
            'ca_aujourdhui' => (float) Invoice::query()->where('collaborator_id', $collaboratorId)->whereDate('invoice_date', now()->toDateString())->sum('total'),
            'ca_semaine' => (float) Invoice::query()->where('collaborator_id', $collaboratorId)->whereBetween('invoice_date', [now()->startOfWeek()->toDateString(), now()->toDateString()])->sum('total'),
            'ca_mois' => (float) (clone $invoices)->sum('total'),
            'devis' => (clone $quotes)->count(),
            'commandes' => (clone $orders)->count(),
            'livrees' => (clone $dns)->count(),
            'livrees_amount' => (float) (clone $dns)->sum('total'),
            'facturees' => (clone $invoices)->count(),
            'encaissees' => (clone $invoices)->where('payment_status', Invoice::PAYMENT_PAID)->count(),
            'encaissees_amount' => (float) DB::table('invoice_payments')
                ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
                ->where('invoices.collaborator_id', $collaboratorId)
                ->whereBetween('invoice_payments.payment_date', [$from, $to])
                ->sum('invoice_payments.amount'),
            'annulees' => (clone $invoices)->where('status', 'annulé')->count(),
            'retours_amount' => 0,
            'commission_a_venir' => (float) $sumStatus(Commission::STATUS_A_VENIR),
            'commission_acquise' => (float) $sumStatus(Commission::STATUS_ACQUISE),
            'commission_validee' => (float) $sumStatus(Commission::STATUS_VALIDEE),
            'commission_payee' => (float) $sumStatus(Commission::STATUS_PAYEE),
            'commission_total' => (float) (clone $commissions)->sum('amount'),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function emptyStats(): array
    {
        return [
            'ca_aujourdhui' => 0, 'ca_semaine' => 0, 'ca_mois' => 0,
            'devis' => 0, 'commandes' => 0, 'livrees' => 0, 'livrees_amount' => 0,
            'facturees' => 0, 'encaissees' => 0, 'encaissees_amount' => 0,
            'annulees' => 0, 'retours_amount' => 0,
            'commission_a_venir' => 0, 'commission_acquise' => 0,
            'commission_validee' => 0, 'commission_payee' => 0, 'commission_total' => 0,
        ];
    }
}
