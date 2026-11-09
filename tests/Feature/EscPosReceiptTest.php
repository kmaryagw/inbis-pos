<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\EscPosReceipt;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EscPosReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_fits_58mm_and_contains_required_fields(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Kasir Dengan Nama Yang Sangat Panjang Sekali']);
        $event = new Event(['code' => 'UIE26', 'name' => 'Udayana Innovation Expo 2026', 'location' => 'Unud',
            'start_date' => today(), 'end_date' => today(), 'person_in_charge' => 'PJ']);
        $event->status = Event::ACTIVE;
        $event->save();

        $product = Product::create(['code' => 'KPI', 'name' => 'Kopi Kintamani Arabika Premium Café Édition 200 gram', 'unit' => 'pcs', 'is_active' => true]);
        $ep = $event->eventProducts()->create(['product_id' => $product->id, 'selling_price' => 125000, 'is_active' => true]);
        $ep->setRelation('product', $product);
        app(StockService::class)->move($ep, StockMovement::OPENING, 50, $admin);

        $trx = app(CheckoutService::class)->checkout($event, $admin, [$ep->id => 12], 'cash', 1_600_000, (string) Str::uuid());

        $bytes = app(EscPosReceipt::class)->build($trx);
        $this->assertStringStartsWith("\x1B@", $bytes);

        // Buang perintah ESC/POS, sisakan teks yang tercetak
        $text = preg_replace('/\x1B[@]|\x1B[aEd].|\x1D!./s', '', $bytes);
        $lines = explode("\n", rtrim($text, "\n"));

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(32, strlen($line), "Baris melebihi 32 karakter: [$line]");
            $this->assertMatchesRegularExpression('/^[\x20-\x7E]*$/', $line, 'Hanya ASCII yang boleh dikirim ke printer');
        }

        $this->assertStringContainsString('No: '.$trx->number, $text);
        $this->assertStringContainsString('Cafe Edition', $text);          // aksen diubah ke ASCII
        $this->assertStringContainsString('12 x 125.000', $text);
        $this->assertMatchesRegularExpression('/TOTAL\s+Rp1\.500\.000/', $text);
        $this->assertMatchesRegularExpression('/KEMBALI\s+Rp100\.000/', $text);
        $this->assertStringContainsString('Metode: TUNAI', $text);

        $this->actingAs($admin)->get(route('receipts.show', $trx))->assertOk()->assertSee('Cetak via RawBT');
    }
}
