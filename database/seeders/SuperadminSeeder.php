<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@resqhub.ph'],
            [
                'name' => 'Super Admin',
                'password' => 'superadmin',
                'role' => UserRole::Superadmin,
                'contact_number' => '09171230000',
            ],
        );
    }
}
