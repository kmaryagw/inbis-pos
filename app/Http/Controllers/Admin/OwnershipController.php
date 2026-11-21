<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Penetapan pemilik (tenant/dosen) untuk banyak produk sekaligus. */
class OwnershipController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['category', 'tenant'])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")))
            ->when($request->owner === 'none', fn ($q) => $q->whereNull('tenant_id'))
            ->when(ctype_digit((string) $request->owner), fn ($q) => $q->where('tenant_id', $request->owner))
            ->when($request->category, fn ($q, $c) => $q->where('category_id', $c))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.ownership.index', [
            'products' => $products,
            'tenants' => Tenant::orderBy('name')->get(['id', 'code', 'name', 'type', 'is_active']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'unownedCount' => Product::whereNull('tenant_id')->count(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*' => ['integer', 'exists:products,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ], ['products.required' => 'Pilih minimal satu produk.'], ['tenant_id' => 'pemilik']);

        $tenantId = $data['tenant_id'] ?? null;
        if ($tenantId && ! Tenant::whereKey($tenantId)->value('is_active')) {
            return back()->with('error', 'Tenant/dosen tidak aktif tidak dapat diberi produk.');
        }

        $backfilled = DB::transaction(function () use ($data, $tenantId) {
            Product::whereIn('id', $data['products'])->update(['tenant_id' => $tenantId]);

            // Penjualan lama yang belum punya pemilik ikut tercatat ke pemilik baru.
            // Penjualan yang sudah tercatat atas pemilik lain tidak dipindahkan.
            if (! $tenantId) {
                return 0;
            }

            return DB::table('transaction_details')
                ->whereNull('tenant_id')
                ->whereIn('event_product_id', DB::table('event_products')->whereIn('product_id', $data['products'])->select('id'))
                ->update(['tenant_id' => $tenantId]);
        });

        $count = count($data['products']);
        $message = $tenantId
            ? "{$count} produk ditetapkan ke ".Tenant::find($tenantId)->name.'.'
            : "Pemilik {$count} produk dikosongkan.";
        if ($backfilled) {
            $message .= " {$backfilled} baris penjualan lama yang belum memiliki pemilik ikut dicatat.";
        }

        return back()->with('success', $message);
    }
}
