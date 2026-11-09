<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\StockService;
use App\Services\VoidService;
use Illuminate\Support\Str;

/** Data contoh untuk uji coba: php artisan db:seed --class=DemoSeeder */
class DemoSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        parent::run();
        $stock = app(StockService::class);
        $admin = User::where('username', 'admin')->first();

        $cashier = $this->seedUser('kasir1', 'Kasir Satu', User::ROLE_CASHIER);
        $this->seedUser('pimpinan', 'Pimpinan INBIS', User::ROLE_MANAGER);

        $categories = collect(['Makanan', 'Minuman', 'Kerajinan', 'Fashion'])
            ->mapWithKeys(fn ($name) => [$name => Category::firstOrCreate(['name' => $name])]);

        $products = [
            ['KPI-001', 'Kopi Kintamani 200g', 'Makanan', 'pack', 65000],
            ['KPI-002', 'Kopi Kintamani Drip Bag', 'Minuman', 'box', 45000],
            ['MDU-001', 'Madu Trigona 250ml', 'Minuman', 'botol', 85000],
            ['KRP-001', 'Keripik Singkong Pedas', 'Makanan', 'pcs', 15000],
            ['KRP-002', 'Keripik Pisang Manis', 'Makanan', 'pcs', 15000],
            ['TEH-001', 'Teh Rosella Kering', 'Minuman', 'pack', 30000],
            ['KRJ-001', 'Gantungan Kunci Barong', 'Kerajinan', 'pcs', 10000],
            ['KRJ-002', 'Tas Anyaman Ate', 'Kerajinan', 'pcs', 175000],
            ['FSH-001', 'Kaos Unud Innovation', 'Fashion', 'pcs', 95000],
            ['FSH-002', 'Udeng Batik', 'Fashion', 'pcs', 50000],
            ['SBN-001', 'Sabun Herbal Sereh', 'Kerajinan', 'pcs', 20000],
            ['MNY-001', 'Minyak Kelapa VCO 100ml', 'Minuman', 'botol', 40000],
        ];

        $event = Event::firstOrNew(['code' => 'UIE26']);
        if (! $event->exists) {
            $event->fill([
                'name' => 'Udayana Innovation Expo 2026',
                'location' => 'Gedung Agrokompleks, Kampus Sudirman',
                'start_date' => today(),
                'end_date' => today()->addDays(2),
                'person_in_charge' => 'Tim INBIS',
            ]);
            $event->status = Event::DRAFT;
            $event->save();
        }

        foreach ($products as $i => [$code, $name, $cat, $unit, $price]) {
            $product = Product::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'category_id' => $categories[$cat]->id, 'unit' => $unit, 'is_active' => true],
            );

            $ep = $event->eventProducts()->firstOrCreate(
                ['product_id' => $product->id],
                ['selling_price' => $price, 'min_stock' => 3, 'is_active' => true],
            );
            $ep->setRelation('product', $product);

            if ($ep->wasRecentlyCreated) {
                $stock->move($ep, StockMovement::OPENING, [20, 15, 10, 40, 40, 25, 50, 5, 12, 20, 30, 2][$i], $admin, 'Stok awal');
            }
        }

        $event->cashiers()->syncWithoutDetaching([$cashier->id]);

        if ($event->isDraft()) {
            $event->forceFill(['status' => Event::ACTIVE, 'activated_at' => now()])->save();
        }

        $this->sampleTransactions($event, $cashier, $admin);
    }

    /** Beberapa transaksi contoh (termasuk satu pembatalan) agar dashboard & laporan berisi data. */
    private function sampleTransactions(Event $event, User $cashier, User $admin): void
    {
        if ($event->transactions()->exists()) {
            return;
        }

        $checkout = app(CheckoutService::class);
        $ids = $event->eventProducts()->where('current_stock', '>', 5)->pluck('id')->all();
        mt_srand(2026);

        for ($i = 0; $i < 14; $i++) {
            $items = [];
            foreach ((array) array_rand(array_flip($ids), mt_rand(1, 3)) as $id) {
                $items[$id] = mt_rand(1, 2);
            }
            $trx = $checkout->checkout($event, $i % 3 ? $cashier : $admin, $items, $i % 4 ? Transaction::CASH : Transaction::QRIS, 500000, (string) Str::uuid());
            // Sebar waktu transaksi selama hari ini agar riwayat terlihat realistis
            $trx->forceFill(['transacted_at' => today()->setTime(9, 0)->addMinutes($i * 37)])->save();
        }

        app(VoidService::class)->void($trx, $admin, 'Contoh pembatalan: pembeli batal membeli');
    }
}
