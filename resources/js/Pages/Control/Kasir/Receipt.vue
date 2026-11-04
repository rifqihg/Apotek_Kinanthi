<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { toast } from '@/Components/ui/toast';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    sale: Object,
});

// Formatters
const formatDateTime = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(d)) : '';
const formatRupiah = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);

// Paper Size State (58mm vs 80mm) with LocalStorage persistence
const paperSize = ref(typeof window !== 'undefined' ? localStorage.getItem('kinanthi_receipt_paper_size') || '58mm' : '58mm');
const setPaperSize = (size) => {
    paperSize.value = size;
    if (typeof window !== 'undefined') {
        localStorage.setItem('kinanthi_receipt_paper_size', size);
    }
};

// Auto Print State with LocalStorage persistence
const autoPrint = ref(typeof window !== 'undefined' ? localStorage.getItem('kinanthi_receipt_auto_print') === 'true' : false);
const toggleAutoPrint = () => {
    autoPrint.value = !autoPrint.value;
    if (typeof window !== 'undefined') {
        localStorage.setItem('kinanthi_receipt_auto_print', autoPrint.value ? 'true' : 'false');
    }
};

// Standard Thermal Browser Print
const printReceipt = () => {
    window.print();
};

// Web Bluetooth Support
const isBluetoothSupported = ref(typeof navigator !== 'undefined' && !!navigator.bluetooth);
const isBluetoothConnecting = ref(false);

// ESC/POS Command Byte Generator
const generateEscPos = (sale, paper = '58mm') => {
    const lineWidth = paper === '58mm' ? 32 : 48;

    const padLeft = (text, len) => {
        text = String(text || '');
        if (text.length >= len) return text.substring(0, len);
        return ' '.repeat(len - text.length) + text;
    };

    const twoColumns = (left, right, width = lineWidth) => {
        left = String(left || '');
        right = String(right || '');
        if (left.length + right.length >= width) {
            const maxLeft = Math.max(4, width - right.length - 1);
            left = left.substring(0, maxLeft);
        }
        const spaces = Math.max(1, width - left.length - right.length);
        return left + ' '.repeat(spaces) + right;
    };

    const ESC = 0x1B;
    const GS = 0x1D;
    const LF = 0x0A;

    const bytes = [];
    const pushBytes = (...b) => bytes.push(...b);
    const pushText = (str) => {
        const encoder = new TextEncoder();
        const encoded = encoder.encode(str);
        for (let i = 0; i < encoded.length; i++) bytes.push(encoded[i]);
    };
    const pushLine = (str = '') => {
        pushText(str);
        pushBytes(LF);
    };

    // 1. Initialize printer: ESC @
    pushBytes(ESC, 0x40);

    // 2. Header (Center align)
    pushBytes(ESC, 0x61, 1); // Center
    pushBytes(ESC, 0x45, 1); // Bold on
    pushBytes(GS, 0x21, 0x11); // Double width & height
    pushLine('APOTEK KINANTHI');
    pushBytes(GS, 0x21, 0x00); // Normal size
    pushBytes(ESC, 0x45, 0); // Bold off
    pushLine('Jl. Dieng No.24, Wonosobo');
    pushLine('Telp: 081238575662');
    pushLine('-'.repeat(lineWidth));

    // 3. Info Transaksi (Left align)
    pushBytes(ESC, 0x61, 0); // Left
    pushLine(twoColumns('No. Invoice', sale.sale_number || '-'));
    pushLine(twoColumns('Tanggal', formatDateTime(sale.created_at)));
    pushLine(twoColumns('Kasir', sale.kasir?.name || '-'));
    if (sale.prescription?.patient?.name) {
        pushLine(twoColumns('Pasien', sale.prescription.patient.name));
    }
    if (sale.prescription?.prescription_number) {
        pushLine(twoColumns('No. Resep', sale.prescription.prescription_number));
    }
    pushLine('-'.repeat(lineWidth));

    // 4. Daftar Item
    (sale.sale_items || []).forEach(item => {
        pushLine(item.item_name);
        const qtyUnit = `${Number(item.quantity)} ${item.unit} x ${formatRupiah(item.unit_price)}`;
        const subtotal = formatRupiah(item.subtotal);
        pushLine(twoColumns(`  ${qtyUnit}`, subtotal));
    });
    pushLine('-'.repeat(lineWidth));

    // 5. Total & Pembayaran
    if (Number(sale.discount_amount) > 0) {
        const subtotal = Number(sale.total_amount) + Number(sale.discount_amount);
        pushLine(twoColumns('Subtotal', formatRupiah(subtotal)));
        pushLine(twoColumns('Diskon', '-' + formatRupiah(sale.discount_amount)));
    }

    pushBytes(ESC, 0x45, 1); // Bold on
    pushLine(twoColumns('TOTAL BELANJA', formatRupiah(sale.total_amount)));
    pushBytes(ESC, 0x45, 0); // Bold off

    const method = String(sale.payment_method || 'TUNAI').toUpperCase();
    pushLine(twoColumns(`Bayar (${method})`, formatRupiah(sale.paid_amount)));

    pushBytes(ESC, 0x45, 1); // Bold on
    pushLine(twoColumns('KEMBALIAN', formatRupiah(sale.change_amount)));
    pushBytes(ESC, 0x45, 0); // Bold off
    pushLine('-'.repeat(lineWidth));

    // 6. Footer (Center)
    pushBytes(ESC, 0x61, 1); // Center
    pushLine('Terima kasih atas kunjungan Anda');
    pushLine('Semoga lekas sembuh dan sehat selalu');
    pushLine('');
    pushLine('');
    pushLine('');

    // Cut paper: GS V 66 0
    pushBytes(GS, 0x56, 66, 0);

    return new Uint8Array(bytes);
};

