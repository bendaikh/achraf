<?php

namespace App\Models;

use App\Models\Concerns\HasManagedDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasManagedDocuments;

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const RECURRENCE_ACTIVE = 'active';

    public const RECURRENCE_SUSPENDED = 'suspended';

    public const RECURRENCE_STOPPED = 'stopped';

    public const FREQUENCIES = [
        'weekly' => 'Hebdomadaire',
        'monthly' => 'Mensuelle',
        'quarterly' => 'Trimestrielle',
        'semiannual' => 'Semestrielle',
        'annual' => 'Annuelle',
        'custom' => 'Personnalisée',
    ];

    protected $attributes = [
        'payment_status' => self::PAYMENT_PAID,
        'is_recurring' => false,
        'recurrence_interval' => 1,
    ];

    protected $fillable = [
        'designation', 'expense_type', 'expense_category', 'expense_date', 'amount', 'currency',
        'reference', 'client_id', 'supplier_id', 'payment_method', 'account', 'tax_type', 'invoice_file_path',
        'payment_status', 'paid_at', 'is_recurring', 'recurrence_frequency', 'recurrence_interval',
        'recurrence_interval_unit', 'recurrence_start_date', 'recurrence_end_date', 'next_due_date',
        'recurrence_status', 'recurrence_parent_id', 'occurrence_date',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'is_recurring' => 'boolean',
        'recurrence_start_date' => 'date',
        'recurrence_end_date' => 'date',
        'next_due_date' => 'date',
        'occurrence_date' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function recurrenceParent()
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    public function occurrences()
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('is_recurring', true);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', self::PAYMENT_PAID);
    }

    public function isRecurrenceTemplate(): bool
    {
        return $this->is_recurring && $this->recurrence_parent_id === null && $this->recurrence_frequency !== null;
    }

    public function isPendingPayment(): bool
    {
        return $this->payment_status === self::PAYMENT_PENDING;
    }

    public function recurrenceLabel(): ?string
    {
        $template = $this->recurrence_parent_id ? $this->recurrenceParent : $this;

        if (! $template?->recurrence_frequency) {
            return null;
        }

        $label = self::FREQUENCIES[$template->recurrence_frequency] ?? ucfirst($template->recurrence_frequency);
        $interval = max(1, (int) $template->recurrence_interval);

        if ($template->recurrence_frequency === 'custom') {
            $unit = $template->recurrence_interval_unit === 'day' ? 'jour' : 'mois';

            return "Tous les {$interval} {$unit}".($interval > 1 ? 's' : '');
        }

        return $interval > 1 ? "{$label} (tous les {$interval} cycles)" : $label;
    }
}
