<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Fictional demo accounts. All share the password "Password123". */
class UserSeeder extends Seeder
{
    public const PASSWORD = 'Password123';

    public function run(): void
    {
        $users = [
            ['name' => 'HireFlow Admin', 'email' => 'admin@hireflow.test', 'role' => UserRole::Admin],
            ['name' => 'Demo Candidate One', 'email' => 'candidate1@hireflow.test', 'role' => UserRole::Candidate],
            ['name' => 'Demo Candidate Two', 'email' => 'candidate2@hireflow.test', 'role' => UserRole::Candidate],
        ];

        foreach ($users as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);
            $user->forceFill([
                'name' => $data['name'],
                'password' => self::PASSWORD,
                'role' => $data['role'],
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
