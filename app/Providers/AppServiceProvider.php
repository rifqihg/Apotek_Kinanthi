<?php

namespace App\Providers;

use App\Models\Control\RawMaterialStock;
use App\Models\Control\WarehouseInventory;
use App\Observers\RawMaterialStockObserver;
use App\Observers\WarehouseInventoryObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        WarehouseInventory::observe(WarehouseInventoryObserver::class);
        RawMaterialStock::observe(RawMaterialStockObserver::class);
    }
}
