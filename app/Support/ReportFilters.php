<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Filter laporan & riwayat transaksi (RP-06): kegiatan, tanggal, kasir, metode, produk, nomor. */
class ReportFilters
{
    public function __construct(
        public readonly ?int $eventId = null,
        public readonly ?CarbonImmutable $from = null,
        public readonly ?CarbonImmutable $to = null,
        public readonly ?int $cashierId = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?int $productId = null,
        public readonly ?string $number = null,
        /** Batas kegiatan yang boleh dilihat (null = semua). */
        public readonly ?array $allowedEventIds = null,
    ) {
    }

    public static function fromRequest(Request $request, User $user): self
    {
        $data = $request->validate([
            'event' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'cashier' => ['nullable', 'integer'],
            'method' => ['nullable', 'in:'.implode(',', array_keys(Transaction::PAYMENT_METHODS))],
            'product' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:40'],
        ]);

        $allowed = $user->isCashier() ? $user->visibleEvents()->pluck('id')->all() : null;
        $eventId = isset($data['event']) ? (int) $data['event'] : null;

        if ($eventId !== null && $allowed !== null && ! in_array($eventId, $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke kegiatan ini.');
        }

        return new self(
            eventId: $eventId,
            from: isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : null,
            to: isset($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : null,
            cashierId: isset($data['cashier']) ? (int) $data['cashier'] : null,
            paymentMethod: $data['method'] ?? null,
            productId: isset($data['product']) ? (int) $data['product'] : null,
            number: $data['q'] ?? null,
            allowedEventIds: $allowed,
        );
    }

    /**
     * Terapkan filter ke query yang memuat tabel transaksi.
     * $t adalah nama/alias tabel transaksi, mis. 'transactions' atau 't'.
     */
    public function apply($query, string $t = 'transactions')
    {
        return $query
            ->when($this->allowedEventIds !== null, fn ($q) => $q->whereIn("$t.event_id", $this->allowedEventIds))
            ->when($this->eventId, fn ($q, $id) => $q->where("$t.event_id", $id))
            ->when($this->from, fn ($q, $d) => $q->where("$t.transacted_at", '>=', $d))
            ->when($this->to, fn ($q, $d) => $q->where("$t.transacted_at", '<=', $d))
            ->when($this->cashierId, fn ($q, $id) => $q->where("$t.cashier_id", $id))
            ->when($this->paymentMethod, fn ($q, $m) => $q->where("$t.payment_method", $m))
            ->when($this->number, fn ($q, $n) => $q->where("$t.number", 'like', "%{$n}%"))
            ->when($this->productId, fn ($q, $pid) => $q->whereExists(fn ($s) => $s
                ->selectRaw('1')
                ->from('transaction_details as fd')
                ->join('event_products as fep', 'fep.id', '=', 'fd.event_product_id')
                ->whereColumn('fd.transaction_id', "$t.id")
                ->where('fep.product_id', $pid)));
    }

    public function event(): ?Event
    {
        return $this->eventId ? Event::find($this->eventId) : null;
    }

    /** Parameter query string untuk tautan/ekspor dengan filter yang sama. */
    public function query(): array
    {
        return array_filter([
            'event' => $this->eventId,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'cashier' => $this->cashierId,
            'method' => $this->paymentMethod,
            'product' => $this->productId,
            'q' => $this->number,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Uraian filter untuk judul laporan cetak/ekspor. */
    public function describe(): string
    {
        $parts = [$this->event()?->name ?? 'Semua kegiatan'];

        if ($this->from || $this->to) {
            $parts[] = ($this->from?->format('d/m/Y') ?? '…').' – '.($this->to?->format('d/m/Y') ?? '…');
        }
        if ($this->cashierId) {
            $parts[] = 'Kasir: '.(User::find($this->cashierId)?->name ?? '-');
        }
        if ($this->paymentMethod) {
            $parts[] = 'Metode: '.Transaction::PAYMENT_METHODS[$this->paymentMethod];
        }
        if ($this->productId) {
            $parts[] = 'Produk: '.(\App\Models\Product::find($this->productId)?->name ?? '-');
        }

        return implode(' · ', $parts);
    }
}
