<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Default admin credentials (change in production).
     * Email: admin@admin.com
     * Password: 123456
     */
    public const ADMIN_EMAIL = 'admin@admin.com';
    public const ADMIN_DEFAULT_PASSWORD = '123456';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Administrator',
                'email' => self::ADMIN_EMAIL,
                'password' => Hash::make(self::ADMIN_DEFAULT_PASSWORD),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
