<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class TransactionAndReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private Event $event;
    private EventProduct $kopi;
    private EventProduct $madu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->cashier = User::factory()->create();

        $this->event = new Event([
            'code' => 'UIE26', 'name' => 'Expo', 'location' => 'Unud',
            'start_date' => today(), 'end_date' => today(), 'person_in_charge' => 'PJ',
        ]);
        $this->event->status = Event::ACTIVE;
        $this->event->save();
        $this->event->cashiers()->attach($this->cashier);

        $this->kopi = $this->allocate('KPI', 'Kopi', 25000, 10);
        $this->madu = $this->allocate('MDU', 'Madu', 15000, 5);
    }

    private function allocate(string $code, string $name, int $price, int $stock): EventProduct
    {
        $product = Product::create(['code' => $code, 'name' => $name, 'unit' => 'pcs', 'is_active' => true]);
        $ep = $this->event->eventProducts()->create(['product_id' => $product->id, 'selling_price' => $price, 'min_stock' => 3, 'is_active' => true]);
        $ep->setRelation('product', $product);
        app(StockService::class)->move($ep, StockMovement::OPENING, $stock, $this->admin);

        return $ep->fresh();
    }

    private function sell(array $items, string $method = 'cash', ?User $as = null): Transaction
    {
        return app(CheckoutService::class)->checkout(
            $this->event, $as ?? $this->cashier, $items, $method, 1_000_000, (string) Str::uuid(),
        );
    }

    public function test_admin_can_void_transaction_and_stock_is_restored(): void
    {
        $trx = $this->sell([$this->kopi->id => 3]);
        $this->assertSame(7, $this->kopi->fresh()->current_stock);

        $this->actingAs($this->admin)
            ->post(route('transactions.void', $trx), ['reason' => 'Salah input jumlah'])
            ->assertRedirect(route('transactions.show', $trx));

        $trx->refresh();
        $this->assertTrue($trx->isVoid());
        $this->assertSame($this->admin->id, $trx->voided_by);
        $this->assertSame('Salah input jumlah', $trx->void_reason);
        $this->assertSame(10, $this->kopi->fresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', ['event_product_id' => $this->kopi->id, 'type' => 'void', 'quantity' => 3]);

        // Tidak bisa dibatalkan dua kali
        $this->post(route('transactions.void', $trx), ['reason' => 'Ulang lagi'])->assertSessionHas('error');
        $this->assertSame(10, $this->kopi->fresh()->current_stock);
    }

    public function test_void_requires_reason_and_admin_role(): void
    {
        $trx = $this->sell([$this->kopi->id => 1]);

        $this->actingAs($this->admin)->post(route('transactions.void', $trx), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->cashier)->post(route('transactions.void', $trx), ['reason' => 'Coba batalkan'])->assertForbidden();

        $this->assertFalse($trx->fresh()->isVoid());
    }

    public function test_voided_number_is_not_reused(): void
    {
        $first = $this->sell([$this->kopi->id => 1]);
        $this->actingAs($this->admin)->post(route('transactions.void', $first), ['reason' => 'Batal beli']);
        $second = $this->sell([$this->kopi->id => 1]);

        $this->assertNotSame($first->number, $second->number);
        $this->assertStringEndsWith('-0002', $second->number);
    }

    public function test_reports_count_only_paid_transactions(): void
    {
        $this->sell([$this->kopi->id => 2, $this->madu->id => 1]);          // 65.000 tunai
        $this->sell([$this->madu->id => 2], 'qris');                         // 30.000 QRIS
        $void = $this->sell([$this->kopi->id => 4]);                         // 100.000, dibatalkan
        $this->actingAs($this->admin)->post(route('transactions.void', $void), ['reason' => 'Pembeli batal']);

        $this->get(route('dashboard', ['event' => $this->event->id]))
            ->assertOk()
            ->assertSee('Rp95.000')     // total pendapatan
            ->assertSee('Rp65.000')     // tunai
            ->assertSee('Rp30.000');    // QRIS

        $this->get(route('reports.index', ['event' => $this->event->id, 'tab' => 'products']))
            ->assertOk()->assertSee('Kopi')->assertSee('Rp50.000')->assertSee('Rp45.000');

        $this->get(route('reports.index', ['event' => $this->event->id, 'tab' => 'sales']))
            ->assertOk()->assertSee($void->number)->assertSee('Pembeli batal');

        // Stok: kopi 10 - 2 = 8 (penjualan yang dibatalkan sudah kembali), madu 5 - 3 = 2 (menipis)
        $this->get(route('reports.index', ['event' => $this->event->id, 'tab' => 'stock']))
            ->assertOk()->assertSee('Menipis');

        $summary = app(\App\Services\ReportService::class)->summary(
            new \App\Support\ReportFilters(eventId: $this->event->id)
        );
        $this->assertSame(2, $summary['count']);
        $this->assertSame(95000, $summary['revenue']);
        $this->assertSame(5, $summary['units']);
        $this->assertSame(1, $summary['void_count']);
        $this->assertSame(1, $summary['low_stock']);
    }

    public function test_filters_by_payment_method_and_product(): void
    {
        $this->sell([$this->kopi->id => 1]);
        $this->sell([$this->madu->id => 1], 'qris');

        $reports = app(\App\Services\ReportService::class);
        $this->assertSame(15000, $reports->summary(new \App\Support\ReportFilters(paymentMethod: 'qris'))['revenue']);

        $byProduct = $reports->productSales(new \App\Support\ReportFilters(productId: $this->kopi->product_id));
        $this->assertCount(1, $byProduct);
        $this->assertSame('Kopi', $byProduct->first()->name);
    }

    public function test_excel_export_contains_all_sheets(): void
    {
        $this->sell([$this->kopi->id => 2]);

        $res = $this->actingAs($this->admin)->get(route('reports.export', ['event' => $this->event->id]))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $res->headers->get('Content-Type'));

        $path = $res->getFile()->getPathname();
        $reader = new Reader();
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $sheets[$sheet->getName()] = $rows;
        }
        $reader->close();

        $this->assertSame(['Ringkasan', 'Transaksi', 'Per Produk', 'Stok', 'Dibatalkan'], array_keys($sheets));
        $this->assertSame(50000, (int) $sheets['Transaksi'][1][5]);
        $this->assertSame('Kopi', $sheets['Per Produk'][1][1]);
    }

    public function test_cashier_sees_only_assigned_event_transactions(): void
    {
        $this->sell([$this->kopi->id => 1]);

        $other = new Event(['code' => 'OTH', 'name' => 'Lain', 'location' => 'x', 'start_date' => today(), 'end_date' => today(), 'person_in_charge' => 'y']);
        $other->status = Event::ACTIVE;
        $other->save();

        $this->actingAs($this->cashier)->get(route('transactions.index'))->assertOk()->assertSee('POS-UIE26');
        $this->actingAs($this->cashier)->get(route('transactions.index', ['event' => $other->id]))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('reports.index'))->assertForbidden();
    }

    public function test_manager_is_read_only(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $trx = $this->sell([$this->kopi->id => 1]);

        $this->actingAs($manager)->get('/')->assertRedirect(route('dashboard'));
        $this->get(route('dashboard', ['event' => $this->event->id]))->assertOk();
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('transactions.show', $trx))->assertOk()->assertDontSee('Batalkan transaksi');
        $this->get(route('receipts.show', $trx))->assertOk();

        $this->get(route('pos.show', $this->event))->assertForbidden();
        $this->get(route('admin.events.index'))->assertForbidden();
        $this->post(route('transactions.void', $trx), ['reason' => 'Coba batalkan'])->assertForbidden();
    }

    public function test_pages_render(): void
    {
        $trx = $this->sell([$this->kopi->id => 1]);
        $this->actingAs($this->admin);

        // Hanya satu kegiatan aktif: dashboard langsung memilih kegiatan itu
        $this->get(route('dashboard'))->assertRedirect(route('dashboard', ['event' => $this->event->id]));
        $this->get(route('dashboard', ['event' => '']))->assertOk()->assertSee('Pendapatan per kegiatan');
        foreach (['sales', 'products', 'payments', 'stock'] as $tab) {
            $this->get(route('reports.index', ['tab' => $tab]))->assertOk();
        }
        $this->get(route('transactions.index', ['method' => 'cash', 'status' => 'paid', 'q' => 'UIE']))->assertOk()->assertSee($trx->number);
        $this->get(route('transactions.show', $trx))->assertOk()->assertSee('Batalkan transaksi');
    }
}
