<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Pengelolaan tenant/dosen pemilik produk. Tidak ada hapus: nonaktifkan saja agar rekap lama tetap utuh. */
class TenantController extends Controller
{
    public function index(Request $request)
    {
        $tenants = Tenant::withCount('products')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")
                ->orWhere('unit', 'like', "%{$s}%")))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('admin.tenants.form', ['tenant' => new Tenant(['is_active' => true, 'type' => Tenant::DOSEN])]);
    }

    public function store(Request $request)
    {
        $tenant = Tenant::create($this->validated($request));

        return redirect()->route('admin.tenants.show', $tenant)->with('success', "Tenant/dosen ditambahkan dengan kode {$tenant->code}.");
    }

    public function show(Tenant $tenant)
    {
        $products = $tenant->products()->with('category')->orderBy('name')->get();

        // Penjualan per kegiatan berdasarkan pemilik saat barang terjual (hanya transaksi berhasil)
        $sales = DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->join('events as e', 'e.id', '=', 't.event_id')
            ->where('td.tenant_id', $tenant->id)
            ->where('t.status', Transaction::PAID)
            ->groupBy('e.id', 'e.code', 'e.name', 'e.status', 'e.start_date', 'e.end_date')
            ->selectRaw('e.id, e.code, e.name, e.status, e.start_date, e.end_date,
                COUNT(DISTINCT t.id) as transactions, SUM(td.quantity) as quantity, SUM(td.subtotal) as total')
            ->orderByDesc('e.start_date')
            ->get();

        return view('admin.tenants.show', compact('tenant', 'products', 'sales'));
    }

    public function edit(Tenant $tenant)
    {
        return view('admin.tenants.form', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $tenant->update($this->validated($request));

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Data tenant/dosen diperbarui.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Tenant::TYPES))],
            'unit' => ['nullable', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => 'nama', 'type' => 'jenis', 'unit' => 'unit/fakultas', 'bank_account_number' => 'nomor rekening',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
