<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import { ref } from 'vue';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    opname: Object,
});

const formatDateTime = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(d)) : '-';
const formatDate = (d) => d ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(d)) : '-';

const isSaving = ref(false);
const isFinalizing = ref(false);

const getPackaging = (item) => {
    const inc = item.medicine?.latest_active_incoming_stock || item.medicine?.latestActiveIncomingStock;
    const rSmall = Math.max(1, Number(inc?.small_to_mid_ratio) || 1);
    const rMid = Math.max(1, Number(inc?.mid_to_big_ratio) || 1);
    const rBig = (rMid > 1 && rSmall > 1) ? (rMid * rSmall) : (Number(inc?.unit_conversion_factor) || (rMid * rSmall));
    const satuanPrimer = item.medicine?.satuan_stok || item.raw_material?.satuan_ukur || item.raw_material?.base_unit || 'Unit';

    return {
        satuan_input: inc?.satuan_input || null,
        mid_unit: inc?.mid_unit || null,
        satuan_stok: satuanPrimer,
        rBig,
        rSmall,
        hasBig: Boolean(inc?.satuan_input && rBig > 1 && inc?.satuan_input !== satuanPrimer),
        hasMid: Boolean(inc?.mid_unit && rSmall > 1 && inc?.mid_unit !== inc?.satuan_input && inc?.mid_unit !== satuanPrimer),
    };
};

const getStockUnits = (qty, item) => {
    let rem = Number(qty) || 0;
    const pkg = getPackaging(item);
    if (rem <= 0) return [{ qty: 0, unit: pkg.satuan_stok }];

    const res = [];
    if (pkg.hasBig && rem >= pkg.rBig) {
        res.push({ qty: Math.floor(rem / pkg.rBig), unit: pkg.satuan_input });
        rem %= pkg.rBig;
    }
    if (pkg.hasMid && rem >= pkg.rSmall) {
        res.push({ qty: Math.floor(rem / pkg.rSmall), unit: pkg.mid_unit });
        rem %= pkg.rSmall;
    }
    if (rem > 0) {
        res.push({ qty: Number(rem.toFixed(2)), unit: pkg.satuan_stok });
    }
    return res.length ? res : [{ qty: 0, unit: pkg.satuan_stok }];
};

// Decompose quantity into big, mid, small based on packaging ratios
const decomposeStock = (qty, item) => {
    let rem = Number(qty) || 0;
    const pkg = getPackaging(item);
    let qBig = 0;
    let qMid = 0;
    let qSmall = 0;

    if (pkg.hasBig && rem >= pkg.rBig) {
        qBig = Math.floor(rem / pkg.rBig);
        rem %= pkg.rBig;
    }
    if (pkg.hasMid && rem >= pkg.rSmall) {
        qMid = Math.floor(rem / pkg.rSmall);
        rem %= pkg.rSmall;
    }
    qSmall = Number(rem.toFixed(2));
    return { qBig, qMid, qSmall };
};

const itemsList = ref(
    (props.opname.items || []).map(item => {
        const decomp = decomposeStock(item.physical_stock, item);
        return {
            id: item.id,
            medicine_id: item.medicine_id,
            raw_material_id: item.raw_material_id,
            system_stock: Number(item.system_stock) || 0,
            physical_stock: Number(item.physical_stock) || 0,
            difference: Number(item.difference) || 0,
            reason: item.reason || '',
            qty_besar: decomp.qBig,
            qty_menengah: decomp.qMid,
            qty_kecil: decomp.qSmall,
            raw_item: item,
        };
    })
);

const onRowInput = (row) => {
    const pkg = getPackaging(row.raw_item);
    const qBig = (pkg.hasBig ? Number(row.qty_besar) || 0 : 0) * pkg.rBig;
    const qMid = (pkg.hasMid ? Number(row.qty_menengah) || 0 : 0) * pkg.rSmall;
    const qSmall = Number(row.qty_kecil) || 0;
    row.physical_stock = Number((qBig + qMid + qSmall).toFixed(2));
    row.difference = Number((row.physical_stock - row.system_stock).toFixed(2));
    if (Math.abs(row.difference) <= 0.001) {
        row.reason = '';
    } else if (!row.reason) {
        row.reason = 'Barang Hilang / Rusak';
    }
};

const saveDraft = () => {
    isSaving.value = true;
    router.post(route('control.admin.stock-opname.adjust', props.opname.id), {
        action: 'save_draft',
        items: itemsList.value.map(row => ({
            id: row.id,
            physical_stock: row.physical_stock,
            reason: row.reason,
        }))
    }, {
        preserveScroll: true,
        onFinish: () => { isSaving.value = false; }
    });
};

