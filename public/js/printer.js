/*
 * Pengaturan & pengiriman cetak struk, disimpan per perangkat.
 * - browser: dialog cetak browser (laptop + printer USB, driver 58 mm)
 * - rawbt:   HP Android + printer Bluetooth melalui aplikasi RawBT (ESC/POS)
 */
(() => {
    const KEY = 'inbis.printer';
    const isAndroid = /Android/i.test(navigator.userAgent);
    const defaults = { mode: isAndroid ? 'rawbt' : 'browser', auto: false };

    window.InbisPrinter = {
        isAndroid,

        get() {
            try {
                return { ...defaults, ...JSON.parse(localStorage.getItem(KEY) || '{}') };
            } catch (e) {
                return { ...defaults };
            }
        },

        set(cfg) {
            try {
                localStorage.setItem(KEY, JSON.stringify(cfg));
            } catch (e) {
                // Penyimpanan diblokir (mode privat): pengaturan berlaku sampai halaman ditutup
            }
        },

        /** Kirim data ESC/POS (base64) ke RawBT; Android membuka Play Store bila RawBT belum terpasang. */
        rawbt(base64) {
            window.location.href = 'intent:base64,' + base64 + '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;';
        },

        browser(receiptUrl) {
            window.open(receiptUrl + (receiptUrl.includes('?') ? '&' : '?') + 'print=1', '_blank');
        },

        /** Cetak sesuai pengaturan perangkat ini. */
        print({ escpos, receiptUrl }) {
            if (this.get().mode === 'rawbt' && escpos) this.rawbt(escpos);
            else this.browser(receiptUrl);
        },
    };
})();
