<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'superadmin',
                'description' => 'Full access to all system features',
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrative access',
            ],
            [
                'name' => 'User',
                'slug' => 'user',
                'description' => 'Regular user access',
            ],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
