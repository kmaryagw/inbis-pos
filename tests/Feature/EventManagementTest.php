<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function createEvent(): Event
    {
        $this->actingAs($this->admin)->post(route('admin.events.store'), [
            'code' => 'uie26', 'name' => 'Expo', 'location' => 'Unud', 'person_in_charge' => 'PJ',
            'start_date' => '2026-11-06', 'end_date' => '2026-11-08',
        ])->assertRedirect();

        return Event::firstOrFail();
    }

    public function test_full_setup_flow_from_draft_to_active(): void
    {
        $event = $this->createEvent();
        $this->assertSame('UIE26', $event->code);
        $this->assertTrue($event->isDraft());

        // Belum bisa aktif tanpa produk & kasir
        $this->post(route('admin.events.activate', $event))->assertSessionHas('error');

        $product = Product::create(['code' => 'P1', 'name' => 'Kopi', 'unit' => 'pcs', 'is_active' => true]);
        $this->post(route('admin.event-products.store', $event), [
            'product_id' => $product->id, 'selling_price' => 25000, 'opening_stock' => 10, 'min_stock' => 2,
        ])->assertSessionHasNoErrors();

        $ep = $event->eventProducts()->first();
        $this->assertSame(10, $ep->current_stock);

        // Stok masuk dan keluar tercatat
        $this->post(route('admin.event-products.stock', [$event, $ep]), ['type' => 'in', 'quantity' => 5])->assertSessionHasNoErrors();
        $this->post(route('admin.event-products.stock', [$event, $ep]), ['type' => 'out', 'quantity' => 3])->assertSessionHasErrors('note');
        $this->post(route('admin.event-products.stock', [$event, $ep]), ['type' => 'out', 'quantity' => 3, 'note' => 'Rusak']);
        $this->assertSame(12, $ep->fresh()->current_stock);

        // Stok keluar melebihi tersedia ditolak
        $this->post(route('admin.event-products.stock', [$event, $ep]), ['type' => 'out', 'quantity' => 99, 'note' => 'x'])->assertSessionHas('error');
        $this->assertSame(12, $ep->fresh()->current_stock);

        $cashier = User::factory()->create();
        $this->put(route('admin.events.cashiers', $event), ['cashiers' => [$cashier->id]]);

        $this->post(route('admin.events.activate', $event))->assertSessionHas('success');
        $this->assertTrue($event->fresh()->isActive());

        $this->get(route('admin.events.show', $event))->assertOk()->assertSee('Kopi');
        $this->get(route('admin.event-products.history', [$event, $ep]))->assertOk()->assertSee('Rusak');

        $this->post(route('admin.events.close', $event));
        $this->assertTrue($event->fresh()->isClosed());
        $this->post(route('admin.event-products.stock', [$event, $ep]), ['type' => 'in', 'quantity' => 1])->assertForbidden();
    }

    public function test_same_product_can_have_different_price_per_event(): void
    {
        $product = Product::create(['code' => 'P1', 'name' => 'Kopi', 'unit' => 'pcs', 'is_active' => true]);
        $a = $this->createEvent();
        $b = Event::create(['code' => 'B', 'name' => 'B', 'location' => 'x', 'person_in_charge' => 'y', 'start_date' => today(), 'end_date' => today()]);

        $this->post(route('admin.event-products.store', $a), ['product_id' => $product->id, 'selling_price' => 25000, 'opening_stock' => 5]);
        $this->post(route('admin.event-products.store', $b), ['product_id' => $product->id, 'selling_price' => 30000, 'opening_stock' => 7]);

        $this->assertSame(25000, $a->eventProducts()->first()->selling_price);
        $this->assertSame(30000, $b->eventProducts()->first()->selling_price);
    }

    public function test_draft_event_without_transactions_can_be_deleted(): void
    {
        $event = $this->createEvent();
        $this->delete(route('admin.events.destroy', $event))->assertRedirect(route('admin.events.index'));
        $this->assertDatabaseCount('events', 0);
    }

    public function test_admin_pages_render(): void
    {
        $this->createEvent();
        foreach (['admin.events.index', 'admin.events.create', 'admin.products.index', 'admin.products.create',
                  'admin.categories.index', 'admin.users.index', 'admin.users.create', 'pos.index', 'account.password'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
