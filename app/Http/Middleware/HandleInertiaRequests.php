<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $notifications = [
            'pending_admin' => 0,
            
            'pending_revision' => 0,
            'low_stock' => 0,
        ];

        if ($user) {
            if ($user->role === 'admin' || $user->role === 'super_admin') {
                $notifications['pending_admin'] = \App\Models\Control\IncomingStock::where('status_approval', 'pending')->count();
                $notifications['pending_revision'] = \App\Models\Control\IncomingStock::where('status_approval', 'pending_revision')->count();
                
                // Multi-Gate Compounding: Pending Pricing (Gate 2)
                $notifications['pending_compounding'] = \App\Models\Control\CompoundingSubmission::where('status', 'pending_final_verification')->count();
                
                $notifications['pending_disposals'] = \App\Models\Control\InventoryLoss::where('status', 'pending')->count();
                
                // Verification: invoices verified physically but not yet price-finalized
                $notifications['pending_verification'] = \App\Models\Control\IncomingStock::where('status_approval', 'approved')
                    ->whereNotNull('qty_fisik_verify')
                    ->whereNull('harga_jual_akhir')
                    ->count();

                // Raw Materials Gates
                $notifications['pending_raw_materials'] = \App\Models\Control\RawMaterial::where('status', 'pending_registration')->count(); // Gate 1
                $notifications['pending_admin_raw_materials'] = \App\Models\Control\RawMaterial::where('status', 'pending_final_verification')->count(); // Gate 2

                // Physical Verifications (Merged from Gudang)
                $notifications['pending_physical_verify'] = \App\Models\Control\IncomingStock::pendingVerify()->count()
                    + \App\Models\Control\CompoundingSubmission::where('status', 'pending_physical_check')->count()
                    + \App\Models\Control\RawMaterial::where('status', 'pending_physical_check')->count();
                    
                $notifications['opname_schedule'] = \App\Models\Control\WarehouseInventory::active()->count() 
                                                  + \App\Models\Control\RawMaterialStock::active()->count()
                                                  + \App\Models\Control\CompoundedProduct::active()->count();
            }
            
            // Common alerts
            $notifications['low_stock'] = \App\Models\Control\Medicine::whereRaw('stok_total < threshold_min')->count();
            $notifications['bud_critical'] = \App\Models\Control\CompoundedProduct::where('status', 'active')->where('bud_date', '<', now()->addDays(30))->count();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => $notifications,
        ];
    }
}
