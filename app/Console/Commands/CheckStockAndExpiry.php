<?php

namespace App\Console\Commands;

use App\Models\Control\Medicine;
use App\Models\Control\WarehouseInventory;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckStockAndExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'apotek:check-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for low stock and expiring items, then notify admins/gudang via Email/WhatsApp.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mengecek stok minimum...');
        $lowStockMedicines = Medicine::belowThreshold()->get();
        
        $this->info('Mengecek barang kadaluarsa (3 bulan ke depan)...');
        $expiringSoon = WarehouseInventory::with('medicine')
            ->expiringSoon(90)
            ->where('stock_on_hand', '>', 0)
            ->get();

        if ($lowStockMedicines->isEmpty() && $expiringSoon->isEmpty()) {
            $this->info('Semua stok aman. Tidak ada notifikasi yang dikirim.');
            return;
        }

        // Simulasikan pengiriman notifikasi ke Admin & Gudang
        $usersToNotify = User::whereIn('role', ['admin', 'gudang'])->get();

        foreach ($usersToNotify as $user) {
            $message = "PEMBERITAHUAN APOTEK KINANTHI\n\n";

            if ($lowStockMedicines->isNotEmpty()) {
                $message .= "âš ï¸  OBAT MENDEKATI STOK MINIMUM:\n";
                foreach ($lowStockMedicines as $med) {
                    $message .= "- {$med->nama_obat}: Sisa {$med->stok_total} {$med->satuan_stok} (Min: {$med->threshold_min})\n";
                }
                $message .= "\n";
            }

            if ($expiringSoon->isNotEmpty()) {
                $message .= "â° OBAT AKAN KADALUARSA (< 3 BULAN):\n";
                foreach ($expiringSoon as $inv) {
                    $message .= "- {$inv->medicine->nama_obat} (Batch: {$inv->batch_no}): Sisa {$inv->stock_on_hand} Exp: {$inv->expired_date->format('d/m/Y')}\n";
                }
            }

            // Integrasi ke WhatsApp API / Email (Schema Logic)
            // Example: Http::post('https://api.whatsapp.com/...', ['to' => $user->phone, 'message' => $message]);
            // Example: Mail::to($user->email)->send(new StockAlertMail($message));
            
            Log::info("Notification sent to {$user->name} ({$user->role}):\n{$message}");
        }

        $this->info('Notifikasi berhasil dicatat (Lihat laravel.log).');
    }
}