// Direct Bluetooth Print via Web Bluetooth API
const printViaBluetooth = async () => {
    if (!navigator.bluetooth) {
        toast.warning('Bluetooth Tidak Didukung', {
            description: 'Browser atau jaringan tidak mendukung Web Bluetooth. Silakan gunakan tombol Cetak Struk (Thermal).'
        });
        return;
    }

    isBluetoothConnecting.value = true;

    try {
        toast.info('Mencari Printer Bluetooth...', {
            description: 'Pilih printer thermal bluetooth Anda pada jendela peramban.'
        });

        const device = await navigator.bluetooth.requestDevice({
            filters: [
                { services: ['000018f0-0000-1000-8000-00805f9b34fb'] },
                { services: ['0000ffe0-0000-1000-8000-00805f9b34fb'] },
                { namePrefix: 'POS' },
                { namePrefix: 'RPP' },
                { namePrefix: 'MPT' },
                { namePrefix: 'MTP' },
                { namePrefix: 'Printer' },
                { namePrefix: 'Thermal' },
                { namePrefix: 'Bluetooth' }
            ],
            optionalServices: [
                '000018f0-0000-1000-8000-00805f9b34fb',
                '0000ffe0-0000-1000-8000-00805f9b34fb',
                '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                'e7810a71-73ae-499d-8c15-faa9aef0c3f2'
            ]
        }).catch(() => {
            // Fallback: acceptAllDevices jika filter prefix tidak terdaftar di perangkat
            return navigator.bluetooth.requestDevice({
                acceptAllDevices: true,
                optionalServices: [
                    '000018f0-0000-1000-8000-00805f9b34fb',
                    '0000ffe0-0000-1000-8000-00805f9b34fb',
                    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                    'e7810a71-73ae-499d-8c15-faa9aef0c3f2'
                ]
            });
        });

        if (!device) {
            isBluetoothConnecting.value = false;
            return;
        }

        const server = await device.gatt.connect();
        const services = await server.getPrimaryServices();
        let writeChar = null;

        for (const service of services) {
            try {
                const chars = await service.getCharacteristics();
                for (const ch of chars) {
                    if (ch.properties.write || ch.properties.writeWithoutResponse) {
                        writeChar = ch;
                        break;
                    }
                }
            } catch {
                // Lewati service yang tidak memiliki izin
            }
            if (writeChar) break;
        }

        if (!writeChar) {
            throw new Error('Karakteristik data printer Bluetooth tidak ditemukan.');
        }

        const data = generateEscPos(props.sale, paperSize.value);

        // Kirim dalam potongan 100 bytes untuk mencegah overflow pada printer Bluetooth
        const chunkSize = 100;
        for (let i = 0; i < data.length; i += chunkSize) {
            const chunk = data.slice(i, i + chunkSize);
            if (writeChar.properties.writeWithoutResponse) {
                await writeChar.writeValueWithoutResponse(chunk);
            } else {
                await writeChar.writeValue(chunk);
            }
            await new Promise(r => setTimeout(r, 20));
        }

        toast.success('Struk Dicetak!', {
            description: `Berhasil dikirim ke printer bluetooth ${device.name || ''}.`
        });

    } catch (err) {
        if (err.name !== 'NotFoundError') {
            console.error('Bluetooth Print Error:', err);
            toast.error('Gagal Cetak Bluetooth', {
                description: err.message || 'Pastikan printer Bluetooth menyala dan tersambung.'
            });
        }
    } finally {
        isBluetoothConnecting.value = false;
    }
};

