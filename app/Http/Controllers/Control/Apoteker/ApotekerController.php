<?php

namespace App\Http\Controllers\Control\Apoteker;

use App\Http\Controllers\Controller;
use App\Models\Control\CompoundingSubmission;
use App\Models\Control\RawMaterial;
use Inertia\Inertia;

class ApotekerController extends Controller
{
    /**
     * Display the Apoteker (The Lab) operational dashboard.
     */
    public function dashboard()
    {
        return Inertia::render('Control/Apoteker/Dashboard', [
            'queueCount' => CompoundingSubmission::where('status', 'pending_physical_check')->count(),
            'activeSubmissions' => CompoundingSubmission::with(['details.medicine', 'details.rawMaterial', 'label', 'formula'])
                ->where('user_id', auth()->id())
                ->whereIn('status', ['pending_physical_check', 'pending_final_verification'])
                ->latest()
                ->take(10)
                ->get(),
            'rawMaterialAlerts' => RawMaterial::belowThreshold()->get(),
        ]);
    }
}
