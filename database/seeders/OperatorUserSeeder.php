<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OperatorUserSeeder extends Seeder
{
    /**
     * Seed demo operator users for the intake station.
     */
    public function run(): void
    {
        $operators = [
            [
                'name' => 'Santos, M.L.',
                'email' => 'santos.ml@cdrrmo.gov.ph',
                'role' => UserRole::Operator,
                'badge_number' => 'OP-0001',
            ],
            [
                'name' => 'Reyes, J.A.',
                'email' => 'reyes.ja@cdrrmo.gov.ph',
                'role' => UserRole::Supervisor,
                'badge_number' => 'SV-0010',
            ],
            [
                'name' => 'Admin Operator',
                'email' => 'admin.ops@cdrrmo.gov.ph',
                'role' => UserRole::Admin,
                'badge_number' => 'AD-0001',
            ],
        ];

        foreach ($operators as $data) {
            User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('password'),
                    'role' => $data['role'],
                    'badge_number' => $data['badge_number'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
