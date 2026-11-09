<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventProductController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    /** ST-01..03: alokasikan produk master ke kegiatan beserta harga dan stok awal. */
    public function store(Request $request, Event $event)
    {
        $this->ensureEditable($event);

        $data = $request->validate([
            'product_id' => [
                'required',
                Rule::exists('products', 'id')->where('is_active', true),
                Rule::unique('event_products')->where('event_id', $event->id),
            ],
            'selling_price' => ['required', 'integer', 'min:0'],
            'opening_stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
        ], ['product_id.unique' => 'Produk ini sudah dialokasikan ke kegiatan.']);

        DB::transaction(function () use ($event, $data, $request) {
            $ep = $event->eventProducts()->create([
                'product_id' => $data['product_id'],
                'selling_price' => $data['selling_price'],
                'min_stock' => $data['min_stock'] ?? 0,
                'is_active' => true,
            ]);
            $ep->setRelation('product', Product::find($data['product_id']));

            if ($data['opening_stock'] > 0) {
                $this->stock->move($ep, StockMovement::OPENING, $data['opening_stock'], $request->user(), 'Stok awal');
            }
        });

        return back()->with('success', 'Produk dialokasikan ke kegiatan.');
    }

    public function update(Request $request, Event $event, EventProduct $eventProduct)
    {
        $this->ensureEditable($event);

        $data = $request->validate([
            'selling_price' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'opening_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($event, $eventProduct, $data, $request) {
            $ep = EventProduct::with('product')->lockForUpdate()->findOrFail($eventProduct->id);
            $ep->fill([
                'selling_price' => $data['selling_price'],
                'min_stock' => $data['min_stock'] ?? 0,
                'is_active' => $request->boolean('is_active'),
            ])->save();

            // Koreksi stok awal hanya saat Draft; tercatat sebagai pergerakan selisih
            if ($event->isDraft() && isset($data['opening_stock']) && (int) $data['opening_stock'] !== $ep->opening_stock) {
                $this->stock->move(
                    $ep, StockMovement::OPENING, (int) $data['opening_stock'] - $ep->opening_stock,
                    $request->user(), 'Koreksi stok awal',
                );
            }
        });

        return back()->with('success', 'Produk kegiatan diperbarui.');
    }

    /** ST-04 / ST-05: stok masuk atau stok keluar/penyesuaian dengan alasan. */
    public function adjustStock(Request $request, Event $event, EventProduct $eventProduct)
    {
        $this->ensureEditable($event);

        $data = $request->validate([
            'type' => ['required', Rule::in([StockMovement::IN, StockMovement::OUT])],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['required_if:type,out', 'nullable', 'string', 'max:255'],
        ], ['note.required_if' => 'Alasan wajib diisi untuk stok keluar/penyesuaian.']);

        $quantity = $data['type'] === StockMovement::OUT ? -$data['quantity'] : $data['quantity'];

        $this->stock->moveLocked($eventProduct, $data['type'], $quantity, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Pergerakan stok dicatat.');
    }

    /** ST-07 */
    public function history(Event $event, EventProduct $eventProduct)
    {
        $eventProduct->load('product');
        $movements = $eventProduct->movements()->with('user')->latest('id')->paginate(50);

        return view('admin.events.history', compact('event', 'eventProduct', 'movements'));
    }

    private function ensureEditable(Event $event): void
    {
        abort_if($event->isClosed(), 403, 'Kegiatan sudah selesai; stok dan harga tidak dapat diubah.');
    }
}
