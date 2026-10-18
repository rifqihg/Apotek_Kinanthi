<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import ControlLayout from '@/Layouts/ControlLayout.vue';
import axios from 'axios';

defineOptions({ layout: ControlLayout });

const props = defineProps({
    patients: Array,
    medicines: Array,
    compounded_products: Array
});

const form = useForm({
    patient_id: '',
    patient_weight_kg: '',
    patient_age_years: '',
    doctor_name: '',
    doctor_sip: '',
    clinic_name: '',
    prescription_date: new Date().toISOString().split('T')[0],
    notes: '',
    screening_notes: '',
    verified_hardcopy: false,
    items: [
        {
            item_type: 'obat_jadi',
            medicine_id: '',
            compounded_product_id: '',
            custom_formula_name: '',
            custom_formula_notes: '',
            quantity: 1,
            unit: '',
            dosage_instruction: '',
            usage_instructions: '',
            item_notes: '',
            is_iter: false,
            iter_count: 0,
            racikan_source: 'master' // local ui state: 'master' or 'baru'
        }
    ]
});

const pastPrescriptions = ref([]);
const isLoadingHistory = ref(false);
const showHistoryModal = ref(false);
const copySuccessAlert = ref('');

// --- Pasien Searchable Combobox State ---
const localPatients = ref([...(props.patients || [])]);
const patientSearch = ref('');
const isPatientDropdownOpen = ref(false);
const isSearchingPatients = ref(false);
const selectedPatient = computed(() => localPatients.value.find(p => p.id === form.patient_id));

let patientSearchTimer = null;
watch(patientSearch, (newVal) => {
    if (patientSearchTimer) clearTimeout(patientSearchTimer);
    const q = newVal.trim();
    if (!q) return;

    patientSearchTimer = setTimeout(async () => {
        try {
            isSearchingPatients.value = true;
            const res = await fetch(`/control/apoteker/prescriptions/search-patients?q=${encodeURIComponent(q)}`);
            if (res.ok) {
                const data = await res.json();
                if (Array.isArray(data) && data.length > 0) {
                    const existingIds = new Set(localPatients.value.map(p => p.id));
                    const newItems = data.filter(p => !existingIds.has(p.id));
                    if (newItems.length > 0) {
                        localPatients.value.push(...newItems);
                    }
                }
            }
        } catch (err) {
            console.error('Error searching patients:', err);
        } finally {
            isSearchingPatients.value = false;
        }
    }, 250);
});

watch(() => props.patients, (val) => {
    if (val) localPatients.value = [...val];
});

const filteredPatients = computed(() => {
    const q = patientSearch.value.toLowerCase().trim();
    if (!q) return localPatients.value.slice(0, 50);
    return localPatients.value
        .filter(p => p.name && p.name.toLowerCase().includes(q))
        .slice(0, 50);
});

const selectPatient = (patient) => {
    form.patient_id = patient.id;
    isPatientDropdownOpen.value = false;
    patientSearch.value = '';
};

const clearPatient = () => {
    form.patient_id = '';
    patientSearch.value = '';
};

// --- Obat Jadi (Medicine) Searchable Combobox State ---
const localMedicines = ref([...(props.medicines || [])]);
const activeMedDropdownIndex = ref(null);
const medicineSearch = ref('');
const isSearchingMedicines = ref(false);
const getMedicine = (id) => localMedicines.value?.find(m => m.id === id);

let medSearchTimer = null;
watch(medicineSearch, (newVal) => {
    if (medSearchTimer) clearTimeout(medSearchTimer);
    const q = newVal.trim();
    if (!q) return;

    medSearchTimer = setTimeout(async () => {
        try {
            isSearchingMedicines.value = true;
            const res = await fetch(`/control/apoteker/prescriptions/search-medicines?q=${encodeURIComponent(q)}`);
            if (res.ok) {
                const data = await res.json();
                if (Array.isArray(data) && data.length > 0) {
                    const existingIds = new Set(localMedicines.value.map(m => m.id));
                    const newItems = data.filter(m => !existingIds.has(m.id));
                    if (newItems.length > 0) {
                        localMedicines.value.push(...newItems);
                    }
                }
            }
        } catch (err) {
            console.error('Error searching medicines:', err);
        } finally {
            isSearchingMedicines.value = false;
        }
    }, 250);
});

watch(() => props.medicines, (val) => {
    if (val) localMedicines.value = [...val];
});

const filteredMedicines = computed(() => {
    const q = medicineSearch.value.toLowerCase().trim();
    const list = localMedicines.value || [];
    if (!q) return list.slice(0, 40);
    return list
        .filter(m => (m.nama_obat && m.nama_obat.toLowerCase().includes(q)) || (m.kode_obat && m.kode_obat.toLowerCase().includes(q)))
        .slice(0, 50);
});

const openMedicineDropdown = (index) => {
    if (activeMedDropdownIndex.value === index) {
        activeMedDropdownIndex.value = null;
    } else {
        activeMedDropdownIndex.value = index;
        medicineSearch.value = '';
    }
};

const selectMedicine = (index, med) => {
    const item = form.items[index];
    item.medicine_id = med.id;
    item.unit = med.satuan_stok || '';
    activeMedDropdownIndex.value = null;
    medicineSearch.value = '';
};

const clearMedicine = (index) => {
    const item = form.items[index];
    item.medicine_id = '';
    item.unit = '';
};

