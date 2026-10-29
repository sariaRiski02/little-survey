<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Create or update the one and only user from the ADMIN_* settings.
     */
    public function run(): void
    {
        $admin = config('auth.admin');

        if (blank($admin['email']) || blank($admin['password'])) {
            $this->command?->warn('ADMIN_EMAIL dan ADMIN_PASSWORD belum diisi di .env, akun admin dilewati.');

            return;
        }

        $user = User::query()->oldest('id')->first() ?? new User;

        $user->forceFill([
            'name' => $admin['name'] ?: 'Admin',
            'email' => $admin['email'],
            'password' => Hash::make($admin['password']),
        ])->save();

        User::query()->whereKeyNot($user->getKey())->delete();
    }
}
