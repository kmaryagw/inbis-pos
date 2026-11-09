@extends('layouts.app')
@section('title', $event->name)

@php
    $editable = ! $event->isClosed();
    $lowCount = $event->eventProducts->where('is_active', true)->filter->isLowStock()->count();
@endphp

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mb-3">
    <div>
        <a href="{{ route('admin.events.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Kegiatan</a>
        <h1 class="page-title mt-1">{{ $event->name }} <span class="badge {{ $event->statusBadgeClass() }} align-middle fs-6">{{ $event->statusLabel() }}</span></h1>
        <div class="text-muted small">
            {{ $event->code }} · {{ $event->location }} · {{ $event->start_date->format('d/m/Y') }} – {{ $event->end_date->format('d/m/Y') }} · PJ: {{ $event->person_in_charge }}
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if ($event->isActive())
            <a href="{{ route('pos.show', $event) }}" class="btn btn-success"><i class="bi bi-cart3 me-1"></i>Buka kasir</a>
        @endif
        @if ($editable)
            <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Ubah</a>
        @endif
        @if ($event->isDraft())
            <form method="post" action="{{ route('admin.events.activate', $event) }}">
                @csrf
                <button class="btn btn-primary" @disabled($blockers)><i class="bi bi-play-fill me-1"></i>Aktifkan</button>
            </form>
            <form method="post" action="{{ route('admin.events.destroy', $event) }}" onsubmit="return confirm('Hapus kegiatan Draft ini beserta alokasi produknya?')">
                @csrf @method('delete')
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i><span class="visually-hidden">Hapus</span></button>
            </form>
        @elseif ($event->isActive())
            <form method="post" action="{{ route('admin.events.close', $event) }}"
                  onsubmit="return confirm('Tutup kegiatan? Setelah ditutup, transaksi baru tidak dapat dibuat dan stok/harga tidak dapat diubah.')">
                @csrf
                <button class="btn btn-outline-danger"><i class="bi bi-stop-fill me-1"></i>Tutup kegiatan</button>
            </form>
        @endif
    </div>
</div>