// --- Aturan Pakai (Sig) State & Auto-Learning ---
const defaultSignas = [
    '3x1 Sesudah Makan',
    '2x1 Sesudah Makan',
    '1x1 Pagi Hari',
    '1x1 Malam Hari',
    '3x1 Sendok Teh (5ml)',
    'Oles Tipis 2x Sehari',
    'Bila Demam / Perlu',
    '3x1 Sebelum Makan',
    '1x1 Sebelum Tidur',
    '2x1 Tetes Mata Kanan/Kiri'
];

const savedCustomSignas = ref([]);
try {
    const stored = localStorage.getItem('kinanthi_custom_signas');
    if (stored) {
        savedCustomSignas.value = JSON.parse(stored);
    }
} catch (e) {
    console.warn('Gagal membaca custom signas dari localStorage', e);
}

const allSignas = computed(() => {
    const set = new Set([...defaultSignas, ...savedCustomSignas.value]);
    return Array.from(set);
});

const activeSignaDropdownIndex = ref(null);
const signaSearch = ref('');
const newSignaInput = ref('');
const isAddingNewSigna = ref(false);

const filteredSignas = computed(() => {
    const q = signaSearch.value.toLowerCase().trim();
    if (!q) return allSignas.value;
    return allSignas.value.filter(s => s.toLowerCase().includes(q));
});

const toggleSignaDropdown = (index) => {
    if (activeSignaDropdownIndex.value === index) {
        activeSignaDropdownIndex.value = null;
    } else {
        activeSignaDropdownIndex.value = index;
        signaSearch.value = '';
        isAddingNewSigna.value = false;
        newSignaInput.value = '';
    }
};

const selectSigna = (item, sig) => {
    item.usage_instructions = sig;
    activeSignaDropdownIndex.value = null;
    signaSearch.value = '';
};

const saveAndApplyCustomSigna = (item) => {
    const custom = newSignaInput.value.trim();
    if (!custom) return;

    if (!allSignas.value.includes(custom)) {
        savedCustomSignas.value.push(custom);
        try {
            localStorage.setItem('kinanthi_custom_signas', JSON.stringify(savedCustomSignas.value));
        } catch (e) {
            console.warn('Gagal menyimpan custom signas ke localStorage', e);
        }
    }

    item.usage_instructions = custom;
    newSignaInput.value = '';
    isAddingNewSigna.value = false;
    activeSignaDropdownIndex.value = null;
    signaSearch.value = '';
};

// Global click outside listener to close floating dropdowns
const handleGlobalClick = (e) => {
    if (!e.target.closest('.patient-dropdown-container')) {
        isPatientDropdownOpen.value = false;
    }
    if (!e.target.closest('.medicine-dropdown-container')) {
        activeMedDropdownIndex.value = null;
    }
    if (!e.target.closest('.signa-dropdown-container')) {
        activeSignaDropdownIndex.value = null;
    }
};

onMounted(() => {
    document.addEventListener('click', handleGlobalClick);
});

onUnmounted(() => {
    document.removeEventListener('click', handleGlobalClick);
});

const fetchPatientHistory = async (patientId) => {
    if (!patientId) {
        pastPrescriptions.value = [];
        return;
    }
    isLoadingHistory.value = true;
    try {
        const { data } = await axios.get(route('control.apoteker.prescriptions.patient-history'), {
            params: { patient_id: patientId }
        });
        pastPrescriptions.value = data.prescriptions || [];
    } catch (err) {
        console.error('Gagal mengambil riwayat resep:', err);
        pastPrescriptions.value = [];
    } finally {
        isLoadingHistory.value = false;
    }
};

watch(() => form.patient_id, (newVal) => {
    copySuccessAlert.value = '';
    fetchPatientHistory(newVal);
});

const applyPastPrescription = (rx) => {
    if (!rx) return;
    
    // Copy administrative details
    form.doctor_name = rx.doctor_name || '';
    form.doctor_sip = rx.doctor_sip || '';
    form.clinic_name = rx.clinic_name || '';
    if (rx.patient_weight_kg) form.patient_weight_kg = rx.patient_weight_kg;
    if (rx.patient_age_years) form.patient_age_years = rx.patient_age_years;
    if (rx.notes) form.notes = rx.notes;
    if (rx.screening_notes) form.screening_notes = rx.screening_notes;
    
    // Copy items if available
    if (rx.prescription_items && rx.prescription_items.length > 0) {
        form.items = rx.prescription_items.map(it => ({
            item_type: it.item_type || 'obat_jadi',
            medicine_id: it.medicine_id || '',
            compounded_product_id: it.compounded_product_id || '',
            custom_formula_name: it.custom_formula_name || '',
            custom_formula_notes: it.custom_formula_notes || '',
            quantity: Number(it.quantity) || 1,
            unit: it.unit || '',
            dosage_instruction: it.dosage_instruction || '',
            usage_instructions: it.usage_instructions || '',
            item_notes: it.item_notes || '',
            is_iter: Boolean(it.is_iter),
            iter_count: Number(it.iter_count) || 0,
            racikan_source: it.compounded_product_id ? 'master' : 'baru'
        }));
    }
    
    showHistoryModal.value = false;
    copySuccessAlert.value = `Data resep berhasil disalin dari resep #${rx.prescription_number || ''} (${rx.prescription_date ? new Date(rx.prescription_date).toLocaleDateString('id-ID') : ''})`;
};

