<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'is_template',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_template' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function primaryUsers(): HasMany
    {
        return $this->hasMany(User::class, 'primary_role_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public static function makeSlug(string $name): string
    {
        return Str::slug($name);
    }
}