@if ($blockers)
    <div class="alert alert-warning d-flex gap-2">
        <i class="bi bi-info-circle-fill"></i>
        <div><strong>Belum dapat diaktifkan:</strong> {{ implode(' ', $blockers) }}</div>
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label">Transaksi berhasil</div><div class="stat-value num">{{ number_format($sales->count, 0, ',', '.') }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label">Total pendapatan</div><div class="stat-value num">{{ rupiah($sales->revenue) }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label">Unit terjual</div><div class="stat-value num">{{ number_format($sales->units, 0, ',', '.') }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label">Produk stok menipis</div><div class="stat-value num {{ $lowCount ? 'text-danger' : '' }}">{{ $lowCount }}</div>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 m-0">Produk & stok kegiatan</h2>
                @if ($editable && $availableProducts->isNotEmpty())
                    <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#allocateForm"><i class="bi bi-plus-lg me-1"></i>Tambah produk</button>
                @endif
            </div>

            @if ($editable)
                <div class="collapse {{ $event->eventProducts->isEmpty() || $errors->hasAny(['product_id', 'selling_price', 'opening_stock']) ? 'show' : '' }} border-bottom" id="allocateForm">
                    <form method="post" action="{{ route('admin.event-products.store', $event) }}" class="row g-2 p-3 align-items-end">
                        @csrf
                        <div class="col-12 col-md-4">
                            <label class="form-label small" for="product_id">Produk</label>
                            <select class="form-select" id="product_id" name="product_id" required>
                                <option value="">— Pilih produk —</option>
                                @foreach ($availableProducts as $p)
                                    <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->code }} · {{ $p->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text"><a href="{{ route('admin.products.create') }}">Produk baru?</a></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="selling_price">Harga jual (Rp)</label>
                            <input class="form-control" type="number" inputmode="numeric" min="0" step="1" id="selling_price" name="selling_price" value="{{ old('selling_price') }}" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small" for="opening_stock">Stok awal</label>
                            <input class="form-control" type="number" inputmode="numeric" min="0" step="1" id="opening_stock" name="opening_stock" value="{{ old('opening_stock', 0) }}" required>
                        </div>
                        <div class="col-6 col-md-1">
                            <label class="form-label small" for="min_stock">Min.</label>
                            <input class="form-control" type="number" inputmode="numeric" min="0" step="1" id="min_stock" name="min_stock" value="{{ old('min_stock', 0) }}">
                        </div>
                        <div class="col-6 col-md-2"><button class="btn btn-primary w-100">Simpan</button></div>
                    </form>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Produk</th><th class="text-end">Harga</th>
                        <th class="text-end">Awal</th><th class="text-end">Masuk</th><th class="text-end">Terjual</th><th class="text-end">Keluar</th>
                        <th class="text-end">Tersedia</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($event->eventProducts as $ep)
                        @php($s = $summary[$ep->id] ?? ['opening' => 0, 'in' => 0, 'sold' => 0, 'out' => 0])
                        <tr class="{{ $ep->is_active ? '' : 'text-muted' }}">
                            <td>
                                <div class="fw-medium">{{ $ep->product->name }}</div>
                                <div class="small text-muted">{{ $ep->product->code }} · {{ $ep->product->unit }}
                                    @unless ($ep->is_active) · <span class="badge text-bg-secondary">Tidak dijual</span> @endunless
                                </div>
                            </td>
                            <td class="text-end num text-nowrap">{{ rupiah($ep->selling_price) }}</td>
                            <td class="text-end num">{{ $s['opening'] }}</td>
                            <td class="text-end num">{{ $s['in'] }}</td>
                            <td class="text-end num">{{ $s['sold'] }}</td>
                            <td class="text-end num">{{ $s['out'] }}</td>
                            <td class="text-end num fw-semibold text-nowrap">
                                {{ $ep->current_stock }}
                                @if ($ep->is_active && $ep->isLowStock())
                                    <span class="badge text-bg-danger ms-1" title="Batas minimum {{ $ep->min_stock }}"><i class="bi bi-exclamation-triangle-fill"></i> Menipis</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($editable)
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#stockModal"
                                            data-action="{{ route('admin.event-products.stock', [$event, $ep]) }}" data-name="{{ $ep->product->name }}"
                                            data-stock="{{ $ep->current_stock }}" title="Stok masuk/keluar"><i class="bi bi-arrow-down-up"></i><span class="visually-hidden">Stok</span></button>
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-action="{{ route('admin.event-products.update', [$event, $ep]) }}" data-name="{{ $ep->product->name }}"
                                            data-price="{{ $ep->selling_price }}" data-min="{{ $ep->min_stock }}" data-opening="{{ $ep->opening_stock }}"
                                            data-active="{{ $ep->is_active ? 1 : 0 }}" title="Ubah"><i class="bi bi-pencil"></i><span class="visually-hidden">Ubah</span></button>
                                @endif
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.event-products.history', [$event, $ep]) }}" title="Riwayat stok"><i class="bi bi-clock-history"></i><span class="visually-hidden">Riwayat</span></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Belum ada produk dialokasikan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card mb-3">
            <div class="card-header bg-white"><h2 class="h6 m-0">Kasir ditugaskan</h2></div>
            <div class="card-body">
                @if ($editable)
                    <form method="post" action="{{ route('admin.events.cashiers', $event) }}">
                        @csrf @method('put')
                        @forelse ($allCashiers as $c)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cashiers[]" value="{{ $c->id }}" id="cashier{{ $c->id }}"
                                       @checked($event->cashiers->contains($c))>
                                <label class="form-check-label" for="cashier{{ $c->id }}">{{ $c->name }} <span class="text-muted small">({{ $c->username }})</span></label>
                            </div>
                        @empty
                            <p class="text-muted small mb-2">Belum ada akun kasir aktif. <a href="{{ route('admin.users.create') }}">Tambah kasir</a>.</p>
                        @endforelse
                        @if ($allCashiers->isNotEmpty())
                            <button class="btn btn-sm btn-outline-primary mt-2">Simpan penugasan</button>
                        @endif
                    </form>
                @else
                    <ul class="mb-0 ps-3">
                        @foreach ($event->cashiers as $c)<li>{{ $c->name }}</li>@endforeach
                    </ul>
                @endif
                <p class="small text-muted mt-2 mb-0">Administrator dapat berjualan di semua kegiatan aktif.</p>
            </div>
        </div>
        @if ($event->description)
            <div class="card">
                <div class="card-header bg-white"><h2 class="h6 m-0">Catatan</h2></div>
                <div class="card-body small">{!! nl2br(e($event->description)) !!}</div>
            </div>
        @endif
    </div>
</div>

@if ($editable)
{{-- Modal stok masuk / keluar --}}
<div class="modal fade" id="stockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Pergerakan stok: <span data-field="name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Stok tersedia saat ini: <strong data-field="stock"></strong></p>
                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="type" value="in" id="typeIn" checked>
                    <label class="btn btn-outline-success" for="typeIn"><i class="bi bi-plus-circle me-1"></i>Stok masuk</label>
                    <input type="radio" class="btn-check" name="type" value="out" id="typeOut">
                    <label class="btn btn-outline-danger" for="typeOut"><i class="bi bi-dash-circle me-1"></i>Keluar / rusak / koreksi</label>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="mvQty">Jumlah</label>
                    <input class="form-control" type="number" inputmode="numeric" min="1" step="1" name="quantity" id="mvQty" required>
                </div>
                <div>
                    <label class="form-label" for="mvNote">Catatan / alasan</label>
                    <input class="form-control" name="note" id="mvNote" maxlength="255" placeholder="Wajib untuk stok keluar">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal ubah harga / minimum / status --}}
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            @csrf @method('put')
            <div class="modal-header">
                <h5 class="modal-title">Ubah: <span data-field="name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="edPrice">Harga jual (Rp)</label>
                    <input class="form-control" type="number" inputmode="numeric" min="0" step="1" name="selling_price" id="edPrice" required>
                    @if ($event->isActive())
                        <div class="form-text">Perubahan harga berlaku untuk transaksi berikutnya; transaksi lama tidak berubah.</div>
                    @endif
                </div>
                @if ($event->isDraft())
                    <div class="mb-3">
                        <label class="form-label" for="edOpening">Stok awal</label>
                        <input class="form-control" type="number" inputmode="numeric" min="0" step="1" name="opening_stock" id="edOpening">
                        <div class="form-text">Koreksi stok awal hanya dapat dilakukan saat Draft dan tercatat di riwayat.</div>
                    </div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="edMin">Batas stok minimum</label>
                    <input class="form-control" type="number" inputmode="numeric" min="0" step="1" name="min_stock" id="edMin">
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="edActive">
                    <label class="form-check-label" for="edActive">Dijual di kasir</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    document.getElementById('stockModal')?.addEventListener('show.bs.modal', (e) => {
        const d = e.relatedTarget.dataset, m = e.target;
        m.querySelector('form').action = d.action;
        m.querySelector('[data-field=name]').textContent = d.name;
        m.querySelector('[data-field=stock]').textContent = d.stock;
        m.querySelector('form').reset();
    });
    document.getElementById('editModal')?.addEventListener('show.bs.modal', (e) => {
        const d = e.relatedTarget.dataset, m = e.target;
        m.querySelector('form').action = d.action;
        m.querySelector('[data-field=name]').textContent = d.name;
        m.querySelector('#edPrice').value = d.price;
        m.querySelector('#edMin').value = d.min;
        m.querySelector('#edActive').checked = d.active === '1';
        const opening = m.querySelector('#edOpening');
        if (opening) opening.value = d.opening;
    });
</script>
@endpush
