<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@mahalustudio.com'],
            [
                'name' => 'Admin Mahalu Studio',
                'password' => Hash::make('password123'), // ganti nanti setelah testing
            ]
        );

        $admin->assignRole('admin'); // role ini butuh PermissionSeeder sudah jalan duluan
    }
}