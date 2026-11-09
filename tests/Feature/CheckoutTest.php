<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Event $event;
    private EventProduct $kopi;
    private EventProduct $madu;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->admin()->create();
        $this->cashier = User::factory()->create();

        $this->event = new Event([
            'code' => 'UIE26', 'name' => 'Expo', 'location' => 'Unud',
            'start_date' => today(), 'end_date' => today(), 'person_in_charge' => 'PJ',
        ]);
        $this->event->status = Event::ACTIVE;
        $this->event->save();
        $this->event->cashiers()->attach($this->cashier);

        $this->kopi = $this->allocate('KPI', 'Kopi', 25000, 10, $admin);
        $this->madu = $this->allocate('MDU', 'Madu', 15000, 2, $admin);
    }

    private function allocate(string $code, string $name, int $price, int $stock, User $by): EventProduct
    {
        $product = Product::create(['code' => $code, 'name' => $name, 'unit' => 'pcs', 'is_active' => true]);
        $ep = $this->event->eventProducts()->create(['product_id' => $product->id, 'selling_price' => $price, 'is_active' => true]);
        $ep->setRelation('product', $product);
        app(StockService::class)->move($ep, StockMovement::OPENING, $stock, $by);

        return $ep->fresh();
    }

    private function checkout(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->cashier)->postJson(route('pos.checkout', $this->event), $overrides + [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['id' => $this->kopi->id, 'qty' => 2], ['id' => $this->madu->id, 'qty' => 1]],
            'payment_method' => 'cash',
            'amount_paid' => 100000,
        ]);
    }

    public function test_cash_sale_saves_transaction_reduces_stock_and_computes_change(): void
    {
        $res = $this->checkout()->assertOk();

        $res->assertJsonPath('transaction.total', 65000)
            ->assertJsonPath('transaction.change', 35000)
            ->assertJsonPath('transaction.number', 'POS-UIE26-'.now()->format('Ymd').'-0001');

        $this->assertSame(8, $this->kopi->fresh()->current_stock);
        $this->assertSame(1, $this->madu->fresh()->current_stock);
        $this->assertDatabaseCount('transaction_details', 2);
        $this->assertDatabaseHas('stock_movements', ['event_product_id' => $this->kopi->id, 'type' => 'sale', 'quantity' => -2, 'balance_after' => 8]);
    }

    public function test_transaction_numbers_are_sequential(): void
    {
        $this->checkout(['items' => [['id' => $this->kopi->id, 'qty' => 1]]])->assertOk();
        $this->checkout(['items' => [['id' => $this->kopi->id, 'qty' => 1]]])
            ->assertJsonPath('transaction.number', 'POS-UIE26-'.now()->format('Ymd').'-0002');
    }

    public function test_insufficient_stock_is_rejected_without_side_effects(): void
    {
        $this->checkout(['items' => [['id' => $this->madu->id, 'qty' => 3]]])
            ->assertStatus(422)
            ->assertJsonPath('stock.'.$this->madu->id, 2);

        $this->assertSame(2, $this->madu->fresh()->current_stock);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_cash_less_than_total_is_rejected(): void
    {
        $this->checkout(['amount_paid' => 60000])->assertStatus(422);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(10, $this->kopi->fresh()->current_stock);
    }

    public function test_qris_records_total_as_paid_without_change(): void
    {
        $this->checkout(['payment_method' => 'qris', 'amount_paid' => null])
            ->assertOk()
            ->assertJsonPath('transaction.amount_paid', 65000)
            ->assertJsonPath('transaction.change', 0)
            ->assertJsonPath('transaction.payment_method', 'QRIS');
    }

    public function test_same_idempotency_key_does_not_create_duplicate(): void
    {
        $key = (string) Str::uuid();

        $first = $this->checkout(['idempotency_key' => $key])->assertOk();
        $second = $this->checkout(['idempotency_key' => $key])->assertOk();

        $this->assertSame($first->json('transaction.id'), $second->json('transaction.id'));
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame(8, $this->kopi->fresh()->current_stock);
    }

    public function test_changed_price_is_detected_via_expected_total(): void
    {
        $this->checkout(['expected_total' => 50000])->assertStatus(422);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_closed_event_rejects_sales(): void
    {
        $this->event->forceFill(['status' => Event::CLOSED])->save();

        $this->checkout()->assertForbidden();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_unassigned_cashier_cannot_sell(): void
    {
        $this->checkout(as: User::factory()->create())->assertForbidden();
    }

    public function test_product_from_another_event_is_rejected(): void
    {
        $other = new Event(['code' => 'OTH', 'name' => 'Other', 'location' => 'X', 'start_date' => today(), 'end_date' => today(), 'person_in_charge' => 'PJ']);
        $other->status = Event::ACTIVE;
        $other->save();
        $foreign = $other->eventProducts()->create(['product_id' => $this->kopi->product_id, 'selling_price' => 1000, 'is_active' => true]);

        $this->checkout(['items' => [['id' => $foreign->id, 'qty' => 1]]])->assertStatus(422);
    }

    public function test_receipt_shows_snapshot_after_master_product_renamed(): void
    {
        $id = $this->checkout()->json('transaction.id');
        $this->kopi->product->update(['name' => 'Kopi Baru']);

        $this->actingAs($this->cashier)->get(route('receipts.show', $id))
            ->assertOk()
            ->assertSee('Kopi')
            ->assertDontSee('Kopi Baru')
            ->assertSee('Rp65.000');
    }

    public function test_cashier_cannot_access_admin_pages(): void
    {
        $this->actingAs($this->cashier)->get(route('admin.events.index'))->assertForbidden();
    }

    public function test_pos_page_renders_for_assigned_cashier(): void
    {
        $this->actingAs($this->cashier)->get(route('pos.show', $this->event))
            ->assertOk()
            ->assertSee('Expo')
            ->assertSee('Simpan pembayaran');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->cashier->update(['is_active' => false]);

        $this->post('/login', ['username' => $this->cashier->username, 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
