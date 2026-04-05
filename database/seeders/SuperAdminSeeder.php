<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@lavfast.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $superAdminRole = Role::where('slug', 'superadmin')->first();
        
        if ($superAdminRole) {
            $superAdmin->roles()->attach($superAdminRole);
        }
    }
}
