<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRuleSet extends Model
{
    protected $fillable = [
        'name',
        'effective_from',
        'rules',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'rules' => 'array',
    ];

    public static function forDate(\DateTimeInterface $date): ?self
    {
        return static::query()
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function rule(string $key, mixed $default = null): mixed
    {
        return data_get($this->rules, $key, $default);
    }
}
