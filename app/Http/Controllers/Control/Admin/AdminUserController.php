<?php

namespace App\Http\Controllers\Control\Admin;

use App\Http\Controllers\Controller;
use App\Models\Control\AuditTrail;
use App\Models\Control\ReturnForm;
use App\Models\Control\StockMutation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    /**
     * Redirect standalone audit trail route to unified StockLogs page with audit tab.
     */
    public function auditTrails(Request $request)
    {
        return redirect()->route('control.admin.stock-logs', array_merge($request->query(), ['tab' => 'audit']));
    }

    /**
     * Display unified stock mutation logs and audit trail activity history.
     */
    public function stockLogs(Request $request)
    {
        // 1. Mutasi Fisik Stok
        $mutationQuery = StockMutation::with([
            'inventory:id,medicine_id,incoming_stock_id,batch_no', 
            'inventory.medicine:id,nama_obat,satuan_stok,satuan_besar,satuan_menengah,base_unit,rasio_konversi,classification', 
            'inventory.incomingStock:id,satuan_input,mid_unit,unit_conversion_factor,mid_to_big_ratio,small_to_mid_ratio',
            'rawMaterialStock:id,raw_material_id,batch_no', 
            'rawMaterialStock.rawMaterial:id,nama_bahan,satuan_dasar_penggunaan', 
            'item'
        ])->where('quantity', '>', 0)
          ->where('type', '!=', 'Distribusi Keluar')
          ->where(function ($q) {
              $q->whereNull('note')
                ->orWhere(function ($q2) {
                    $q2->where('note', 'not like', 'Distribusi ke Kasir ID%')
                       ->where('note', 'not like', 'Return dari Kasir ID%');
                });
          });

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $mutationQuery->whereBetween('created_at', ["{$request->start_date} 00:00:00", "{$request->end_date} 23:59:59"]);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            match ($request->type) {
                'Masuk' => $mutationQuery->whereIn('type', ['Masuk', 'Penyesuaian Masuk', 'Distribusi Masuk']),
                'Retur' => $mutationQuery->where(fn($q) => $q->where('reference_type', ReturnForm::class)->orWhere('type', 'Retur')),
                'Jual'  => $mutationQuery->where(fn($q) => $q->whereIn('type', ['Keluar', 'Penjualan', 'Penyesuaian Keluar'])->where(fn($q2) => $q2->whereNull('reference_type')->orWhere('reference_type', '!=', ReturnForm::class))->where('type', 'not like', '%Distribusi%')->where(fn($q3) => $q3->whereNull('note')->orWhere('note', 'not like', '%Distribusi%'))),
                'Distribusi' => $mutationQuery->where(fn($q) => $q->where('type', 'like', '%Distribusi%')->orWhere('note', 'like', '%Distribusi%')),
                default => null,
            };
        }

        if ($request->boolean('is_controlled')) {
            $mutationQuery->whereIn('warehouse_inventory_id', function ($sub) {
                $sub->select('id')
                    ->from('warehouse_inventories')
                    ->whereIn('medicine_id', function ($sub2) {
                        $sub2->select('id')
                             ->from('medicines')
                             ->whereIn('classification', ['narkotika', 'psikotropika']);
                    });
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $mutationQuery->where(fn($q) =>
                $q->whereHas('inventory.medicine', fn($q2) => $q2->where('nama_obat', 'like', "%{$search}%"))
                  ->orWhereHas('rawMaterialStock.rawMaterial', fn($q2) => $q2->where('nama_bahan', 'like', "%{$search}%"))
                  ->orWhereHas('inventory', fn($q2) => $q2->where('batch_no', 'like', "%{$search}%"))
                  ->orWhereHas('rawMaterialStock', fn($q2) => $q2->where('batch_no', 'like', "%{$search}%"))
            );
        }

        $logsClosure = function () use ($mutationQuery) {
            return (clone $mutationQuery)->latest()->paginate(15, ['*'], 'mutations_page')->withQueryString();
        };

        $kpiClosure = function () use ($mutationQuery) {
            $kpiCounts = (clone $mutationQuery)
                ->selectRaw("
                    COUNT(*) as total_count,
                    SUM(CASE WHEN type IN ('Masuk', 'Penyesuaian Masuk', 'Retur') THEN 1 ELSE 0 END) as count_in,
                    SUM(CASE WHEN type IN ('Keluar', 'Pemusnahan', 'Penjualan', 'Penyesuaian Keluar') THEN 1 ELSE 0 END) as count_out
                ")
                ->first();

            return [
                'in' => (int) ($kpiCounts->count_in ?? 0),
                'out' => (int) ($kpiCounts->count_out ?? 0),
                'total' => (int) ($kpiCounts->total_count ?? 0)
            ];
        };

        $auditsClosure = function () use ($request) {
            return AuditTrail::with('user:id,name,role')
                ->latest('performed_at')
                ->when($request->filled('audit_search'), function ($q) use ($request) {
                    $s = $request->audit_search;
                    $q->where(fn($sub) =>
                        $sub->where('description', 'like', "%{$s}%")
                            ->orWhere('action', 'like', "%{$s}%")
                            ->orWhereHas('user', fn($qu) => $qu->where('name', 'like', "%{$s}%"))
                    );
                })
                ->when($request->filled('role'), fn($q) => $q->whereHas('user', fn($qu) => $qu->where('role', $request->role)))
                ->paginate(15, ['*'], 'audits_page')
                ->withQueryString();
        };

        return Inertia::render('Control/Admin/StockLogs', [
            'logs' => $logsClosure,
            'kpi' => $kpiClosure,
            'audits' => $auditsClosure,
            'filters' => $request->only(['search', 'start_date', 'end_date', 'type', 'role', 'audit_search', 'tab', 'is_controlled'])
        ]);
    }

    /**
     * Display list of users/staff.
     */
    public function users()
    {
        return Inertia::render('Control/Admin/Users', [
            'users' => User::latest()->paginate(15),
        ]);
    }

    /**
     * Store a newly created user/staff account.
     */
    public function storeUser(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'role' => 'required|in:admin,apoteker,kasir',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $v['name'],
            'email' => $v['email'],
            'role' => $v['role'],
            'password' => Hash::make($v['password']),
        ]);

        AuditTrail::log($request->user()->id, 'CREATE_USER', "Tambah user baru: {$user->name} ({$user->role})", User::class, $user->id);

        return redirect()->back()->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * Update user/staff information.
     */
    public function updateUser(Request $request, User $user)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,apoteker,kasir',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $old = $user->toArray();
        $user->update([
            'name' => $v['name'],
            'email' => $v['email'],
            'role' => $v['role'],
        ]);

        if (!empty($v['password'])) {
            $user->update(['password' => Hash::make($v['password'])]);
        }

        AuditTrail::log($request->user()->id, 'UPDATE_USER', "Update user: {$user->name}", User::class, $user->id, $old, $v);

        return redirect()->back()->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Remove a user/staff account.
     */
    public function destroyUser(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return redirect()->back()->with('error', 'Anda tidak bisa menghapus diri sendiri.');
        }

        $name = $user->name;
        $user->delete();

        AuditTrail::log($request->user()->id, 'DELETE_USER', "Hapus user: {$name}");

        return redirect()->back()->with('success', 'User berhasil dihapus.');
    }
}
