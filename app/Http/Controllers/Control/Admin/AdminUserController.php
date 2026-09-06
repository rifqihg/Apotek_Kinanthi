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
     * Display audit trail log activities.
     */
    public function auditTrails(Request $request)
    {
        $query = AuditTrail::with('user')->latest('performed_at');

        // Advanced Filtering
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', "%{$request->search}%")
                    ->orWhere('action', 'like', "%{$request->search}%")
                    ->orWhereHas('user', function ($qu) use ($request) {
                        $qu->where('name', 'like', "%{$request->search}%");
                    });
            });
        }

        if ($request->role) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('role', $request->role);
            });
        }

        return Inertia::render('Control/Admin/AuditTrail', [
            'audits' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'role'])
        ]);
    }

    /**
     * Display stock mutation logs and internal movement history.
     */
    public function stockLogs(Request $request)
    {
        $query = StockMutation::with([
            'inventory.medicine', 
            'inventory.incomingStock', 
            'rawMaterialStock.rawMaterial', 
            'item'
        ])->where('quantity', '>', 0);

        if ($request->has('start_date') && $request->has('end_date') && $request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        if ($request->has('type') && $request->type !== 'all') {
            if ($request->type === 'Masuk') {
                $query->whereIn('type', ['Masuk', 'Penyesuaian Masuk']);
            } elseif ($request->type === 'Retur') {
                $query->where(function($q) {
                    $q->where('reference_type', ReturnForm::class)
                      ->orWhere('type', 'Retur');
                });
            } elseif ($request->type === 'Jual') {
                $query->where(function($q) {
                    $q->whereIn('type', ['Keluar', 'Penjualan', 'Penyesuaian Keluar'])
                      ->where(function($q2) {
                          $q2->whereNull('reference_type')
                             ->orWhere('reference_type', '!=', ReturnForm::class);
                      });
                });
            }
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('inventory.medicine', function ($q2) use ($search) {
                    $q2->where('nama_obat', 'like', "%{$search}%");
                })->orWhereHas('rawMaterialStock.rawMaterial', function ($q2) use ($search) {
                    $q2->where('nama_bahan', 'like', "%{$search}%");
                });
            });
        }

        $kpi_in = (clone $query)->whereIn('type', ['Masuk', 'Penyesuaian Masuk', 'Retur'])->count();
        $kpi_out = (clone $query)->whereIn('type', ['Keluar', 'Pemusnahan', 'Penjualan', 'Penyesuaian Keluar'])->count();

        $logs = $query->latest()->paginate(25)->withQueryString();
        
        return Inertia::render('Control/Admin/StockLogs', [
            'logs' => $logs,
            'kpi' => [
                'in' => $kpi_in,
                'out' => $kpi_out,
                'total' => $logs->total()
            ],
            'filters' => $request->only(['search', 'start_date', 'end_date', 'type'])
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
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|max:255|unique:users',
            'role' => 'required|in:admin,apoteker,kasir',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $v['name'],
            'username' => $v['username'],
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
            'username' => 'required|string|max:50|unique:users,username,' . $user->id,
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,apoteker,kasir',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $old = $user->toArray();
        $user->update([
            'name' => $v['name'],
            'username' => $v['username'],
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