const finalizeOpname = () => {
    const unreasoned = itemsList.value.filter(r => Math.abs(r.difference) > 0.001 && !r.reason);
    if (unreasoned.length > 0) {
        alert('Terdapat item dengan selisih fisik yang belum memiliki alasan selisih. Silakan pilih alasan selisih terlebih dahulu.');
        return;
    }
    if (confirm('Anda yakin ingin memfinalisasi opname ini dan menyesuaikan stok aktual di sistem? Tindakan ini akan memperbarui saldo inventori gudang dan tidak dapat dibatalkan.')) {
        isFinalizing.value = true;
        router.post(route('control.admin.stock-opname.adjust', props.opname.id), {
            action: 'finalize',
            items: itemsList.value.map(row => ({
                id: row.id,
                physical_stock: row.physical_stock,
                reason: row.reason,
            }))
        }, {
            preserveScroll: true,
            onFinish: () => { isFinalizing.value = false; }
        });
    }
};
</script>

<template>
    <Head title="Detail Stock Opname" />

    <div class="space-y-6">
        <!-- Back Navigation Link -->
        <div>
            <Link :href="route('control.admin.stock-opname.index')" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors font-medium">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Kembali ke Daftar Opname
            </Link>
        </div>

        <!-- Header Info Card -->
        <div class="bg-card rounded-lg border border-border shadow-sm p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-foreground tracking-tight">
                        Opname {{ formatDate(opname.opname_date) }}
                    </h2>
                    <span v-if="opname.status === 'completed'" class="inline-flex items-center gap-1.5 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 px-2.5 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wider">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Selesai / Disesuaikan
                    </span>
                    <span v-else class="inline-flex items-center gap-1.5 bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 px-2.5 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wider">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Draft Lembar Kerja
                    </span>
                </div>
                <div class="text-xs text-muted-foreground space-y-0.5">
                    <p><span class="font-medium text-foreground">Dibuat Oleh:</span> {{ opname.creator?.name || '-' }}</p>
                    <p v-if="opname.catatan"><span class="font-medium text-foreground">Keterangan:</span> {{ opname.catatan }}</p>
                    <p v-if="opname.status === 'completed' && opname.finalizer"><span class="font-medium text-foreground">Difinalisasi Oleh:</span> {{ opname.finalizer.name }} ({{ formatDateTime(opname.finalized_at) }})</p>
                </div>
            </div>
            
            <div v-if="opname.status === 'draft'" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                <button @click="saveDraft" :disabled="isSaving || isFinalizing" class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-md border border-input bg-background hover:bg-accent text-foreground text-xs font-medium shadow-2xs transition-colors disabled:opacity-50">
                    <svg v-if="isSaving" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    <svg v-else class="w-3.5 h-3.5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                    Simpan Draf
                </button>
                <button @click="finalizeOpname" :disabled="isSaving || isFinalizing" class="inline-flex items-center justify-center gap-1.5 h-9 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium shadow-sm transition-colors disabled:opacity-50">
                    <svg v-if="isFinalizing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    Finalisasi & Sesuaikan Stok
                </button>
            </div>
        </div>

        <!-- Detail Items Table -->
        <div class="bg-card rounded-lg border border-border shadow-sm overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-muted/50 border-b border-border h-10">
                        <th class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Nama Item</th>
                        <th class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Batch & ED</th>
                        <th class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right whitespace-nowrap">Stok Sistem</th>
                        <th class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right min-w-[350px]">Qty Fisik</th>
                        <th class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right whitespace-nowrap">Selisih</th>
                        <th class="py-2 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider min-w-[220px]">Alasan Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-if="itemsList.length === 0">
                        <td colspan="6" class="py-8 px-3 text-center text-muted-foreground text-xs font-medium">
                            Tidak ada item dalam lembar kerja opname ini.
                        </td>
                    </tr>
                    <tr v-for="row in itemsList" :key="row.id" class="hover:bg-muted/30 transition-colors">
                        <!-- Item Name & Type -->
                        <td class="py-2.5 px-3 align-middle">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-semibold text-foreground text-xs">
                                    {{ row.raw_item.medicine?.nama_obat || row.raw_item.raw_material?.nama_bahan || '-' }}
                                </span>
                                <span v-if="row.medicine_id" class="text-[10px] text-muted-foreground uppercase font-medium tracking-wide">
                                    Obat Jadi
                                </span>
                                <span v-else-if="row.raw_material_id" class="text-[10px] text-amber-600 dark:text-amber-400 uppercase font-medium tracking-wide">
                                    Bahan Baku
                                </span>
                            </div>
                        </td>

                        <!-- Batch & ED -->
                        <td class="py-2.5 px-3 align-middle text-xs">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-mono font-semibold text-foreground px-1.5 py-0.5 rounded bg-muted text-[11px] border border-border">
                                    {{ row.raw_item.batch_no || row.raw_item.product_batch?.batch_no || row.raw_item.raw_material_batch?.batch_no || '-' }}
                                </span>
                                <span v-if="row.raw_item.product_batch?.expired_date || row.raw_item.raw_material_batch?.expired_date" class="text-[11px] text-muted-foreground font-normal">
                                    ED: {{ formatDate(row.raw_item.product_batch?.expired_date || row.raw_item.raw_material_batch?.expired_date) }}
                                </span>
                            </div>
                        </td>

                        <!-- System Stock -->
                        <td class="py-2.5 px-3 align-middle text-right">
                            <div class="flex flex-col items-end gap-0.5">
                                <span class="text-xs font-semibold text-foreground tabular-nums">
                                    {{ Number(row.system_stock).toLocaleString('id-ID') }}
                                    <span class="text-[10px] text-muted-foreground font-normal lowercase">{{ getPackaging(row.raw_item).satuan_stok }}</span>
                                </span>
                                <div v-if="getPackaging(row.raw_item).hasBig || getPackaging(row.raw_item).hasMid" class="flex items-center gap-1 flex-wrap justify-end">
                                    <span v-for="(u, uIdx) in getStockUnits(row.system_stock, row.raw_item)" :key="uIdx" class="text-[10px] text-muted-foreground font-medium">
                                        {{ u.qty }} {{ u.unit }}<span v-if="uIdx < getStockUnits(row.system_stock, row.raw_item).length - 1" class="text-slate-300 dark:text-slate-600 ml-1">·</span>
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- Physical Stock Input / Display -->
                        <td class="py-2.5 px-3 align-middle text-right">
                            <!-- Editable in Draft -->
                            <div v-if="opname.status === 'draft'">
                                <div v-if="row.medicine_id && (getPackaging(row.raw_item).hasBig || getPackaging(row.raw_item).hasMid)" class="flex items-center justify-end gap-2">
                                    <!-- Big Unit -->
                                    <div v-if="getPackaging(row.raw_item).hasBig" class="flex items-center border border-border/80 bg-muted/40 hover:bg-muted/60 focus-within:bg-background focus-within:border-primary/60 focus-within:ring-2 focus-within:ring-primary/10 rounded-md transition-all shadow-2xs w-28 sm:w-32">
                                        <input type="number" v-model.number="row.qty_besar" @input="onRowInput(row)" min="0" class="w-full h-8 px-2.5 text-sm font-bold text-right text-foreground bg-transparent border-0 focus:outline-none focus:ring-0 tabular-nums no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none placeholder:text-muted-foreground/40" placeholder="0">
                                        <span class="pr-2.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground select-none shrink-0">
                                            {{ getPackaging(row.raw_item).satuan_input }}
                                        </span>
                                    </div>
                                    <!-- Mid Unit -->
                                    <div v-if="getPackaging(row.raw_item).hasMid" class="flex items-center border border-border/80 bg-muted/40 hover:bg-muted/60 focus-within:bg-background focus-within:border-primary/60 focus-within:ring-2 focus-within:ring-primary/10 rounded-md transition-all shadow-2xs w-28 sm:w-32">
                                        <input type="number" v-model.number="row.qty_menengah" @input="onRowInput(row)" min="0" class="w-full h-8 px-2.5 text-sm font-bold text-right text-foreground bg-transparent border-0 focus:outline-none focus:ring-0 tabular-nums no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none placeholder:text-muted-foreground/40" placeholder="0">
                                        <span class="pr-2.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground select-none shrink-0">
                                            {{ getPackaging(row.raw_item).mid_unit }}
                                        </span>
                                    </div>
                                    <!-- Small Unit -->
                                    <div class="flex items-center border border-border/80 bg-muted/40 hover:bg-muted/60 focus-within:bg-background focus-within:border-primary/60 focus-within:ring-2 focus-within:ring-primary/10 rounded-md transition-all shadow-2xs w-28 sm:w-32">
                                        <input type="number" v-model.number="row.qty_kecil" @input="onRowInput(row)" min="0" step="0.01" class="w-full h-8 px-2.5 text-sm font-bold text-right text-foreground bg-transparent border-0 focus:outline-none focus:ring-0 tabular-nums no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none placeholder:text-muted-foreground/40" placeholder="0">
                                        <span class="pr-2.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground select-none shrink-0">
                                            {{ getPackaging(row.raw_item).satuan_stok }}
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Single Unit Input (Raw Material or simple medicine) -->
                                <div v-else class="flex items-center justify-end">
                                    <div class="flex items-center border border-border/80 bg-muted/40 hover:bg-muted/60 focus-within:bg-background focus-within:border-primary/60 focus-within:ring-2 focus-within:ring-primary/10 rounded-md transition-all shadow-2xs w-36">
                                        <input type="number" v-model.number="row.qty_kecil" @input="onRowInput(row)" min="0" step="0.01" class="w-full h-8 px-3 text-sm font-bold text-right text-foreground bg-transparent border-0 focus:outline-none focus:ring-0 tabular-nums no-spinner [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none placeholder:text-muted-foreground/40" placeholder="0">
                                        <span class="pr-3 text-[11px] font-bold uppercase tracking-wider text-muted-foreground select-none shrink-0">
                                            {{ getPackaging(row.raw_item).satuan_stok }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Total Equivalent Base Unit (Crisp & Ergonomic) -->
                                <div class="text-[11px] text-right font-medium text-muted-foreground mt-1 tabular-nums flex items-center justify-end gap-1">
                                    <span class="text-muted-foreground/70">=</span>
                                    <span class="font-bold text-foreground">{{ Number(row.physical_stock).toLocaleString('id-ID') }}</span>
                                    <span class="text-[10px] uppercase tracking-wide text-muted-foreground font-semibold">{{ getPackaging(row.raw_item).satuan_stok }}</span>
                                </div>
                            </div>

                            <!-- Readonly in Completed -->
                            <div v-else class="flex flex-col items-end gap-0.5">
                                <span class="text-xs font-semibold text-foreground tabular-nums">
                                    {{ Number(row.physical_stock).toLocaleString('id-ID') }}
                                    <span class="text-[10px] text-muted-foreground font-normal lowercase">{{ getPackaging(row.raw_item).satuan_stok }}</span>
                                </span>
                                <div v-if="getPackaging(row.raw_item).hasBig || getPackaging(row.raw_item).hasMid" class="flex items-center gap-1 flex-wrap justify-end">
                                    <span v-for="(u, uIdx) in getStockUnits(row.physical_stock, row.raw_item)" :key="uIdx" class="text-[10px] text-muted-foreground font-medium">
                                        {{ u.qty }} {{ u.unit }}<span v-if="uIdx < getStockUnits(row.physical_stock, row.raw_item).length - 1" class="text-slate-300 dark:text-slate-600 ml-1">·</span>
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- Difference -->
                        <td class="py-2.5 px-3 align-middle text-right text-xs">
                            <span v-if="row.difference < 0" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold text-destructive bg-destructive/10 border border-destructive/20 tabular-nums">
                                {{ Number(row.difference).toLocaleString('id-ID') }}
                            </span>
                            <span v-else-if="row.difference > 0" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 tabular-nums">
                                +{{ Number(row.difference).toLocaleString('id-ID') }}
                            </span>
                            <span v-else class="text-muted-foreground tabular-nums font-medium text-xs">0</span>
                        </td>

                        <!-- Discrepancy Reason -->
                        <td class="py-2.5 px-3 align-middle text-xs">
                            <!-- Draft Reason Selector -->
                            <div v-if="opname.status === 'draft'">
                                <select v-if="Math.abs(row.difference) > 0.001" v-model="row.reason" class="w-full min-w-[210px] h-8 px-2.5 py-1 text-xs border border-amber-300 dark:border-amber-700 bg-amber-50/60 dark:bg-amber-950/30 rounded-md text-foreground font-medium focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring transition-colors leading-normal">
                                    <option value="" disabled>Pilih Alasan Selisih...</option>
                                    <option value="Barang Hilang / Rusak">Barang Hilang / Rusak</option>
                                    <option value="Salah Catat Satuan Eceran">Salah Catat Satuan Eceran</option>
                                    <option value="Faktur Belum Terinput">Faktur Belum Terinput</option>
                                    <option value="Penjualan Kasir Terlewat Input">Penjualan Kasir Terlewat Input</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                                <span v-else class="text-muted-foreground text-center block text-xs">-</span>
                            </div>

                            <!-- Completed Reason Display -->
                            <div v-else>
                                <span v-if="row.reason" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 leading-tight">
                                    {{ row.reason }}
                                </span>
                                <span v-else class="text-muted-foreground text-center block text-xs">-</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
