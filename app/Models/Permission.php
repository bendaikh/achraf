<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'key',
        'module',
        'resource',
        'action',
        'label',
        'group_label',
        'is_sensitive',
        'sort_order',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_user')
            ->withPivot('granted')
            ->withTimestamps();
    }
}