// Keyboard Shortcuts: Enter = Print, Esc = Back
const handleKeyDown = (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        printReceipt();
    } else if (e.key === 'Escape') {
        e.preventDefault();
        if (props.sale.sale_type === 'otc') {
            router.visit(route('control.kasir.otc.index'));
        } else {
            router.visit(route('control.kasir.queue'));
        }
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
    if (autoPrint.value) {
        setTimeout(() => {
            printReceipt();
        }, 350);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <Head title="Struk Pembayaran" />

    <div class="max-w-xl mx-auto print-container pb-12">
        <!-- TOOLBAR & PETUNJUK KASIR (NON-PRINT) -->
        <div class="space-y-4 mb-6 no-print">
            <!-- Navigasi Kembali -->
            <div class="flex items-center justify-between">
                <Link 
                    :href="sale.sale_type === 'otc' ? route('control.kasir.otc.index') : route('control.kasir.queue')" 
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>{{ sale.sale_type === 'otc' ? 'Kembali ke Kasir OTC' : 'Kembali ke Antrean Resep' }}</span>
                </Link>

                <span class="text-[11px] text-muted-foreground">
                    Shortcut: <kbd class="px-1.5 py-0.5 rounded bg-muted border border-border font-sans font-semibold text-[10px]">Enter</kbd> Cetak &bull; <kbd class="px-1.5 py-0.5 rounded bg-muted border border-border font-sans font-semibold text-[10px]">Esc</kbd> Kembali
                </span>
            </div>

            <!-- Banner Solusi Pengaturan Cetak (Mencegah PDF Terdownload) -->
            <div class="p-3.5 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 rounded-lg text-xs flex items-start gap-2.5">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="space-y-1">
                    <p class="font-semibold text-emerald-800 dark:text-emerald-300">Tips Cetak Thermal Langsung:</p>
                    <p class="text-emerald-700 dark:text-emerald-400 leading-relaxed text-[11px]">
                        Jika struk masih terunduh sebagai file PDF, pada jendela print browser ganti pilihan <strong>Tujuan (Destination)</strong> dari <em>"Save as PDF"</em> ke printer fisik thermal Anda (misal: <em>POS-58</em> atau printer Bluetooth). Browser akan otomatis mengingat printer tersebut untuk seterusnya.
                    </p>
                </div>
            </div>

            <!-- Pengaturan Ukuran Kertas & Otomatis Cetak -->
            <div class="bg-card border border-border rounded-lg p-3 flex flex-wrap items-center justify-between gap-3 shadow-xs">
                <!-- Ukuran Kertas -->
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-foreground">Ukuran Kertas:</span>
                    <div class="inline-flex rounded-md border border-border p-0.5 bg-muted/40">
                        <button 
                            type="button"
                            @click="setPaperSize('58mm')"
                            class="px-2.5 py-1 text-xs font-medium rounded transition-all cursor-pointer"
                            :class="paperSize === '58mm' ? 'bg-background text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground'"
                        >
                            58 mm (Mini / Bluetooth)
                        </button>
                        <button 
                            type="button"
                            @click="setPaperSize('80mm')"
                            class="px-2.5 py-1 text-xs font-medium rounded transition-all cursor-pointer"
                            :class="paperSize === '80mm' ? 'bg-background text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground'"
                        >
                            80 mm (Desktop POS)
                        </button>
                    </div>
                </div>

                <!-- Toggle Auto-Print -->
                <label class="inline-flex items-center gap-2 text-xs font-medium text-foreground cursor-pointer select-none">
                    <input 
                        type="checkbox" 
                        :checked="autoPrint" 
                        @change="toggleAutoPrint"
                        class="rounded border-input text-emerald-600 focus:ring-emerald-500 w-3.5 h-3.5 cursor-pointer"
                    >
                    <span>Cetak Otomatis Setiap Transaksi</span>
                </label>
            </div>

            <!-- Tombol Aksi Utama -->
            <div class="flex flex-col sm:flex-row gap-2.5">
                <button 
                    type="button"
                    @click="printReceipt" 
                    class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold h-10 px-4 rounded-md transition-all shadow-sm flex items-center justify-center gap-2 text-sm cursor-pointer active:scale-[0.99]"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    <span>Cetak Struk (Thermal / USB)</span>
                </button>

                <!-- Tombol Bluetooth (Muncul jika didukung browser) -->
                <button 
                    v-if="isBluetoothSupported"
                    type="button"
                    :disabled="isBluetoothConnecting"
                    @click="printViaBluetooth" 
                    class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold h-10 px-4 rounded-md transition-all shadow-sm flex items-center justify-center gap-2 text-sm cursor-pointer active:scale-[0.99]"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v18l7-5-7-4 7-4-7-5z" />
                    </svg>
                    <span>{{ isBluetoothConnecting ? 'Menghubungkan...' : 'Cetak via Bluetooth' }}</span>
                </button>
            </div>
        </div>

        <!-- ─── TAMPILAN FISIK STRUK THERMAL ─── -->
        <div 
            class="receipt-box bg-white dark:bg-card border border-border rounded-lg shadow-sm overflow-hidden mx-auto transition-all text-slate-900 dark:text-foreground"
            :class="paperSize === '58mm' ? 'max-w-[340px] size-58mm' : 'max-w-[440px] size-80mm'"
        >
            <div class="receipt-inner p-4 sm:p-5 font-sans text-xs space-y-3 leading-snug">
                <!-- Header Apotek -->
                <div class="text-center pb-2.5 border-b border-dashed border-border receipt-divider">
                    <h1 class="text-base font-bold tracking-tight text-foreground uppercase">APOTEK KINANTHI</h1>
                    <p class="text-[11px] text-muted-foreground mt-0.5">Jl. Dieng No.24, Wonosobo Timur</p>
                    <p class="text-[11px] text-muted-foreground">Wonosobo, Jawa Tengah 56311</p>
                    <p class="text-[11px] text-muted-foreground">Telp: 081238575662</p>
                </div>

                <!-- Info Invoice -->
                <div class="pb-2.5 border-b border-dashed border-border receipt-divider space-y-1 text-[11px]">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">No. Invoice</span>
                        <span class="font-bold text-foreground tabular-nums">{{ sale.sale_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Tanggal</span>
                        <span class="text-foreground tabular-nums">{{ formatDateTime(sale.created_at) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Kasir</span>
                        <span class="text-foreground font-medium">{{ sale.kasir?.name || '-' }}</span>
                    </div>
                    <div v-if="sale.prescription" class="flex justify-between">
                        <span class="text-muted-foreground">Pasien</span>
                        <span class="text-foreground font-semibold">{{ sale.prescription?.patient?.name || '-' }}</span>
                    </div>
                    <div v-if="sale.prescription?.prescription_number" class="flex justify-between">
                        <span class="text-muted-foreground">No. Resep</span>
                        <span class="text-foreground tabular-nums">{{ sale.prescription.prescription_number }}</span>
                    </div>
                </div>

                <!-- Daftar Item Penjualan -->
                <div class="pb-2.5 border-b border-dashed border-border receipt-divider">
                    <table class="w-full text-[11px]">
                        <thead>
                            <tr class="text-left text-muted-foreground border-b border-border/60 pb-1">
                                <th class="pb-1 font-semibold w-full">Item</th>
                                <th class="pb-1 font-semibold text-right whitespace-nowrap pl-2">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr v-for="item in sale.sale_items" :key="item.id">
                                <td class="py-1.5 pr-2">
                                    <p class="font-semibold text-foreground leading-tight">{{ item.item_name }}</p>
                                    <p class="text-[10px] text-muted-foreground tabular-nums">
                                        {{ Number(item.quantity) }} {{ item.unit }} &times; {{ formatRupiah(item.unit_price) }}
                                    </p>
                                </td>
                                <td class="py-1.5 text-right font-semibold text-foreground align-top tabular-nums whitespace-nowrap">
                                    {{ formatRupiah(item.subtotal) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Total & Pembayaran -->
                <div class="space-y-1.5 text-[11px] pt-1">
                    <!-- Subtotal & Diskon (Jika ada) -->
                    <div v-if="Number(sale.discount_amount) > 0" class="space-y-1 pb-1.5 border-b border-dashed border-border receipt-divider">
                        <div class="flex justify-between text-muted-foreground">
                            <span>Subtotal</span>
                            <span class="tabular-nums">{{ formatRupiah(Number(sale.total_amount) + Number(sale.discount_amount)) }}</span>
                        </div>
                        <div class="flex justify-between text-rose-600 font-semibold">
                            <span>Diskon</span>
                            <span class="tabular-nums">-{{ formatRupiah(sale.discount_amount) }}</span>
                        </div>
                    </div>

                    <!-- Total Belanja Utama -->
                    <div class="flex justify-between text-xs font-bold text-foreground py-0.5">
                        <span>TOTAL BELANJA</span>
                        <span class="tabular-nums">{{ formatRupiah(sale.total_amount) }}</span>
                    </div>

                    <!-- Metode & Jumlah Bayar -->
                    <div class="flex justify-between text-muted-foreground">
                        <span>Bayar ({{ String(sale.payment_method || 'TUNAI').toUpperCase() }})</span>
                        <span class="tabular-nums font-medium text-foreground">{{ formatRupiah(sale.paid_amount) }}</span>
                    </div>

                    <!-- Kembalian -->
                    <div class="flex justify-between font-bold text-foreground pt-1 border-t border-border receipt-divider">
                        <span>KEMBALIAN</span>
                        <span class="tabular-nums">{{ formatRupiah(sale.change_amount) }}</span>
                    </div>
                </div>

                <!-- Footer Ucapan Apotek -->
                <div class="pt-3 pb-1 text-center text-[10px] text-muted-foreground space-y-1 border-t border-dashed border-border receipt-divider">
                    <p>Terima kasih atas kunjungan Anda di Apotek Kinanthi.</p>
                    <p class="italic font-medium text-foreground">"Semoga lekas sembuh dan sehat selalu"</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* ─── STYLING KHUSUS CETAK PRINTER THERMAL (58MM & 80MM) ─── */
@media print {
    /* Sembunyikan semua elemen layout, sidebar, topbar, dan toolbar */
    html, body {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
        color: #000 !important;
    }

    body * {
        visibility: hidden !important;
    }

    .print-container, 
    .print-container * {
        visibility: visible !important;
    }

    .print-container {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    .no-print {
        display: none !important;
    }

    .receipt-box {
        border: none !important;
        box-shadow: none !important;
        background: #fff !important;
        color: #000 !important;
        padding: 0 !important;
        margin: 0 auto !important;
    }

    .receipt-inner {
        padding: 2mm 1mm !important;
        background: #fff !important;
        color: #000 !important;
    }

    .receipt-box * {
        color: #000 !important;
        background: transparent !important;
        text-shadow: none !important;
        box-shadow: none !important;
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        font-feature-settings: 'tnum' 1 !important;
    }

    .receipt-divider {
        border-color: #000 !important;
        border-style: dashed !important;
    }

    /* Ukuran Kertas Thermal 58mm */
    .size-58mm {
        width: 48mm !important;
        max-width: 48mm !important;
        font-size: 10px !important;
        line-height: 1.25 !important;
    }

    /* Ukuran Kertas Thermal 80mm */
    .size-80mm {
        width: 72mm !important;
        max-width: 72mm !important;
        font-size: 11px !important;
        line-height: 1.3 !important;
    }

    /* Hilangkan header tanggal, URL, dan nomor halaman bawaan browser */
    @page {
        margin: 0;
        size: auto;
    }
}
</style>
