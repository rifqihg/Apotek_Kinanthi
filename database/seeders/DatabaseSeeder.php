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
        Setting::create(['default_ppn' => 11.00, 'default_margin' => 25.00]);

        foreach ([
            ['Admin Kinanthi', 'admin@kinanthi.com', 'admin'],
            ['Apoteker Kinanthi', 'apoteker@kinanthi.com', 'apoteker'],
            ['Kasir Apotek', 'kasir@kinanthi.com', 'kasir'],
        ] as [$name, $email, $role]) {
            User::create(compact('name', 'email', 'role') + ['password' => bcrypt('password')]);
        }

        foreach ([
            ['PT Kimia Farma Tbk', 'Jakarta', '021-1234567'],
            ['PT Kalbe Farma Tbk', 'Jakarta', '021-7654321'],
            ['PT Sanbe Farma', 'Bandung', '022-1234567'],
        ] as [$nama_pbf, $alamat, $telepon]) {
            Supplier::create(compact('nama_pbf', 'alamat', 'telepon') + ['is_active' => true]);
        }

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
