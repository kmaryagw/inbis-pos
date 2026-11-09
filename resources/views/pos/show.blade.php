<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="theme-color" content="#14365c">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Kasir · {{ $event->name }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}">
    <script src="{{ asset('js/printer.js') }}"></script>
    <script src="{{ asset('js/pos.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
</head>
<body class="pos">
@php
    $posConfig = [
        'products' => $products,
        'checkoutUrl' => route('pos.checkout', $event),
        'productsUrl' => route('pos.products', $event),
        'csrf' => csrf_token(),
    ];
@endphp
<script type="application/json" id="pos-config">@json($posConfig)</script>

<div class="pos-app" x-data="pos(JSON.parse(document.getElementById('pos-config').textContent))">

    {{-- Header: kegiatan aktif & kasir selalu terlihat --}}
    <header class="pos-header">
        <div class="position-relative" x-data="{ menu: false }" @click.outside="menu = false">
            <button type="button" class="btn-icon" @click="menu = !menu" aria-label="Menu" :aria-expanded="menu"><i class="bi bi-list"></i></button>
            <div class="dropdown-menu show shadow" x-show="menu" x-cloak style="top: 48px; left: 0">
                <button type="button" class="dropdown-item py-2" @click="menu = false; printerOpen = true"><i class="bi bi-printer me-2"></i>Pengaturan printer</button>
                <a class="dropdown-item py-2" href="{{ route('transactions.index', ['event' => $event->id]) }}"><i class="bi bi-receipt me-2"></i>Riwayat transaksi</a>
                <a class="dropdown-item py-2" href="{{ route('pos.index', ['pilih' => 1]) }}"><i class="bi bi-arrow-left-right me-2"></i>Ganti kegiatan</a>
                @if (auth()->user()->isAdmin())
                    <a class="dropdown-item py-2" href="{{ route('admin.events.show', $event) }}"><i class="bi bi-gear me-2"></i>Kelola kegiatan</a>
                @endif
                <a class="dropdown-item py-2" href="{{ route('account.password') }}"><i class="bi bi-key me-2"></i>Ubah kata sandi</a>
                <div class="dropdown-divider"></div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item py-2 text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                </form>
            </div>
        </div>
        <div class="flex-grow-1 min-w-0" style="min-width:0">
            <div class="event-name">{{ $event->name }}</div>
            <div class="cashier"><i class="bi bi-person-fill"></i> {{ auth()->user()->name }}</div>
        </div>
        <button type="button" class="btn-icon" @click="syncStock(true)" aria-label="Perbarui stok"><i class="bi bi-arrow-clockwise"></i></button>
    </header>

    <div class="offline-banner" x-show="!online" x-cloak><i class="bi bi-wifi-off me-1"></i>Tidak ada koneksi internet. Transaksi belum bisa disimpan.</div>

    <div class="pos-body">
        <section class="pos-products" aria-label="Daftar produk">
            {{-- Pencarian --}}
            <div class="pos-search">
                <i class="bi bi-search search-icon"></i>
                <input type="search" class="form-control" placeholder="Cari nama atau kode produk" x-model="query" x-ref="search"
                       enterkeyhint="search" autocomplete="off" aria-label="Cari produk"
                       @keydown.enter.prevent="filtered.length === 1 && add(filtered[0]); filtered.length === 1 && (query = '')">
                <button type="button" class="clear-btn" x-show="query" x-cloak @click="query = ''; $refs.search.focus()" aria-label="Hapus pencarian"><i class="bi bi-x-circle-fill"></i></button>
            </div>

            <div class="product-grid">
                <template x-for="p in filtered" :key="p.id">
                    <div class="product-item" :class="{ 'in-cart': qtyOf(p.id) > 0, 'sold-out': p.stock <= 0 }"
                         role="button" tabindex="0" @click="add(p)" @keydown.enter="add(p)" :aria-label="'Tambah ' + p.name">
                        <template x-if="p.photo"><img class="thumb" :src="p.photo" alt="" loading="lazy"></template>
                        <div class="info">
                            <div class="name" x-text="p.name"></div>
                            <div class="meta" x-text="p.code + (p.category ? ' · ' + p.category : '')"></div>
                            <div class="d-flex gap-2 align-items-baseline">
                                <span class="price num" x-text="rupiah(p.price)"></span>
                                <span class="stock-badge" :class="stockClass(p)">
                                    <i class="bi" :class="p.stock <= 0 ? 'bi-x-circle' : (p.stock <= p.min ? 'bi-exclamation-triangle' : 'bi-box')"></i>
                                    <span x-text="stockText(p)"></span>
                                </span>
                            </div>
                        </div>
                        <template x-if="qtyOf(p.id) === 0">
                            <button type="button" class="btn btn-primary add-btn" :disabled="p.stock <= 0" @click.stop="add(p)" :aria-label="'Tambah ' + p.name"><i class="bi bi-plus-lg"></i></button>
                        </template>
                        <template x-if="qtyOf(p.id) > 0">
                            <div class="qty-stepper" @click.stop>
                                <button type="button" @click="decrease(p.id)" aria-label="Kurangi"><i class="bi bi-dash-lg"></i></button>
                                <span class="qty" x-text="qtyOf(p.id)"></span>
                                <button type="button" @click="add(p)" :disabled="qtyOf(p.id) >= p.stock" aria-label="Tambah"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="empty-state" x-show="filtered.length === 0" x-cloak>
                <i class="bi bi-search"></i>
                <span x-show="products.length">Produk "<span x-text="query"></span>" tidak ditemukan.</span>
                <span x-show="!products.length">Belum ada produk yang dijual pada kegiatan ini.</span>
            </div>
        </section>

        {{-- Keranjang & pembayaran --}}
        <aside class="pos-cart" :class="{ open: cartOpen }" aria-label="Keranjang dan pembayaran">
            <div class="cart-head">
                <button type="button" class="btn btn-light cart-close" @click="cartOpen = false" aria-label="Kembali ke produk"><i class="bi bi-chevron-down"></i></button>
                <h2>Keranjang <span class="text-muted fw-normal" x-show="itemCount" x-text="'(' + itemCount + ' item)'"></span></h2>
                <button type="button" class="btn btn-sm btn-outline-secondary" x-show="cart.length" @click="clearCart()" :disabled="processing">
                    <i class="bi bi-trash3 me-1"></i>Kosongkan
                </button>
            </div>

            <div class="cart-scroll">
                <template x-for="l in lines" :key="l.id">
                    <div class="cart-line" :class="{ over: l.over }">
                        <div class="info">
                            <div class="name" x-text="l.p.name"></div>
                            <div class="sub"><span x-text="rupiah(l.p.price)"></span> × <span x-text="l.qty"></span></div>
                            <div class="sub text-danger fw-semibold" x-show="l.over"><i class="bi bi-exclamation-triangle-fill"></i> Stok tersisa <span x-text="l.p.stock"></span></div>
                            <div class="line-total mt-1" x-text="rupiah(l.subtotal)"></div>
                        </div>
                        <div class="qty-stepper">
                            <button type="button" @click="decrease(l.id)" :disabled="processing" aria-label="Kurangi"><i class="bi bi-dash-lg"></i></button>
                            <input type="text" inputmode="numeric" class="form-control text-center fw-bold px-1" style="width:3.25rem;height:44px"
                                   :value="l.qty" @change="setQty(l.id, $event.target.value); $event.target.value = qtyOf(l.id) || ''"
                                   @focus="$event.target.select()" :disabled="processing" aria-label="Jumlah">
                            <button type="button" @click="add(l.p)" :disabled="processing || l.qty >= l.p.stock" aria-label="Tambah"><i class="bi bi-plus-lg"></i></button>
                        </div>
                        <button type="button" class="remove-btn" @click="remove(l.id)" :disabled="processing" :aria-label="'Hapus ' + l.p.name"><i class="bi bi-x-lg"></i></button>
                    </div>
                </template>

                <div class="empty-state" x-show="!cart.length">
                    <i class="bi bi-cart"></i>Keranjang kosong.<br>Ketuk produk untuk menambahkan.
                </div>

                <div class="pay-section" x-show="cart.length">
                    <div class="fw-semibold mb-2">Metode pembayaran</div>
                    <div class="btn-group w-100 method-toggle mb-3" role="group">
                        <button type="button" class="btn" :class="method === 'cash' ? 'btn-primary' : 'btn-outline-primary'" @click="setMethod('cash')" :aria-pressed="method === 'cash'">
                            <i class="bi bi-cash-stack me-1"></i>Tunai
                        </button>
                        <button type="button" class="btn" :class="method === 'qris' ? 'btn-primary' : 'btn-outline-primary'" @click="setMethod('qris')" :aria-pressed="method === 'qris'">
                            <i class="bi bi-qr-code me-1"></i>QRIS
                        </button>
                    </div>

                    <div x-show="method === 'cash'">
                        <label class="form-label fw-semibold" for="cash">Uang diterima</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text fw-bold">Rp</span>
                            <input id="cash" type="text" inputmode="numeric" enterkeyhint="done" class="form-control cash-input" placeholder="0"
                                   :value="cashDisplay" @input="onCashInput($event)" @focus="$el.scrollIntoView({ block: 'center', behavior: 'smooth' })"
                                   @keydown.enter.prevent="$el.blur()" autocomplete="off">
                        </div>
                        <div class="quick-cash mb-3">
                            <template x-for="(v, i) in quickCash" :key="v">
                                <button type="button" class="btn btn-outline-secondary" @click="setCash(v)" x-text="i === 0 ? 'Uang pas' : fmt(v)"></button>
                            </template>
                        </div>
                        <div class="change-box" :class="cash >= total ? 'bg-success-subtle text-success-emphasis' : 'bg-light text-body'" x-show="cashDigits">
                            <span>
                                <i class="bi" :class="cash >= total ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'"></i>
                                <span x-text="cash >= total ? 'Kembalian' : 'Kurang'"></span>
                            </span>
                            <span class="amount" x-text="rupiah(cash >= total ? change : shortfall)"></span>
                        </div>
                    </div>

                    <div x-show="method === 'qris'" x-cloak>
                        <div class="alert alert-info small d-flex gap-2 mb-2">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>Minta pembeli membayar via QRIS merchant, lalu <strong>cek pembayaran berhasil</strong> di aplikasi merchant. Sistem hanya mencatat metode pembayaran.</div>
                        </div>
                        <div class="form-check p-3 border rounded-3" style="padding-left: 2.5rem !important">
                            <input class="form-check-input" type="checkbox" id="qrisOk" x-model="qrisConfirmed" @change="touch()" style="transform: scale(1.3)">
                            <label class="form-check-label fw-semibold" for="qrisOk">Pembayaran QRIS sebesar <span x-text="rupiah(total)"></span> sudah saya cek berhasil</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cart-foot">
                <div class="total-row">
                    <span class="fw-semibold">Total</span>
                    <span class="amount" x-text="rupiah(total)"></span>
                </div>
                <div class="small text-danger mb-2" x-show="payHint && cart.length" x-cloak><i class="bi bi-info-circle"></i> <span x-text="payHint"></span></div>
                <button type="button" class="btn btn-success w-100 pay-btn" @click="pay()" :disabled="!canPay">
                    <span x-show="!processing"><i class="bi bi-check2-circle me-1"></i>Simpan pembayaran</span>
                    <span x-show="processing" x-cloak><span class="spinner-border spinner-border-sm me-2"></span>Menyimpan…</span>
                </button>
            </div>
        </aside>
    </div>

    {{-- Bar bawah HP: jumlah item, total, tombol Bayar --}}
    <div class="pos-bar">
        <div class="summary">
            <div class="count" x-text="itemCount ? itemCount + ' item' : 'Keranjang kosong'"></div>
            <div class="total" x-text="rupiah(total)"></div>
        </div>
        <button type="button" class="btn btn-success" @click="openCart()" :disabled="!cart.length">
            Bayar <i class="bi bi-chevron-right"></i>
        </button>
    </div>

    {{-- Hasil transaksi --}}
    <div class="pos-result" x-show="result" x-cloak role="dialog" aria-modal="true" aria-labelledby="resultTitle">
        <div class="sheet" x-show="result" x-transition>
            <template x-if="result">
                <div>
                    <div class="ok-icon"><i class="bi bi-check-circle-fill"></i></div>
                    <h2 class="h4 fw-bold mt-2 mb-1" id="resultTitle">Transaksi berhasil</h2>
                    <div class="trx-no text-muted" x-text="result.number"></div>
                    <dl>
                        <dt>Total</dt><dd x-text="rupiah(result.total)"></dd>
                        <dt>Metode</dt><dd x-text="result.payment_method"></dd>
                        <template x-if="result.payment_method === 'Tunai'">
                            <dt>Diterima</dt>
                        </template>
                        <template x-if="result.payment_method === 'Tunai'">
                            <dd x-text="rupiah(result.amount_paid)"></dd>
                        </template>
                        <template x-if="result.payment_method === 'Tunai'">
                            <dt class="align-self-center">Kembalian</dt>
                        </template>
                        <template x-if="result.payment_method === 'Tunai'">
                            <dd class="change" x-text="rupiah(result.change)"></dd>
                        </template>
                    </dl>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" @click="printReceipt()">
                            <i class="bi me-1" :class="printer.mode === 'rawbt' ? 'bi-bluetooth' : 'bi-printer'"></i>Cetak struk
                        </button>
                        <button type="button" class="btn btn-outline-secondary" @click="newSale()"><i class="bi bi-plus-circle me-1"></i>Transaksi baru</button>
                        <button type="button" class="btn btn-link btn-sm text-decoration-none" style="height:auto"
                                @click="printReceipt(printer.mode === 'rawbt' ? 'browser' : 'rawbt')"
                                x-text="printer.mode === 'rawbt' ? 'Cetak lewat dialog browser' : 'Cetak lewat RawBT (Bluetooth)'"></button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Pengaturan printer (per perangkat) --}}
    <div class="pos-result" x-show="printerOpen" x-cloak role="dialog" aria-modal="true" aria-labelledby="printerTitle" @click.self="printerOpen = false">
        <div class="sheet text-start" x-show="printerOpen" x-transition>
            <h2 class="h5 fw-bold mb-1" id="printerTitle"><i class="bi bi-printer me-1"></i>Pengaturan printer</h2>
            <p class="small text-muted">Disimpan di perangkat ini saja. Printer: thermal 58 mm (iWare X583).</p>

            <div class="d-grid gap-2 mb-3">
                <label class="border rounded-3 p-3 d-flex gap-3 align-items-start" :class="printer.mode === 'rawbt' && 'border-primary bg-primary-subtle'">
                    <input class="form-check-input mt-1" type="radio" value="rawbt" x-model="printer.mode">
                    <span>
                        <span class="fw-semibold d-block"><i class="bi bi-bluetooth"></i> HP Android + Bluetooth (RawBT)</span>
                        <span class="small text-muted">Pasang aplikasi <strong>RawBT</strong> dari Play Store, pasangkan (pair) printer X583 di pengaturan Bluetooth, lalu pilih printer di RawBT.</span>
                    </span>
                </label>
                <label class="border rounded-3 p-3 d-flex gap-3 align-items-start" :class="printer.mode === 'browser' && 'border-primary bg-primary-subtle'">
                    <input class="form-check-input mt-1" type="radio" value="browser" x-model="printer.mode">
                    <span>
                        <span class="fw-semibold d-block"><i class="bi bi-usb-symbol"></i> Laptop + USB (dialog cetak)</span>
                        <span class="small text-muted">Pasang driver printer 58 mm di laptop, pilih ukuran kertas 58 mm dan margin "None" di dialog cetak.</span>
                    </span>
                </label>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="autoPrint" x-model="printer.auto">
                <label class="form-check-label" for="autoPrint">Cetak otomatis setelah transaksi berhasil</label>
            </div>

            <div class="d-grid gap-2">
                <button type="button" class="btn btn-primary" @click="savePrinter()">Simpan</button>
                <button type="button" class="btn btn-outline-secondary" x-show="printer.mode === 'rawbt'" @click="testPrint()"><i class="bi bi-bluetooth me-1"></i>Cetak struk uji</button>
                <button type="button" class="btn btn-light" @click="printer = InbisPrinter.get(); printerOpen = false">Batal</button>
            </div>
        </div>
    </div>

    {{-- Notifikasi --}}
    <div class="pos-toast alert mb-0" x-show="toast" x-cloak x-transition.opacity :class="toast ? 'alert-' + toast.type : ''" role="alert" aria-live="assertive">
        <i class="bi" :class="toast ? 'bi-' + toast.icon : ''"></i>
        <div class="flex-grow-1" x-text="toast?.message"></div>
        <button type="button" class="btn-close" @click="toast = null" aria-label="Tutup"></button>
    </div>
</div>
</body>
</html>
