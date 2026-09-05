<script setup>
import { computed } from 'vue';

const props = defineProps({
    headers: {
        type: Array,
        required: true
    },
    items: {
        type: Array,
        required: true
    },
    keyProp: {
        type: String,
        default: 'id'
    },
    isLoading: {
        type: Boolean,
        default: false
    },
    emptyText: {
        type: String,
        default: 'Tidak ada data ditemukan'
    }
});

const getAlignClass = (align) => {
    if (align === 'right') return 'text-right';
    if (align === 'center') return 'text-center';
    return 'text-left';
};

const getCellAlignClass = (header) => {
    const align = header.align;
    if (align) return getAlignClass(align);
    
    // Auto align by type if not specified
    if (header.type === 'number' || header.type === 'currency') return 'text-right';
    if (header.type === 'badge' || header.type === 'actions') return 'text-center';
    return 'text-left';
};
</script>

<template>
    <div class="w-full overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm scrollbar-thin">
        <table class="w-full text-sm text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th 
                        v-for="h in headers" 
                        :key="h.key"
                        :class="[getCellAlignClass(h), h.width ? h.width : '']"
                        class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap"
                    >
                        {{ h.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <!-- Loading Skeleton -->
                <template v-if="isLoading">
                    <tr v-for="i in 5" :key="i" class="animate-pulse">
                        <td v-for="h in headers" :key="h.key" class="px-6 py-4">
                            <div class="h-4 bg-slate-100 rounded-lg w-2/3 my-1"></div>
                        </td>
                    </tr>
                </template>

                <!-- Empty State -->
                <tr v-else-if="items.length === 0">
                    <td :colspan="headers.length" class="px-6 py-12 text-center text-slate-400 font-medium">
                        {{ emptyText }}
                    </td>
                </tr>

                <!-- Actual Rows -->
                <template v-else>
                    <tr 
                        v-for="row in items" 
                        :key="row[keyProp]" 
                        class="hover:bg-slate-50/70 transition-colors duration-150 group"
                    >
                        <td 
                            v-for="h in headers" 
                            :key="h.key" 
                            :class="[getCellAlignClass(h)]"
                            class="px-6 py-4 whitespace-nowrap"
                        >
                            <!-- Custom slot by header key -->
                            <slot :name="`cell(${h.key})`" :row="row" :value="row[h.key]">
                                <!-- Fallback Default Renderers -->
                                <template v-if="h.type === 'currency'">
                                    <span class="font-mono font-bold text-slate-900 tracking-tight">
                                        Rp {{ Number(row[h.key] || 0).toLocaleString('id-ID') }}
                                    </span>
                                </template>
                                <template v-else-if="h.type === 'number'">
                                    <span class="font-mono font-bold text-slate-700">
                                        {{ Number(row[h.key] || 0).toLocaleString('id-ID') }}
                                    </span>
                                </template>
                                <template v-else-if="h.type === 'badge'">
                                    <span 
                                        :class="h.badgeClass ? h.badgeClass(row[h.key]) : 'bg-slate-50 text-slate-600 border-slate-200'"
                                        class="text-[10px] px-2.5 py-1 rounded-full font-bold uppercase tracking-wider border shadow-sm"
                                    >
                                        {{ String(row[h.key] || '').replace(/_/g, ' ') }}
                                    </span>
                                </template>
                                <template v-else>
                                    <span class="text-slate-900 font-medium">{{ row[h.key] }}</span>
                                </template>
                            </slot>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</template>
