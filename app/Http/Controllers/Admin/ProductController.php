<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('category')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")))
            ->when($request->category, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $categories = Category::orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product(['is_active' => true, 'unit' => 'pcs']),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['photo'] = $request->file('photo')?->store('products', 'public');

        $product = Product::create($data);

        return redirect()->route('admin.products.show', $product)->with('success', "Produk berhasil ditambahkan dengan kode {$product->code}.");
    }

    public function show(Product $product)
    {
        $product->load('category');

        // Alokasi per kegiatan beserta penjualan (hanya transaksi berhasil)
        $allocations = $product->eventProducts()
            ->with('event:id,name,code,status,start_date,end_date')
            ->withSum(['transactionDetails as units_sold' => fn ($q) => $q->whereHas('transaction', fn ($t) => $t->where('status', Transaction::PAID))], 'quantity')
            ->withSum(['transactionDetails as revenue' => fn ($q) => $q->whereHas('transaction', fn ($t) => $t->where('status', Transaction::PAID))], 'subtotal')
            ->get()
            ->sortByDesc(fn ($ep) => $ep->event->start_date)
            ->values();

        return view('admin.products.show', [
            'product' => $product,
            'allocations' => $allocations,
            'totals' => (object) [
                'units' => (int) $allocations->sum('units_sold'),
                'revenue' => (int) $allocations->sum('revenue'),
                'stock' => (int) $allocations->filter(fn ($ep) => $ep->event->isActive())->sum('current_stock'),
            ],
        ]);
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::where('is_active', true)->orWhere('id', $product->category_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);

        if ($request->hasFile('photo') || $request->boolean('remove_photo')) {
            if ($product->photo) {
                Storage::disk('public')->delete($product->photo);
            }
            $data['photo'] = $request->file('photo')?->store('products', 'public');
        }

        $product->update($data);

        return redirect()->route('admin.products.show', $product)->with('success', 'Produk berhasil diperbarui.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', 'string', 'max:30'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($data['photo']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