const hasControlledMedicine = computed(() => {
    return form.items.some(item => {
        if (item.item_type === 'obat_jadi' && item.medicine_id) {
            const med = props.medicines?.find(m => m.id === item.medicine_id);
            return med && (med.classification === 'narkotika' || med.classification === 'psikotropika');
        }
        return false;
    });
});

const showPatientModal = ref(false);
const patientForm = useForm({
    name: '',
    phone: '',
    notes: ''
});

const handleProductChange = (index) => {
    const item = form.items[index];
    const prod = props.compounded_products?.find(p => p.id === item.compounded_product_id);
    if (prod) item.unit = prod.satuan || '';
};

const handleItemTypeChange = (index) => {
    const item = form.items[index];
    item.medicine_id = '';
    item.compounded_product_id = '';
    item.custom_formula_name = '';
    item.unit = '';
};

const addItem = () => {
    form.items.push({
        item_type: 'obat_jadi',
        medicine_id: '',
        compounded_product_id: '',
        custom_formula_name: '',
        custom_formula_notes: '',
        quantity: 1,
        unit: '',
        dosage_instruction: '',
        usage_instructions: '',
        item_notes: '',
        is_iter: false,
        iter_count: 0,
        racikan_source: 'master'
    });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const submit = () => {
    if (hasControlledMedicine.value && !form.verified_hardcopy) {
        alert('Resep memuat obat Narkotika/Psikotropika. Anda wajib mencentang konfirmasi verifikasi fisik lembar resep asli dokter sesuai Permenkes No. 73/2016.');
        return;
    }
    form.post(route('control.apoteker.prescriptions.store'), {
        preserveScroll: true,
    });
};

const savePatient = async () => {
    try {
        const { data } = await axios.post(route('control.apoteker.patients.store'), {
            name: patientForm.name,
            phone: patientForm.phone,
            notes: patientForm.notes
        });
        localPatients.value.push(data);
        form.patient_id = data.id;
        showPatientModal.value = false;
        patientSearch.value = '';
        isPatientDropdownOpen.value = false;
        patientForm.reset();
    } catch (err) {
        alert('Gagal menyimpan pasien. Cek kembali form Anda.');
    }
};
</script>

<template>
    <Head title="Tambah Resep Baru" />
    <div class="space-y-6 max-w-5xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-foreground tracking-tight">Resep Baru</h1>
                <p class="text-sm text-muted-foreground mt-1">Masukan data resep dari dokter sesuai Permenkes No. 73/2016</p>
            </div>
            <Link :href="route('control.apoteker.prescriptions.index')" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
                Batal
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Data Pasien & Dokter -->
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h2 class="text-foreground font-semibold text-sm">Informasi Administratif &amp; Klinis Resep</h2>
                    <span class="text-xs text-muted-foreground">Permenkes RI No. 73/2016</span>
                </div>

                <!-- Alert Khusus Obat Terkontrol (Narkotika/Psikotropika) -->
                <div v-if="hasControlledMedicine" class="p-3 bg-red-500/10 border border-red-500/30 rounded-md flex items-start gap-2.5 text-xs text-red-700 dark:text-red-400">
                    <svg class="w-4 h-4 text-red-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <strong class="font-bold uppercase tracking-wider block">Peringatan Regulasi Narkotika &amp; Psikotropika</strong>
                        Resep memuat obat golongan khusus. Nomor SIP Dokter wajib dicantumkan dan fisik resep asli wajib diverifikasi sebelum pemrosesan.
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <!-- Pasien (Searchable Combobox) -->
                    <div class="md:col-span-6">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">
                            Pasien <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-2">
                            <div class="relative flex-1 patient-dropdown-container">
                                <!-- Trigger Button -->
                                <button
                                    type="button"
                                    @click.stop="isPatientDropdownOpen = !isPatientDropdownOpen"
                                    class="w-full h-9 px-3 bg-background border border-input rounded-md text-sm text-left flex items-center justify-between focus:ring-1 focus:ring-ring outline-none transition-colors cursor-pointer"
                                    :class="{ 'ring-1 ring-ring': isPatientDropdownOpen }"
                                >
                                    <span v-if="selectedPatient" class="text-foreground font-medium truncate">
                                        {{ selectedPatient.name }}
                                    </span>
                                    <span v-else class="text-muted-foreground">
                                        Pilih Pasien... (klik untuk mencari)
                                    </span>
                                    <div class="flex items-center gap-1.5 shrink-0 text-muted-foreground">
                                        <button
                                            v-if="form.patient_id"
                                            type="button"
                                            @click.stop="clearPatient"
                                            class="p-0.5 hover:text-foreground hover:bg-muted rounded text-xs"
                                            title="Hapus pilihan pasien"
                                        >
                                            ✕
                                        </button>
                                        <svg class="w-4 h-4 transition-transform duration-150" :class="{ 'rotate-180': isPatientDropdownOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </button>

                                <!-- Floating Dropdown List -->
                                <div
                                    v-if="isPatientDropdownOpen"
                                    class="absolute left-0 right-0 top-full mt-1 bg-card border border-border rounded-lg shadow-xl z-30 p-2 space-y-1.5 animate-in fade-in-50 zoom-in-95 duration-100"
                                >
                                    <div class="relative">
                                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        <input
                                            v-model="patientSearch"
                                            type="text"
                                            placeholder="Ketik nama pasien untuk mencari..."
                                            autofocus
                                            class="w-full h-8 pl-8 pr-3 bg-muted/50 border border-input rounded-md text-xs text-foreground placeholder-muted-foreground focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                        />
                                    </div>

                                    <div class="max-h-52 overflow-y-auto space-y-0.5 pr-0.5">
                                        <button
                                            v-for="p in filteredPatients"
                                            :key="p.id"
                                            type="button"
                                            @click="selectPatient(p)"
                                            class="w-full text-left px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors flex items-center justify-between cursor-pointer"
                                            :class="form.patient_id === p.id ? 'bg-emerald-600 text-white font-semibold' : 'hover:bg-muted text-foreground'"
                                        >
                                            <span class="truncate">{{ p.name }}</span>
                                            <span v-if="form.patient_id === p.id" class="text-xs">✓</span>
                                        </button>

                                        <div v-if="filteredPatients.length === 0" class="p-3 text-center text-xs text-muted-foreground space-y-1.5">
                                            <p v-if="isSearchingPatients">Sedang mencari pasien di database...</p>
                                            <template v-else>
                                                <p>Tidak ditemukan pasien "{{ patientSearch }}".</p>
                                                <button
                                                    type="button"
                                                    @click="patientForm.name = patientSearch; showPatientModal = true; isPatientDropdownOpen = false"
                                                    class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline cursor-pointer"
                                                >
                                                    + Tambah sebagai Pasien Baru
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="button" @click="showPatientModal = true" class="bg-secondary hover:bg-secondary/80 text-secondary-foreground border border-border px-3 h-9 rounded-md text-xs font-medium whitespace-nowrap transition-colors cursor-pointer">
                                Pasien Baru +
                            </button>
                        </div>
                        <input type="hidden" :value="form.patient_id" required />
                        <p v-if="form.errors.patient_id" class="text-red-600 text-xs mt-1">{{ form.errors.patient_id }}</p>
                    </div>

                    <!-- Umur Pasien -->
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Umur Pasien (Thn)</label>
                        <input v-model.number="form.patient_age_years" type="number" min="0" max="150" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Contoh: 8" />
                    </div>

                    <!-- Berat Badan Pasien -->
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Berat Badan (kg)</label>
                        <input v-model.number="form.patient_weight_kg" type="number" step="0.1" min="0" max="300" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Contoh: 22.5" />
                    </div>

                    <!-- Banner Salin dari Resep Sebelumnya -->
                    <div v-if="copySuccessAlert" class="md:col-span-12 p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-md flex items-center justify-between gap-3 text-xs text-emerald-800 dark:text-emerald-300">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>{{ copySuccessAlert }}</span>
                        </div>
                        <button type="button" @click="copySuccessAlert = ''" class="text-emerald-700 dark:text-emerald-400 hover:text-emerald-900 font-bold cursor-pointer">&times;</button>
                    </div>

                    <div v-else-if="pastPrescriptions.length > 0" class="md:col-span-12 p-3 bg-slate-50 dark:bg-muted/40 border border-slate-200 dark:border-border rounded-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3 transition-all">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 flex items-center justify-center shrink-0 font-bold text-xs">
                                📋
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-slate-800 dark:text-foreground truncate">
                                    Ditemukan {{ pastPrescriptions.length }} riwayat resep pasien ini
                                </p>
                                <p class="text-[11px] text-muted-foreground truncate">
                                    Terakhir: Resep #{{ pastPrescriptions[0].prescription_number }} &bull; {{ pastPrescriptions[0].doctor_name }} ({{ pastPrescriptions[0].prescription_date ? new Date(pastPrescriptions[0].prescription_date).toLocaleDateString('id-ID') : '-' }})
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button 
                                type="button" 
                                @click="applyPastPrescription(pastPrescriptions[0])" 
                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs font-semibold shadow-xs flex items-center gap-1.5 cursor-pointer transition-colors"
                            >
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                Salin Resep Terakhir
                            </button>
                            <button 
                                v-if="pastPrescriptions.length > 1" 
                                type="button" 
                                @click="showHistoryModal = true" 
                                class="px-2.5 py-1.5 bg-background border border-input text-foreground rounded-md text-xs font-medium hover:bg-muted cursor-pointer transition-colors"
                            >
                                Riwayat Lainnya ({{ pastPrescriptions.length }})
                            </button>
                        </div>
                    </div>

                    <!-- Nama Dokter -->
                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Dokter <span class="text-red-500">*</span></label>
                        <input v-model="form.doctor_name" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="dr. ..." required />
                    </div>

                    <!-- SIP Dokter -->
                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" :class="hasControlledMedicine ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-foreground'">
                            SIP Dokter 
                            <span v-if="hasControlledMedicine" class="text-rose-600 dark:text-rose-400 font-bold ml-1">* [WAJIB REGULASI SIPNAP]</span>
                            <span v-else class="text-muted-foreground font-normal ml-0.5">(Opsional)</span>
                        </label>
                        <input 
                            v-model="form.doctor_sip" 
                            type="text" 
                            :required="hasControlledMedicine"
                            class="w-full bg-background border rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors"
                            :class="hasControlledMedicine ? 'border-amber-400 dark:border-amber-600 focus:border-rose-500 ring-1 ring-rose-500/20' : 'border-input'"
                            :placeholder="hasControlledMedicine ? 'Wajib diisi sesuai resep dokter (Cth: 446/123/SIP.D/2026)' : 'Contoh: 446/123/SIP.D/2026'" 
                        />
                        <p v-if="form.errors.doctor_sip" class="text-rose-600 text-xs mt-1 font-semibold">{{ form.errors.doctor_sip }}</p>
                    </div>

                    <!-- Tanggal Resep -->
                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Tanggal Resep <span class="text-red-500">*</span></label>
                        <input v-model="form.prescription_date" type="date" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors cursor-pointer" required />
                    </div>

                    <!-- Klinik / RS Asal -->
                    <div class="md:col-span-6">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Klinik/RS Asal</label>
                        <input v-model="form.clinic_name" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Puskesmas / RSUD..." />
                    </div>

                    <!-- Catatan Tambahan -->
                    <div class="md:col-span-6">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Catatan Umum</label>
                        <input v-model="form.notes" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Catatan tambahan pasien..." />
                    </div>

                    <!-- Verifikasi Fisik Resep Asli (Permenkes RI No. 73/2016) -->
                    <div v-if="hasControlledMedicine" class="md:col-span-12 p-3.5 bg-amber-500/10 border border-amber-500/40 rounded-lg space-y-2">
                        <div class="flex items-center gap-2 text-xs font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider">
                            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Verifikasi Keabsahan Fisik Lembar Resep (Permenkes RI No. 73/2016)
                        </div>
                        <p class="text-xs text-amber-700 dark:text-amber-300/90 leading-relaxed">
                            Resep memuat obat golongan khusus. Apoteker penanggung jawab wajib memegang dan menelaah fisik lembar resep asli (tanda tangan dokter, faskes berizin, dan cap basah) serta memastikan bukan salinan tak berizin sebelum obat disiapkan.
                        </p>
                        <label class="flex items-start gap-2.5 text-xs font-semibold text-slate-800 dark:text-foreground cursor-pointer pt-1">
                            <input type="checkbox" v-model="form.verified_hardcopy" class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer" :required="hasControlledMedicine" />
                            <span>Saya menyatakan telah memegang fisik lembar resep asli dokter dan memverifikasi keabsahannya sebelum pemrosesan resep ini.</span>
                        </label>
                        <p v-if="form.errors.verified_hardcopy" class="text-rose-600 text-xs font-semibold mt-1">{{ form.errors.verified_hardcopy }}</p>
                    </div>

                    <!-- Skrining Klinis & Farmasetis -->
                    <div class="md:col-span-12">
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Catatan Telaah / Skrining Klinis Apoteker</label>
                        <textarea v-model="form.screening_notes" rows="2" class="w-full bg-background border border-input rounded-md p-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors resize-none" placeholder="Hasil telaah persyaratan farmasetis dan klinis (ketepatan indikasi, dosis, duplikasi, potensi interaksi obat)..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="bg-card border border-border rounded-lg p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4 border-b border-border pb-3">
                    <h2 class="text-foreground font-semibold text-sm">Daftar Obat / Racikan</h2>
                    <button type="button" @click="addItem" class="bg-secondary hover:bg-secondary/80 text-secondary-foreground border border-border h-8 px-3 rounded-md text-xs font-medium transition-colors cursor-pointer">
                        Tambah Item +
                    </button>
                </div>

                <div class="space-y-4">
                    <div 
                        v-for="(item, idx) in form.items" 
                        :key="idx" 
                        class="bg-muted/30 border border-border p-4 rounded-md relative"
                        :class="{ 'z-20': activeSignaDropdownIndex === idx || activeMedDropdownIndex === idx }"
                    >
                        <button v-if="form.items.length > 1" type="button" @click="removeItem(idx)" class="absolute top-3 right-3 text-muted-foreground hover:text-red-500 hover:bg-red-500/10 w-7 h-7 rounded-md flex items-center justify-center transition-colors cursor-pointer" title="Hapus baris obat">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                        
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                            <!-- Baris 1: Tipe, Nama, Qty, Iter -->
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Tipe Item <span class="text-red-500">*</span></label>
                                <select v-model="item.item_type" @change="handleItemTypeChange(idx)" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors cursor-pointer">
                                    <option value="obat_jadi">Obat Jadi</option>
                                    <option value="racikan">Racikan</option>
                                </select>
                            </div>

                            <!-- Nama Item (Searchable Combobox untuk Obat Jadi) -->
                            <div class="md:col-span-5">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Item <span class="text-red-500">*</span></label>
                                
                                <template v-if="item.item_type === 'obat_jadi'">
                                    <div class="relative medicine-dropdown-container">
                                        <!-- Trigger button -->
                                        <button
                                            type="button"
                                            @click.stop="openMedicineDropdown(idx)"
                                            class="w-full h-9 px-3 bg-background border border-input rounded-md text-sm text-left flex items-center justify-between focus:ring-1 focus:ring-ring outline-none transition-colors cursor-pointer"
                                            :class="{ 'ring-1 ring-ring': activeMedDropdownIndex === idx }"
                                        >
                                            <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                                <template v-if="item.medicine_id && getMedicine(item.medicine_id)">
                                                    <span class="text-foreground font-medium truncate">
                                                        {{ getMedicine(item.medicine_id).nama_obat }}
                                                    </span>
                                                    <span class="text-xs text-muted-foreground shrink-0">
                                                        ({{ getMedicine(item.medicine_id).satuan_stok }})
                                                    </span>
                                                    <span
                                                        v-if="['narkotika', 'psikotropika'].includes(getMedicine(item.medicine_id).classification)"
                                                        class="px-1.5 py-0.2 rounded text-[10px] font-bold uppercase tracking-wider bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-800 shrink-0"
                                                    >
                                                        {{ getMedicine(item.medicine_id).classification }}
                                                    </span>
                                                </template>
                                                <span v-else class="text-muted-foreground">
                                                    Pilih Obat... (klik untuk mencari)
                                                </span>
                                            </div>

                                            <div class="flex items-center gap-1.5 shrink-0 text-muted-foreground">
                                                <button
                                                    v-if="item.medicine_id"
                                                    type="button"
                                                    @click.stop="clearMedicine(idx)"
                                                    class="p-0.5 hover:text-foreground hover:bg-muted rounded text-xs"
                                                    title="Hapus pilihan obat"
                                                >
                                                    ✕
                                                </button>
                                                <svg class="w-4 h-4 transition-transform duration-150" :class="{ 'rotate-180': activeMedDropdownIndex === idx }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </button>

                                        <!-- Floating dropdown -->
                                        <div
                                            v-if="activeMedDropdownIndex === idx"
                                            class="absolute left-0 right-0 top-full mt-1 bg-card border border-border rounded-lg shadow-xl z-30 p-2 space-y-1.5 animate-in fade-in-50 zoom-in-95 duration-100"
                                        >
                                            <div class="relative">
                                                <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                </svg>
                                                <input
                                                    v-model="medicineSearch"
                                                    type="text"
                                                    placeholder="Ketik nama obat atau barcode..."
                                                    autofocus
                                                    class="w-full h-8 pl-8 pr-3 bg-muted/50 border border-input rounded-md text-xs text-foreground placeholder-muted-foreground focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                                />
                                            </div>

                                            <div class="max-h-52 overflow-y-auto space-y-0.5 pr-0.5">
                                                <button
                                                    v-for="m in filteredMedicines"
                                                    :key="m.id"
                                                    type="button"
                                                    @click="selectMedicine(idx, m)"
                                                    class="w-full text-left px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors flex items-center justify-between cursor-pointer"
                                                    :class="item.medicine_id === m.id ? 'bg-emerald-600 text-white font-semibold' : 'hover:bg-muted text-foreground'"
                                                >
                                                    <div class="min-w-0 flex items-center gap-1.5">
                                                        <span class="truncate">{{ m.nama_obat }}</span>
                                                        <span class="text-[11px] opacity-75 shrink-0">({{ m.satuan_stok }})</span>
                                                    </div>
                                                    <div class="shrink-0 flex items-center gap-1">
                                                        <span
                                                            v-if="['narkotika', 'psikotropika'].includes(m.classification)"
                                                            class="px-1 py-0.2 rounded text-[9px] font-bold uppercase tracking-wider"
                                                            :class="item.medicine_id === m.id ? 'bg-white/20 text-white' : 'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-800'"
                                                        >
                                                            {{ m.classification }}
                                                        </span>
                                                        <span v-if="item.medicine_id === m.id" class="text-xs ml-1">✓</span>
                                                    </div>
                                                </button>

                                                <div v-if="filteredMedicines.length === 0" class="p-3 text-center text-xs text-muted-foreground">
                                                    <p v-if="isSearchingMedicines">Sedang mencari obat di database...</p>
                                                    <p v-else>Tidak ada obat yang cocok dengan "{{ medicineSearch }}".</p>
                                                </div>
                                            </div>

                                            <div v-if="filteredMedicines.length >= 40 && !medicineSearch" class="pt-1 text-[11px] text-center text-muted-foreground border-t border-border">
                                                Ketik nama obat untuk mempersempit pencarian
                                            </div>
                                        </div>

                                        <input type="hidden" :value="item.medicine_id" required />
                                    </div>
                                </template>

                                <template v-if="item.item_type === 'racikan'">
                                    <div class="flex gap-4 mb-1.5">
                                        <label class="flex items-center gap-1.5 text-xs font-medium text-foreground cursor-pointer">
                                            <input type="radio" v-model="item.racikan_source" value="master" class="text-emerald-600 focus:ring-emerald-500 cursor-pointer" @change="item.custom_formula_name='';item.unit=''" />
                                            Master
                                        </label>
                                        <label class="flex items-center gap-1.5 text-xs font-medium text-foreground cursor-pointer">
                                            <input type="radio" v-model="item.racikan_source" value="baru" class="text-emerald-600 focus:ring-emerald-500 cursor-pointer" @change="item.compounded_product_id='';item.unit=''" />
                                            Baru
                                        </label>
                                    </div>

                                    <div v-if="item.racikan_source === 'master'">
                                        <select v-model="item.compounded_product_id" @change="handleProductChange(idx)" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors cursor-pointer" required>
                                            <option value="">Pilih Racikan Master...</option>
                                            <option v-for="p in compounded_products" :key="p.id" :value="p.id">{{ p.nama_produk }}</option>
                                        </select>
                                    </div>
                                    <div v-else>
                                        <input v-model="item.custom_formula_name" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Nama racikan kustom..." required />
                                    </div>
                                </template>
                            </div>

                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Quantity <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <input v-model.number="item.quantity" type="number" step="0.01" min="0.01" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm font-semibold focus:ring-1 focus:ring-ring outline-none transition-colors" required />
                                    <input v-model="item.unit" type="text" class="w-20 bg-muted border border-input rounded-md px-2 h-9 text-foreground text-xs font-medium focus:ring-1 focus:ring-ring outline-none text-center" :readonly="item.item_type === 'obat_jadi' || item.racikan_source === 'master'" placeholder="Satuan" required />
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Iter (Pengulangan)</label>
                                <div class="flex items-center gap-2 h-9">
                                    <label class="flex items-center gap-1.5 text-xs font-medium text-foreground cursor-pointer shrink-0">
                                        <input type="checkbox" v-model="item.is_iter" class="text-emerald-600 rounded focus:ring-emerald-500 cursor-pointer" />
                                        Ya
                                    </label>
                                    <input v-if="item.is_iter" v-model.number="item.iter_count" type="number" min="1" class="w-full bg-background border border-input rounded-md h-8 px-2 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Berapa x" required />
                                </div>
                            </div>

                            <!-- Baris 2: Dosis, Instruksi Pakai (Sig), Keterangan Tambahan -->
                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Dosis <span class="text-red-500">*</span></label>
                                <input v-model="item.dosage_instruction" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Cth: 500mg, 1 Tab, 1 Cth" required />
                            </div>

                            <!-- Aturan Pakai (Sig) dengan Accordion / Popover Presets & Auto-Learning -->
                            <div class="md:col-span-4 relative signa-dropdown-container">
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-semibold text-foreground uppercase tracking-wider">
                                        Aturan Pakai (Sig) <span class="text-red-500">*</span>
                                    </label>
                                    
                                    <!-- Accordion / Popover Toggle Button -->
                                    <button
                                        type="button"
                                        @click.stop="toggleSignaDropdown(idx)"
                                        class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 flex items-center gap-1 cursor-pointer transition-colors"
                                    >
                                        <span>📋 Pilihan Aturan Pakai</span>
                                        <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="{ 'rotate-180': activeSignaDropdownIndex === idx }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="relative">
                                    <input 
                                        v-model="item.usage_instructions" 
                                        type="text" 
                                        class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" 
                                        placeholder="Cth: 3x1 sesudah makan" 
                                        required 
                                    />

                                    <!-- Floating Popover / Accordion Content -->
                                    <div 
                                        v-if="activeSignaDropdownIndex === idx" 
                                        class="absolute left-0 right-0 top-full mt-1 bg-card border border-border rounded-lg shadow-xl z-50 p-2.5 space-y-2 animate-in fade-in-50 zoom-in-95 duration-100"
                                    >
                                        <div class="flex items-center justify-between pb-1 border-b border-border">
                                            <span class="text-[11px] font-bold text-foreground uppercase tracking-wider">Pilih / Tambah Aturan Pakai</span>
                                            <button type="button" @click="activeSignaDropdownIndex = null" class="text-xs text-muted-foreground hover:text-foreground p-0.5 cursor-pointer">&times;</button>
                                        </div>

                                        <!-- Search Field -->
                                        <div class="relative">
                                            <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                            <input
                                                v-model="signaSearch"
                                                type="text"
                                                placeholder="Cari aturan pakai (cth: 3x1, sesudah, oles)..."
                                                class="w-full h-7.5 pl-8 pr-2.5 bg-muted/50 border border-input rounded-md text-xs text-foreground placeholder-muted-foreground focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                            />
                                        </div>

                                        <!-- Presets List (Scrollable) -->
                                        <div class="max-h-40 overflow-y-auto space-y-1 pr-0.5">
                                            <button
                                                v-for="sig in filteredSignas"
                                                :key="sig"
                                                type="button"
                                                @click="selectSigna(item, sig)"
                                                class="w-full text-left px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors flex items-center justify-between cursor-pointer"
                                                :class="item.usage_instructions === sig ? 'bg-emerald-600 text-white font-semibold' : 'hover:bg-muted text-foreground'"
                                            >
                                                <span class="truncate">{{ sig }}</span>
                                                <span v-if="item.usage_instructions === sig" class="text-xs">✓</span>
                                            </button>

                                            <div v-if="filteredSignas.length === 0" class="p-2 text-center text-xs text-muted-foreground">
                                                Tidak ada aturan pakai yang sesuai "{{ signaSearch }}".
                                            </div>
                                        </div>

                                        <!-- Bagian "Aturan Pakai Lainnya / Tambah Baru" (Auto-learning) -->
                                        <div class="pt-2 border-t border-border space-y-1.5">
                                            <div v-if="!isAddingNewSigna" class="flex items-center justify-between">
                                                <span class="text-[11px] text-muted-foreground">Tidak menemukan opsi di atas?</span>
                                                <button
                                                    type="button"
                                                    @click="isAddingNewSigna = true; newSignaInput = signaSearch || ''"
                                                    class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline cursor-pointer flex items-center gap-1"
                                                >
                                                    <span>+ Aturan Pakai Lainnya</span>
                                                </button>
                                            </div>

                                            <div v-else class="space-y-1.5 bg-muted/40 p-2 rounded-md border border-border">
                                                <label class="block text-[11px] font-semibold text-foreground">
                                                    Input Aturan Pakai Baru (Otomatis Tersimpan ke Pilihan):
                                                </label>
                                                <div class="flex gap-1.5">
                                                    <input
                                                        v-model="newSignaInput"
                                                        type="text"
                                                        placeholder="Cth: 5x1 sesudah makan"
                                                        @keyup.enter="saveAndApplyCustomSigna(item)"
                                                        class="flex-1 h-7.5 px-2 bg-background border border-input rounded text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                                    />
                                                    <button
                                                        type="button"
                                                        @click="saveAndApplyCustomSigna(item)"
                                                        class="px-2.5 h-7.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded cursor-pointer transition-colors whitespace-nowrap"
                                                    >
                                                        Simpan &amp; Terapkan
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="md:col-span-5">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Keterangan Spesifik</label>
                                <input v-model="item.item_notes" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors" placeholder="Cth: Habiskan, Bila Demam, Telinga Kiri" />
                            </div>

                            <!-- Baris 3: Komposisi Racikan Kustom (Hanya jika racikan baru) -->
                            <div v-if="item.item_type === 'racikan' && item.racikan_source === 'baru'" class="md:col-span-12 mt-1">
                                <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Isi / Komposisi Resep Dokter <span class="text-red-500">*</span></label>
                                <textarea v-model="item.custom_formula_notes" rows="2" class="w-full bg-background border border-input rounded-md p-3 text-foreground text-sm focus:ring-1 focus:ring-ring outline-none transition-colors resize-none" placeholder="Salin persis dari resep dokter. Cth: R/ Paracetamol 250mg, CTM 2mg, m.f. pulv dtd no X..." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" :disabled="form.processing" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white h-10 rounded-md text-sm font-medium shadow-sm transition-colors disabled:opacity-50 uppercase tracking-wider cursor-pointer">
                Simpan Resep
            </button>
        </form>
    </div>

    <!-- History Prescriptions Modal -->
    <Teleport to="body">
        <div v-if="showHistoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm" @click="showHistoryModal = false"></div>
            <div class="relative bg-card rounded-xl shadow-xl w-full max-w-2xl p-6 border border-border max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-border mb-4">
                    <div>
                        <h3 class="text-foreground font-semibold text-base">Riwayat Resep Pasien</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Pilih resep sebelumnya untuk disalin otomatis ke lembar resep baru</p>
                    </div>
                    <button type="button" @click="showHistoryModal = false" class="text-muted-foreground hover:text-foreground p-1 rounded-md text-lg leading-none cursor-pointer">&times;</button>
                </div>

                <div class="space-y-3 overflow-y-auto pr-1 flex-1">
                    <div 
                        v-for="rx in pastPrescriptions" 
                        :key="rx.id" 
                        class="p-4 rounded-lg border border-slate-200 dark:border-border bg-slate-50/50 dark:bg-muted/30 hover:border-emerald-500/50 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-xs text-slate-800 dark:text-foreground font-mono">#{{ rx.prescription_number }}</span>
                                <span class="text-xs text-muted-foreground">&bull;</span>
                                <span class="text-xs text-slate-600 dark:text-muted-foreground font-medium">
                                    {{ rx.prescription_date ? new Date(rx.prescription_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }}
                                </span>
                            </div>
                            <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                {{ rx.doctor_name }} <span v-if="rx.clinic_name" class="font-normal text-muted-foreground">({{ rx.clinic_name }})</span>
                            </p>
                            <p class="text-[11px] text-muted-foreground truncate">
                                {{ (rx.prescription_items || []).map(i => i.medicine?.nama_obat || i.compounded_product?.nama_produk || i.custom_formula_name || 'Obat').join(', ') }}
                            </p>
                        </div>
                        <div class="shrink-0">
                            <button 
                                type="button" 
                                @click="applyPastPrescription(rx)" 
                                class="w-full sm:w-auto px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs font-semibold shadow-xs flex items-center justify-center gap-1.5 cursor-pointer transition-colors"
                            >
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Gunakan Resep Ini
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-border mt-4 flex justify-end">
                    <button type="button" @click="showHistoryModal = false" class="px-4 h-9 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md text-xs font-medium cursor-pointer transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Patient Modal -->
    <Teleport to="body">
        <div v-if="showPatientModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-background/80 backdrop-blur-sm" @click="showPatientModal = false"></div>
            <div class="relative bg-card rounded-lg shadow-lg w-full max-w-md p-6 border border-border">
                <h3 class="text-foreground font-semibold text-base mb-4">Tambah Pasien Baru</h3>
                <form @submit.prevent="savePatient" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Nama Pasien *</label>
                        <input v-model="patientForm.name" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">No. Telepon</label>
                        <input v-model="patientForm.phone" type="text" class="w-full bg-background border border-input rounded-md h-9 px-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-foreground uppercase tracking-wider mb-1.5">Catatan / Alergi</label>
                        <textarea v-model="patientForm.notes" rows="2" class="w-full bg-background border border-input rounded-md p-3 text-sm text-foreground focus:ring-1 focus:ring-ring outline-none transition-colors resize-none"></textarea>
                    </div>
                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button" @click="showPatientModal = false" class="px-4 h-9 bg-secondary hover:bg-secondary/80 text-secondary-foreground rounded-md text-sm font-medium transition-colors cursor-pointer">Batal</button>
                        <button type="submit" :disabled="patientForm.processing" class="px-4 h-9 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm font-medium shadow-sm transition-colors cursor-pointer">Simpan Pasien</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
