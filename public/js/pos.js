/*
 * Layar kasir INBIS POS.
 * Keranjang, total, dan kembalian dihitung di browser agar responsif;
 * server hanya dihubungi saat menyimpan transaksi dan sinkronisasi stok.
 */
(() => {
    const fmt = new Intl.NumberFormat('id-ID');
    const rupiah = (n) => 'Rp' + fmt.format(n || 0);

    // crypto.randomUUID hanya tersedia di HTTPS/localhost; sediakan cadangan untuk akses via IP lokal
    const uuid = () => {
        if (window.crypto?.randomUUID) return crypto.randomUUID();
        const b = crypto.getRandomValues(new Uint8Array(16));
        b[6] = (b[6] & 0x0f) | 0x40;
        b[8] = (b[8] & 0x3f) | 0x80;
        const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
        return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    };

    const normalize = (s) => (s || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    document.addEventListener('alpine:init', () => {
        Alpine.data('pos', (config) => ({
            products: config.products,
            query: '',
            cart: [], // [{ id, qty }]
            cartOpen: false,
            method: 'cash',
            cashDigits: '',
            qrisConfirmed: false,
            processing: false,
            idempotencyKey: uuid(),
            result: null,
            toast: null,
            toastTimer: null,
            online: navigator.onLine,
            lastSync: Date.now(),

            init() {
                this.products.forEach((p) => (p._search = normalize(`${p.name} ${p.code} ${p.category || ''}`)));

                window.addEventListener('online', () => { this.online = true; this.syncStock(); });
                window.addEventListener('offline', () => { this.online = false; });
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') this.syncStock();
                });
                // Sinkronisasi berkala agar stok dari kasir lain ikut terlihat
                setInterval(() => this.syncStock(), 45000);

                // Kunci scroll halaman saat sheet keranjang terbuka di HP
                this.$watch('cartOpen', (open) => document.body.classList.toggle('overflow-hidden', open && !this.isDesktop()));
            },

            isDesktop() {
                return window.matchMedia('(min-width: 992px)').matches;
            },

            // ---------- Produk ----------
            get filtered() {
                const q = normalize(this.query.trim());
                if (!q) return this.products;
                const terms = q.split(/\s+/);
                return this.products.filter((p) => terms.every((t) => p._search.includes(t)));
            },
            product(id) {
                return this.products.find((p) => p.id === id);
            },
            stockClass(p) {
                if (p.stock <= 0) return 'stock-out';
                if (p.stock <= p.min) return 'stock-low';
                return 'text-muted';
            },
            stockText(p) {
                if (p.stock <= 0) return 'Habis';
                if (p.stock <= p.min) return `Sisa ${p.stock} · menipis`;
                return `Stok ${p.stock}`;
            },

            // ---------- Keranjang ----------
            qtyOf(id) {
                return this.cart.find((l) => l.id === id)?.qty || 0;
            },
            add(p) {
                if (this.processing) return;
                const line = this.cart.find((l) => l.id === p.id);
                const current = line?.qty || 0;
                if (current + 1 > p.stock) {
                    this.notify(p.stock <= 0 ? `${p.name} habis.` : `Stok ${p.name} hanya ${p.stock}.`, 'warning');
                    return;
                }
                if (line) line.qty++;
                else this.cart.push({ id: p.id, qty: 1 });
                this.touch();
            },
            decrease(id) {
                if (this.processing) return;
                const line = this.cart.find((l) => l.id === id);
                if (!line) return;
                line.qty--;
                if (line.qty <= 0) this.remove(id);
                this.touch();
            },
            setQty(id, value) {
                const line = this.cart.find((l) => l.id === id);
                const p = this.product(id);
                if (!line || !p) return;
                let qty = parseInt(String(value).replace(/\D/g, ''), 10) || 0;
                if (qty > p.stock) {
                    qty = p.stock;
                    this.notify(`Stok ${p.name} hanya ${p.stock}.`, 'warning');
                }
                if (qty <= 0) this.remove(id);
                else line.qty = qty;
                this.touch();
            },
            remove(id) {
                this.cart = this.cart.filter((l) => l.id !== id);
                this.touch();
            },
            clearCart() {
                if (!this.cart.length || this.processing) return;
                if (!confirm('Kosongkan semua item di keranjang?')) return;
                this.resetSale();
            },
            get lines() {
                return this.cart
                    .map((l) => {
                        const p = this.product(l.id);
                        return p ? { ...l, p, subtotal: p.price * l.qty, over: l.qty > p.stock } : null;
                    })
                    .filter(Boolean);
            },
            get itemCount() {
                return this.cart.reduce((s, l) => s + l.qty, 0);
            },
            get total() {
                return this.lines.reduce((s, l) => s + l.subtotal, 0);
            },
            get hasStockIssue() {
                return this.lines.some((l) => l.over);
            },
            // Setiap perubahan isi transaksi memakai kunci idempotensi baru
            touch() {
                this.idempotencyKey = uuid();
            },

            // ---------- Pembayaran ----------
            get cash() {
                return parseInt(this.cashDigits || '0', 10);
            },
            get cashDisplay() {
                return this.cashDigits ? fmt.format(this.cash) : '';
            },
            onCashInput(e) {
                this.cashDigits = e.target.value.replace(/\D/g, '').replace(/^0+/, '').slice(0, 12);
                e.target.value = this.cashDisplay;
                this.touch();
            },
            setCash(amount) {
                this.cashDigits = String(amount);
                this.touch();
            },
            setMethod(m) {
                this.method = m;
                this.qrisConfirmed = false;
                this.touch();
            },
            get change() {
                return Math.max(0, this.cash - this.total);
            },
            get shortfall() {
                return Math.max(0, this.total - this.cash);
            },
            get quickCash() {
                const t = this.total;
                if (t <= 0) return [];
                const out = new Set([t]);
                for (const step of [5000, 10000, 20000, 50000, 100000]) {
                    const v = Math.ceil(t / step) * step;
                    if (v > t) out.add(v);
                    if (out.size >= 4) break;
                }
                return [...out].sort((a, b) => a - b).slice(0, 4);
            },
            get canPay() {
                if (this.processing || !this.cart.length || this.hasStockIssue) return false;
                if (this.method === 'cash') return this.cash >= this.total;
                return this.qrisConfirmed;
            },
            get payHint() {
                if (!this.cart.length) return 'Keranjang masih kosong.';
                if (this.hasStockIssue) return 'Ada item melebihi stok tersedia.';
                if (this.method === 'cash' && this.cash < this.total) {
                    return this.cashDigits ? `Uang kurang ${rupiah(this.shortfall)}.` : 'Masukkan uang diterima.';
                }
                if (this.method === 'qris' && !this.qrisConfirmed) return 'Centang konfirmasi pembayaran QRIS.';
                return '';
            },
            openCart() {
                if (!this.cart.length) {
                    this.notify('Pilih produk terlebih dahulu.', 'warning');
                    return;
                }
                this.cartOpen = true;
            },

            async pay() {
                if (!this.canPay) {
                    if (this.payHint) this.notify(this.payHint, 'warning');
                    return;
                }
                this.processing = true;
                try {
                    const res = await fetch(config.checkoutUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': config.csrf,
                        },
                        body: JSON.stringify({
                            idempotency_key: this.idempotencyKey,
                            items: this.cart.map((l) => ({ id: l.id, qty: l.qty })),
                            payment_method: this.method,
                            amount_paid: this.method === 'cash' ? this.cash : null,
                            expected_total: this.total,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));

                    if (res.ok) {
                        this.applyStock(data.stock);
                        this.result = data.transaction;
                        this.resetSale();
                        if (this.printer.auto) this.printReceipt();
                        return;
                    }
                    if (res.status === 419) {
                        this.notify('Sesi berakhir. Muat ulang halaman lalu login kembali.', 'danger', 0);
                    } else if (res.status === 422) {
                        if (data.stock) this.applyStock(data.stock);
                        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                        this.notify(firstError || data.message || 'Transaksi ditolak.', 'danger', 8000);
                        if (/harga/i.test(data.message || '')) this.syncStock();
                    } else {
                        this.notify(data.message || 'Transaksi gagal disimpan. Coba lagi.', 'danger', 8000);
                    }
                } catch (e) {
                    // Kunci idempotensi tidak diganti: tekan Simpan lagi aman, tidak akan tercatat ganda
                    this.notify('Koneksi terputus. Periksa internet lalu tekan Simpan lagi — transaksi tidak akan tercatat ganda.', 'danger', 0);
                } finally {
                    this.processing = false;
                }
            },

            resetSale() {
                this.cart = [];
                this.cashDigits = '';
                this.qrisConfirmed = false;
                this.method = 'cash';
                this.cartOpen = false;
                this.touch();
            },

            newSale() {
                this.result = null;
                this.$nextTick(() => this.$refs.search?.focus({ preventScroll: true }));
            },

            // ---------- Printer ----------
            printer: window.InbisPrinter.get(),
            printerOpen: false,
            savePrinter() {
                window.InbisPrinter.set(this.printer);
                this.printerOpen = false;
                this.notify('Pengaturan printer disimpan untuk perangkat ini.', 'success');
            },
            printReceipt(mode = null) {
                if (!this.result) return;
                const target = { escpos: this.result.escpos, receiptUrl: this.result.receipt_url };
                if (mode === 'rawbt') window.InbisPrinter.rawbt(target.escpos);
                else if (mode === 'browser') window.InbisPrinter.browser(target.receiptUrl);
                else window.InbisPrinter.print(target);
            },
            testPrint() {
                // Struk uji ESC/POS sederhana untuk memastikan RawBT & printer terhubung
                const text = '\x1B@\x1Ba\x01\x1BE\x01INBIS POS\x1BE\x00\nTes printer berhasil\n' + new Date().toLocaleString('id-ID') + '\n--------------------------------\n\x1Bd\x04';
                window.InbisPrinter.rawbt(btoa(text));
            },

            // ---------- Sinkronisasi stok ----------
            applyStock(stock) {
                if (!stock) return;
                this.products.forEach((p) => {
                    if (stock[p.id] !== undefined) p.stock = Number(stock[p.id]);
                });
                this.lastSync = Date.now();
            },
            async syncStock(manual = false) {
                if (this.processing || !navigator.onLine) return;
                try {
                    const res = await fetch(config.productsUrl, { headers: { Accept: 'application/json' } });
                    if (res.status === 403) {
                        const data = await res.json().catch(() => ({}));
                        this.notify((data.message || 'Kegiatan tidak aktif') + '. Transaksi tidak dapat dilakukan.', 'danger', 0);
                        return;
                    }
                    if (!res.ok) return;
                    const data = await res.json();
                    const fresh = new Map(data.products.map((p) => [p.id, p]));
                    // Perbarui harga/stok, tambahkan produk baru, hilangkan yang dinonaktifkan
                    this.products = data.products.map((p) => ({
                        ...p,
                        _search: normalize(`${p.name} ${p.code} ${p.category || ''}`),
                    }));
                    const before = this.cart.length;
                    this.cart = this.cart.filter((l) => fresh.has(l.id));
                    if (this.cart.length !== before) this.notify('Beberapa produk tidak lagi dijual dan dihapus dari keranjang.', 'warning');
                    this.lastSync = Date.now();
                    if (manual) this.notify('Data produk & stok diperbarui.', 'success');
                } catch (e) {
                    if (manual) this.notify('Gagal memperbarui. Periksa koneksi.', 'danger');
                }
            },

            notify(message, type = 'info', timeout = 4000) {
                clearTimeout(this.toastTimer);
                const icons = { success: 'check-circle-fill', warning: 'exclamation-triangle-fill', danger: 'x-octagon-fill', info: 'info-circle-fill' };
                this.toast = { message, type, icon: icons[type] || icons.info };
                if (timeout) this.toastTimer = setTimeout(() => (this.toast = null), timeout);
            },

            rupiah,
            fmt: (n) => fmt.format(n || 0),
        }));
    });
})();
