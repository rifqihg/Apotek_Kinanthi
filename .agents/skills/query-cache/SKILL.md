---
name: query-cache
description: Guidelines and patterns for optimizing frontend data fetching and caching using @tanstack/vue-query and Inertia.js in Vue 3. Covers query keys, staleTime/gcTime configuration, cache invalidation, and avoiding redundant network requests.
---

# Query Cache & Data Fetching Guidelines (Vue 3 / Vue Query)

Panduan optimalisasi caching dan pengambilan data pada frontend Vue 3 menggunakan `@tanstack/vue-query` dan Inertia.js agar UI responsif tanpa delay berulang.

## 1. Konsep Utama
Gunakan `@tanstack/vue-query` untuk data dinamis yang sering di-fetch di sisi klien (katalog obat, daftar supplier, autocomplete, faktur lampau):
- **Stale-While-Revalidate (SWR):** Tampilkan data dari cache memori secara instan, lakukan revalidasi di background jika data sudah *stale*.
- **Hindari Refetch Agresif:** Nonaktifkan refetch otomatis saat window blur (`refetchOnWindowFocus: false`) jika data jarang berubah.

## 2. Pengaturan `staleTime` & `gcTime`
Sesuaikan umur cache berdasarkan karakteristik data:
- **Katalog Statis / Jarang Berubah (Suppliers, Kategori Satuan):**
  - `staleTime`: 10 – 30 menit (`10 * 60 * 1000`)
  - `gcTime` (Garbage Collection): 60 menit
- **Data Operasional / Katalog Obat (Medicines, Past Invoices):**
  - `staleTime`: 2 – 5 menit (`5 * 60 * 1000`)
  - `gcTime`: 15 menit
- **Pencarian Autocomplete (Debounced):**
  - Gunakan `useDebounceFn` (dari `@vueuse/core`) atau debounce query minimal 300ms.
  - Set `enabled: computed(() => query.value.length >= 2)`.

## 3. Struktur Query Key
Gunakan array konsisten dan hierarkis:
```javascript
// Data umum
['suppliers']
['medicines', { search: queryText }]
['past-invoices', { supplierId }]
```

## 4. Invalidation pada Mutasi (Form Submission)
Ketika melakukan penambahan atau perubahan data (misal: simpan faktur, tambah obat baru):
1. Setelah request berhasil (`onSuccess` di Inertia atau `useMutation`):
```javascript
import { useQueryClient } from '@tanstack/vue-query';
const queryClient = useQueryClient();

// Invalidate hanya query yang terdampak
queryClient.invalidateQueries({ queryKey: ['medicines'] });
queryClient.invalidateQueries({ queryKey: ['past-invoices'] });
```
2. Kombinasikan dengan fitur Inertia partial reload:
```javascript
form.post(route('...'), {
    preserveScroll: true,
    only: ['stocks', 'flash'], // Hindari full page reload data berat
    onSuccess: () => {
        // Invalidate cache frontend
        queryClient.invalidateQueries({ queryKey: ['stocks'] });
    }
});
```

## 5. Aturan Kerja Agen
1. Cek ketersediaan `@tanstack/vue-query` di komponen sebelum membuat sistem cache manual baru.
2. Jangan membuat state reactive duplikat jika data sudah dikelola oleh `useQuery`.
3. Pastikan kunci query (*query key*) selalu menyertakan parameter dependensinya (seperti filter atau id).
