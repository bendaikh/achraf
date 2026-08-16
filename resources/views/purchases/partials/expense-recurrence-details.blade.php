<div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="rounded-xl border {{ $expense->isPendingPayment() ? 'border-amber-200 bg-amber-50' : 'border-green-200 bg-green-50' }} p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Statut du paiement</p>
                <p class="mt-1 font-semibold {{ $expense->isPendingPayment() ? 'text-amber-800' : 'text-green-800' }}">
                    {{ $expense->isPendingPayment() ? 'À payer / En attente' : 'Payée' }}
                </p>
                @if($expense->paid_at)
                    <p class="mt-1 text-xs text-gray-500">Réglée le {{ $expense->paid_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>
            @if($expense->isPendingPayment())
                <form action="{{ route('expenses.mark-paid', $expense) }}" method="POST">
                    @csrf
                    <button class="px-3 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">
                        Enregistrer le paiement
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if($expense->is_recurring)
        @php($template = $expense->recurrence_parent_id ? $expense->recurrenceParent : $expense)
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-blue-600">↻ Dépense récurrente</p>
                    <p class="mt-1 font-semibold text-blue-900">{{ $expense->recurrenceLabel() }}</p>
                    @if($template?->next_due_date)
                        <p class="mt-1 text-sm text-blue-800">Prochaine échéance : {{ $template->next_due_date->format('d/m/Y') }}</p>
                    @endif
                    @if($expense->recurrence_parent_id)
                        <p class="mt-1 text-xs text-blue-700">Occurrence du {{ $expense->occurrence_date?->format('d/m/Y') }}</p>
                    @endif
                </div>

                @if($expense->isRecurrenceTemplate())
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium
                        {{ $expense->recurrence_status === \App\Models\Expense::RECURRENCE_ACTIVE ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                        {{ match($expense->recurrence_status) {
                            \App\Models\Expense::RECURRENCE_ACTIVE => 'Active',
                            \App\Models\Expense::RECURRENCE_SUSPENDED => 'Suspendue',
                            default => 'Arrêtée',
                        } }}
                    </span>
                @endif
            </div>

            @if($expense->isRecurrenceTemplate() && $expense->recurrence_status !== \App\Models\Expense::RECURRENCE_STOPPED)
                <div class="mt-4 flex flex-wrap gap-2 border-t border-blue-200 pt-3">
                    @if($expense->recurrence_status === \App\Models\Expense::RECURRENCE_ACTIVE)
                        <form action="{{ route('expenses.recurrence.suspend', $expense) }}" method="POST">
                            @csrf
                            <button class="text-sm font-medium text-amber-700 hover:text-amber-900">Suspendre</button>
                        </form>
                    @else
                        <form action="{{ route('expenses.recurrence.resume', $expense) }}" method="POST">
                            @csrf
                            <button class="text-sm font-medium text-green-700 hover:text-green-900">Reprendre</button>
                        </form>
                    @endif
                    <form action="{{ route('expenses.recurrence.stop', $expense) }}" method="POST" onsubmit="return confirm('Arrêter définitivement cette récurrence ?')">
                        @csrf
                        <button class="text-sm font-medium text-red-700 hover:text-red-900">Arrêter</button>
                    </form>
                    <span class="text-xs text-gray-500">Les échéances déjà créées ne seront jamais modifiées.</span>
                </div>
            @endif
        </div>
    @endif
</div>
