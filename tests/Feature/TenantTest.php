<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\StockService;
use App\Services\VoidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private Event $event;
    private Tenant $dosen;
    private Tenant $umkm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->cashier = User::factory()->create();

        $this->event = new Event(['code' => 'UIE26', 'name' => 'Expo', 'location' => 'Unud', 'start_date' => today(), 'end_date' => today(), 'person_in_charge' => 'PJ']);
        $this->event->status = Event::ACTIVE;
        $this->event->save();
        $this->event->cashiers()->attach($this->cashier);

        $this->dosen = Tenant::create(['name' => 'Dr. Dosen', 'type' => Tenant::DOSEN, 'is_active' => true]);
        $this->umkm = Tenant::create(['name' => 'UMKM Kriya', 'type' => Tenant::TENANT, 'is_active' => true]);
    }

    private function allocate(string $name, int $price, int $stock, ?Tenant $owner): EventProduct
    {
        $product = Product::create(['name' => $name, 'unit' => 'pcs', 'is_active' => true, 'tenant_id' => $owner?->id]);
        $ep = $this->event->eventProducts()->create(['product_id' => $product->id, 'selling_price' => $price, 'min_stock' => 0, 'is_active' => true]);
        $ep->setRelation('product', $product);
        app(StockService::class)->move($ep, StockMovement::OPENING, $stock, $this->admin);

        return $ep->fresh();
    }

    private function sell(array $items): Transaction
    {
        return app(CheckoutService::class)->checkout($this->event, $this->cashier, $items, 'cash', 1_000_000, (string) Str::uuid());
    }

    public function test_admin_manages_tenants_with_generated_code(): void
    {
        $this->actingAs($this->admin)->post(route('admin.tenants.store'), [
            'name' => 'Dr. Baru', 'type' => 'dosen', 'unit' => 'FTP', 'email' => 'x@example.test', 'is_active' => 1,
        ])->assertRedirect();

        $t = Tenant::where('name', 'Dr. Baru')->firstOrFail();
        $this->assertSame(Tenant::codeFor($t->id), $t->code);

        $this->put(route('admin.tenants.update', $t), ['name' => 'Dr. Baru, M.T.', 'type' => 'dosen', 'bank_name' => 'BPD Bali', 'bank_account_number' => '123'])
            ->assertRedirect(route('admin.tenants.show', $t));
        $this->assertFalse($t->fresh()->is_active);
        $this->assertSame('BPD Bali 123', $t->fresh()->bankAccount());

        foreach (['admin.tenants.index', 'admin.tenants.create', 'admin.ownership.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.tenants.show', $t))->assertOk()->assertSee('Dr. Baru, M.T.');
    }

    public function test_cashier_and_manager_cannot_manage_tenants(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        foreach ([$this->cashier, $manager] as $user) {
            $this->actingAs($user)->get(route('admin.tenants.index'))->assertForbidden();
            $this->actingAs($user)->put(route('admin.ownership.update'), ['products' => [1], 'tenant_id' => $this->dosen->id])->assertForbidden();
        }
    }

    public function test_sale_records_owner_snapshot_and_ownership_change_keeps_history(): void
    {
        $kopi = $this->allocate('Kopi', 25000, 10, $this->dosen);
        $this->sell([$kopi->id => 2]);

        // Kepemilikan dipindah: penjualan lama tetap milik dosen, penjualan baru milik UMKM
        $this->actingAs($this->admin)->put(route('admin.ownership.update'), [
            'products' => [$kopi->product_id], 'tenant_id' => $this->umkm->id,
        ])->assertSessionHas('success');
        $this->sell([$kopi->id => 1]);

        $this->assertDatabaseHas('transaction_details', ['tenant_id' => $this->dosen->id, 'quantity' => 2]);
        $this->assertDatabaseHas('transaction_details', ['tenant_id' => $this->umkm->id, 'quantity' => 1]);

        $detail = app(\App\Services\ReportService::class)->eventTenantDetail($this->event, new \App\Support\ReportFilters(eventId: $this->event->id));
        $byTenant = $detail->groups->keyBy(fn ($g) => $g->tenant?->id);
        $this->assertSame(50000, $byTenant[$this->dosen->id]->total);
        $this->assertSame(25000, $byTenant[$this->umkm->id]->total);
        // Sisa stok hanya dihitung sekali, pada pemilik saat ini
        $this->assertSame(0, $byTenant[$this->dosen->id]->available);
        $this->assertSame(7, $byTenant[$this->umkm->id]->available);
    }

    public function test_assigning_owner_backfills_unowned_past_sales(): void
    {
        $madu = $this->allocate('Madu', 15000, 10, null);
        $this->sell([$madu->id => 3]);
        $this->assertDatabaseHas('transaction_details', ['tenant_id' => null]);

        $this->actingAs($this->admin)->put(route('admin.ownership.update'), [
            'products' => [$madu->product_id], 'tenant_id' => $this->dosen->id,
        ])->assertSessionHas('success');

        $this->assertDatabaseMissing('transaction_details', ['tenant_id' => null]);
        $this->assertSame($this->dosen->id, $madu->product->fresh()->tenant_id);

        // Tenant tidak aktif tidak dapat diberi produk
        $this->umkm->update(['is_active' => false]);
        $this->put(route('admin.ownership.update'), ['products' => [$madu->product_id], 'tenant_id' => $this->umkm->id])->assertSessionHas('error');
        $this->assertSame($this->dosen->id, $madu->product->fresh()->tenant_id);
    }

    public function test_event_report_groups_sales_per_tenant_and_excludes_void(): void
    {
        $kopi = $this->allocate('Kopi', 25000, 10, $this->dosen);
        $tas = $this->allocate('Tas', 100000, 5, $this->umkm);
        $vco = $this->allocate('VCO', 40000, 5, null);
        $this->sell([$kopi->id => 2, $tas->id => 1]);
        $void = $this->sell([$tas->id => 2]);
        app(VoidService::class)->void($void, $this->admin, 'Salah input');

        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $this->actingAs($manager)->get(route('reports.events.index'))->assertOk()->assertSee('Expo');
        $this->get(route('reports.events.show', $this->event))
            ->assertOk()
            ->assertSeeInOrder(['Rekap per tenant', 'UMKM Kriya', 'Dr. Dosen', 'Tanpa pemilik'])
            ->assertSee('Rp100.000')
            ->assertSee('Rp50.000');

        $this->get(route('reports.events.tenant', [$this->event, $this->umkm]))
            ->assertOk()->assertSee('REKAP PENJUALAN')->assertSee('Tas')->assertDontSee('Kopi');

        $this->get(route('reports.index', ['tab' => 'tenants', 'event' => $this->event->id]))
            ->assertOk()->assertSee('UMKM Kriya')->assertSee('Dr. Dosen');

        $this->get(route('reports.events.show', [$this->event, 'from' => today()->addDay()->toDateString()]))
            ->assertOk()->assertSee('Belum ada penjualan');

        // Ekspor Excel
        $response = $this->get(route('reports.events.export', $this->event))->assertOk();
        $reader = new Reader();
        $reader->open($response->baseResponse->getFile()->getPathname());
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator(), false))->map(fn ($r) => $r->toArray())->all();
        }
        $reader->close();

        $this->assertSame(['Ringkasan', 'Per Tenant', 'Detail Produk', 'Transaksi'], array_keys($sheets));
        $perTenant = collect($sheets['Per Tenant'])->keyBy(1);
        $this->assertEquals(100000, $perTenant['UMKM Kriya'][8]);
        $this->assertEquals(50000, $perTenant['Dr. Dosen'][8]);
        $this->assertEquals(0, $perTenant['Tanpa pemilik'][8]);
        $this->assertEquals(150000, $perTenant['TOTAL'][8]);
    }

    public function test_product_form_saves_owner(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'name' => 'Kopi', 'unit' => 'pcs', 'tenant_id' => $this->dosen->id, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame($this->dosen->id, Product::firstOrFail()->tenant_id);
        $this->get(route('admin.products.index', ['owner' => $this->dosen->id]))->assertOk()->assertSee('Kopi');
    }
}
