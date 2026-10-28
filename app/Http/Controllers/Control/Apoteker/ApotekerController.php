<?php

namespace App\Http\Controllers\Control\Apoteker;

use App\Http\Controllers\Controller;
use App\Models\Control\CompoundingLabel;
use App\Models\Control\CompoundingSubmission;
use App\Models\Control\Medicine;
use App\Models\Control\RawMaterial;
use App\Models\Prescription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApotekerController extends Controller
{
    /**
     * Display the Apoteker (The Lab) operational dashboard.
     */
    public function dashboard(Request $request)
    {
        $period = $request->query('days', '7');
        $today = Carbon::today('Asia/Jakarta');

        // 8 Lightweight Count Metrics (executed as lightning-fast SQL COUNTs)
        $kpi = [
            'rx_menunggu' => Prescription::whereIn('status', ['baru', 'menunggu'])->count(),
            'rx_diproses' => Prescription::where('status', 'diproses')->count(),
            'rx_siap_ambil' => Prescription::where('status', 'siap_ambil')->count(),
            'rx_selesai_hari_ini' => Prescription::where('status', 'selesai')->whereDate('updated_at', $today)->count(),
            'bud_alerts' => CompoundingLabel::whereNotNull('tanggal_kadaluarsa_bud')
                ->whereBetween('tanggal_kadaluarsa_bud', [$today, $today->copy()->addDays(7)->endOfDay()])
                ->count(),
            'compounding_queue' => CompoundingSubmission::whereIn('status', ['pending_physical_check', 'draft', 'pending_final_verification'])->count(),
            'raw_material_alerts' => RawMaterial::belowThreshold()->count(),
            'sipnap_count' => Medicine::sipnap()->count(),
        ];

        // Prescription Trend Data for Chart based on selected period (1D, 7, 30, MTD)
        $prescriptionTrend = $this->getPrescriptionTrend($period, $today);

        // Compounding Distribution by Target Unit: Top 4 + "Lainnya" (maks. 5 entri untuk cegah overflow)
        $allUnits = CompoundingSubmission::selectRaw("COALESCE(NULLIF(target_unit, ''), 'Lainnya') as unit, COUNT(*) as count")
            ->groupBy('unit')
            ->orderByDesc('count')
            ->get();

        $topUnits = $allUnits->take(4);
        $otherCount = $allUnits->skip(4)->sum('count');

        $compoundingDistribution = $topUnits->map(fn($item) => [
            'unit' => $item->unit,
            'count' => (int) $item->count,
        ]);

        if ($otherCount > 0) {
            $compoundingDistribution->push([
                'unit' => 'Lainnya',
                'count' => (int) $otherCount,
            ]);
        }

        // Active Prescriptions (Top 5 awaiting dispensing / compounding) with item metadata
        $activePrescriptions = Prescription::with([
                'patient:id,name',
                'prescriptionItems:id,prescription_id,item_type,custom_formula_name'
            ])
            ->select(['id', 'prescription_number', 'patient_id', 'doctor_name', 'clinic_name', 'status', 'created_at'])
            ->whereIn('status', ['baru', 'menunggu', 'diproses', 'siap_ambil'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($rx) {
                $hasRacikan = $rx->prescriptionItems->some(function ($it) {
                    return $it->item_type === 'custom_formula' || !empty($it->custom_formula_name);
                });

                return [
                    'id' => $rx->id,
                    'prescription_number' => $rx->prescription_number,
                    'patient_name' => $rx->patient?->name ?? 'Pasien Umum',
                    'doctor_name' => $rx->doctor_name ?? '-',
                    'clinic_name' => $rx->clinic_name ?? 'Apotek Kinanthi',
                    'status' => $rx->status,
                    'created_at' => $rx->created_at->timezone('Asia/Jakarta')->format('H:i'),
                    'created_date' => $rx->created_at->timezone('Asia/Jakarta')->isoFormat('D MMM Y'),
                    'items_count' => $rx->prescriptionItems->count(),
                    'has_racikan' => $hasRacikan,
                ];
            });

        // Active Compounding Submissions (Top 5 active in lab)
        $activeSubmissions = CompoundingSubmission::with([
                'formula:id,nama_resep,kategori_sediaan',
                'user:id,name'
            ])
            ->select(['id', 'nama_racikan', 'formula_id', 'target_quantity', 'target_unit', 'status', 'user_id', 'created_at'])
            ->whereIn('status', ['pending_physical_check', 'draft', 'pending_final_verification', 'active'])
            ->latest()
            ->take(5)
            ->get();

        // Raw Materials Below Safety Stock
        $rawMaterialAlerts = RawMaterial::belowThreshold()
            ->select(['id', 'kode_bahan', 'nama_bahan', 'bentuk_sediaan', 'stok_total', 'threshold_min', 'satuan_dasar_penggunaan'])
            ->take(5)
            ->get();

        return Inertia::render('Control/Apoteker/Dashboard', [
            'kpi' => $kpi,
            'prescriptionTrend' => $prescriptionTrend,
            'currentDays' => $period,
            'compoundingDistribution' => $compoundingDistribution,
            'activePrescriptions' => $activePrescriptions,
            'activeSubmissions' => $activeSubmissions,
            'rawMaterialAlerts' => $rawMaterialAlerts,
        ]);
    }

    /**
     * Aggregate Prescription Trends per Period (1D, 7, 30, MTD).
     */
    private function getPrescriptionTrend(string $period, Carbon $today): array
    {
        // 1. FILTER 1D: HARI INI PER MENIT DALAM JAM OPERASIONAL APOTEK (08:00 - 22:00)
        if ($period === '1D') {
            $localStart = $today->copy()->setTime(8, 0, 0);
            $localEnd = $today->copy()->setTime(22, 0, 0);

            $utcStart = $localStart->copy()->timezone('UTC');
            $utcEnd = $localEnd->copy()->timezone('UTC');

            $rxToday = Prescription::where('created_at', '>=', $utcStart)
                ->where('created_at', '<=', $utcEnd)
                ->get(['created_at', 'status']);

            $rxBySlot = [];
            foreach ($rxToday as $rx) {
                $time = $rx->created_at->timezone('Asia/Jakarta');
                $minute = (int) $time->format('i');
                $slotMinute = $minute < 30 ? '00' : '30';
                $slotKey = $time->format('H') . ':' . $slotMinute;
                if (!isset($rxBySlot[$slotKey])) {
                    $rxBySlot[$slotKey] = ['total' => 0, 'selesai' => 0];
                }
                $rxBySlot[$slotKey]['total'] += 1;
                if ($rx->status === 'selesai') {
                    $rxBySlot[$slotKey]['selesai'] += 1;
                }
            }

            // Bangun deret waktu interval 30 menit dari 08:00 hingga 22:00
            $trend = [];
            $cursor = $localStart->copy();
            while ($cursor <= $localEnd) {
                $timeLabel = $cursor->format('H:i');
                $hasData = isset($rxBySlot[$timeLabel]);

                $trend[] = [
                    'date' => $today->format('Y-m-d') . ' ' . $timeLabel,
                    'label' => $timeLabel,
                    'total' => $hasData ? $rxBySlot[$timeLabel]['total'] : 0,
                    'selesai' => $hasData ? $rxBySlot[$timeLabel]['selesai'] : 0,
                ];

                $cursor->addMinutes(30);
            }

            return $trend;
        }

        // 2. FILTER HARIAN (7 HARI, 30 HARI, ATAU MTD BULAN BERJALAN)
        if ($period === 'MTD') {
            $startDate = $today->copy()->startOfMonth();
            $daysCount = (int) $startDate->diffInDays($today) + 1;
        } else {
            $days = (int) $period;
            if ($days <= 0) $days = 7;
            $daysCount = $days;
            $startDate = $today->copy()->subDays($days - 1)->startOfDay();
        }

        $trendRaw = Prescription::where('created_at', '>=', $startDate)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai")
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $trend = [];
        for ($i = $daysCount - 1; $i >= 0; $i--) {
            $dateObj = $today->copy()->subDays($i);
            $dateStr = $dateObj->toDateString();
            $item = $trendRaw->get($dateStr);

            $trend[] = [
                'date' => $dateStr,
                'label' => $dateObj->locale('id')->isoFormat($daysCount > 14 ? 'D MMM' : 'ddd, D MMM'),
                'total' => (int) ($item->total ?? 0),
                'selesai' => (int) ($item->selesai ?? 0),
            ];
        }

        return $trend;
    }
}
