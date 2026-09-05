<?php

namespace Database\Seeders;

use App\Models\Control\Medicine;
use App\Models\Control\Setting;
use App\Models\Control\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create default settings
        Setting::create(['default_ppn' => 11.00, 'default_margin' => 25.00]);

        // Create users for each role
        User::create([
            'name' => 'Admin Kinanthi',
            'email' => 'admin@kinanthi.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Apoteker Kinanthi',
            'email' => 'apoteker@kinanthi.com',
            'password' => bcrypt('password'),
            'role' => 'apoteker',
        ]);

        User::create([
            'name' => 'Kasir Apotek',
            'email' => 'kasir@kinanthi.com',
            'password' => bcrypt('password'),
            'role' => 'kasir',
        ]);

        // Sample suppliers
        Supplier::create(['nama_pbf' => 'PT Kimia Farma Tbk', 'alamat' => 'Jakarta', 'telepon' => '021-1234567', 'is_active' => true]);
        Supplier::create(['nama_pbf' => 'PT Kalbe Farma Tbk', 'alamat' => 'Jakarta', 'telepon' => '021-7654321', 'is_active' => true]);
        Supplier::create(['nama_pbf' => 'PT Sanbe Farma', 'alamat' => 'Bandung', 'telepon' => '022-1234567', 'is_active' => true]);

        // Sample medicines
        $medicines = [
            ['nama_obat' => 'Amoxicillin 500mg', 'kode_obat' => 'AMX500', 'satuan_stok' => 'Kapsul', 'threshold_min' => 50],
            ['nama_obat' => 'Paracetamol 500mg', 'kode_obat' => 'PCT500', 'satuan_stok' => 'Tablet', 'threshold_min' => 100],
            ['nama_obat' => 'Omeprazole 20mg', 'kode_obat' => 'OMP020', 'satuan_stok' => 'Kapsul', 'threshold_min' => 30],
            ['nama_obat' => 'Salep Betamethasone', 'kode_obat' => 'SBT001', 'satuan_stok' => 'Tube', 'threshold_min' => 20],
            ['nama_obat' => 'Vaseline Album', 'kode_obat' => 'VSL001', 'satuan_stok' => 'Pot', 'threshold_min' => 15],
            ['nama_obat' => 'Cetirizine 10mg', 'kode_obat' => 'CTZ010', 'satuan_stok' => 'Tablet', 'threshold_min' => 40],
            ['nama_obat' => 'Metformin 500mg', 'kode_obat' => 'MTF500', 'satuan_stok' => 'Tablet', 'threshold_min' => 60],
            ['nama_obat' => 'Asam Mefenamat 500mg', 'kode_obat' => 'AMF500', 'satuan_stok' => 'Tablet', 'threshold_min' => 50],
        ];

        foreach ($medicines as $m) {
            Medicine::create($m);
        }
    }
}
