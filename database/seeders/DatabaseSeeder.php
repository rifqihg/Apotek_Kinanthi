<?php

namespace Database\Seeders;

use App\Models\Control\Setting;
use App\Models\Control\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pengaturan Sistem Default
        Setting::firstOrCreate(['id' => 1], [
            'default_ppn' => 11.00,
            'default_margin' => 25.00,
        ]);

        // 2. Tiga User dengan role terpisah: Admin, Apoteker, Kasir
        $users = [
            ['name' => 'Admin Kinanthi', 'email' => 'admin@kinanthi.com', 'role' => 'admin'],
            ['name' => 'Apoteker Kinanthi', 'email' => 'apoteker@kinanthi.com', 'role' => 'apoteker'],
            ['name' => 'Kasir Apotek', 'email' => 'kasir@kinanthi.com', 'role' => 'kasir'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'role' => $u['role'],
                    'password' => Hash::make('password'),
                ]
            );
        }

        // 3. Supplier PBF Resmi
        $suppliers = [
            ['nama_pbf' => 'PT Kimia Farma Tbk', 'alamat' => 'Jakarta Pusat', 'telepon' => '021-1234567'],
            ['nama_pbf' => 'PT Kalbe Farma Tbk', 'alamat' => 'Jakarta Timur', 'telepon' => '021-7654321'],
            ['nama_pbf' => 'PT Sanbe Farma', 'alamat' => 'Bandung', 'telepon' => '022-1234567'],
        ];

        foreach ($suppliers as $s) {
            Supplier::firstOrCreate(['nama_pbf' => $s['nama_pbf']], $s + ['is_active' => true]);
        }
    }
}
