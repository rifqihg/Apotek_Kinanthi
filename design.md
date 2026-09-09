# DESIGN.md
# APOTEK KINANTHI — COLOR & VISUAL EMPHASIS DESIGN SYSTEM
# SHADCN/UI PRESERVATION — ZERO LOGIC CHANGE

---

# 0. ROLE

Bertindak sebagai:

- Senior UI/UX Engineer
- Design System Engineer
- Frontend Architect
- Visual Designer untuk Healthcare SaaS
- Vue + Tailwind + shadcn/ui specialist
- Code optimization specialist

Tugas utama bukan membuat ulang UI.

Tugas utama adalah melakukan:

> COLOR & VISUAL EMPHASIS REFINEMENT

terhadap sistem Apotek Kinanthi yang sudah berjalan.

Sistem saat ini sudah memiliki struktur dashboard SaaS yang dianggap baik dan harus dipertahankan.

Masalah utama yang ingin diselesaikan:

> Tampilan terlalu monochrome / hitam-putih sehingga terasa datar, dingin, dan monoton ketika digunakan dalam waktu lama oleh Admin, Apoteker, dan Kasir.

Solusi:

> Tambahkan identitas warna yang lebih kuat, hidup, dan modern melalui functional color system tanpa mengubah struktur UI dan tanpa mengubah logic aplikasi.

---

# 1. PRIMARY OBJECTIVE

Pertahankan:

- struktur halaman;
- layout;
- sidebar;
- header;
- dashboard;
- card;
- table;
- form;
- modal;
- dialog;
- dropdown;
- button;
- pagination;
- tabs;
- accordion;
- chart;
- navigation;
- responsive behavior;
- shadcn/ui;
- Vue;
- Tailwind;
- Ponytail coding style;
- vue-query-cache;
- seluruh logic existing.

Yang diperbaiki:

- warna;
- color hierarchy;
- visual emphasis;
- active state;
- hover state;
- focus state;
- selected state;
- semantic status color;
- badge color;
- icon color;
- accent;
- subtle surface tint;
- chart color;
- navigation emphasis;
- feedback visual.

Hasil akhir harus terasa:

> Modern Healthcare SaaS
> Clean
> Professional
> Calm
> Alive
> Human
> Functional
> Commercial
> Consistent

Bukan:

> Colorful template
> AI-generated dashboard
> Gradient-heavy SaaS
> Neon dashboard
> Dribbble concept
> Marketing landing page

---

# 2. ABSOLUTE NON-NEGOTIABLE RULE

## DO NOT CHANGE APPLICATION LOGIC

Perubahan HARUS terbatas pada visual/frontend styling.

JANGAN mengubah:

- database;
- migration;
- seeder;
- model;
- controller;
- route;
- middleware;
- policy;
- authentication;
- authorization;
- API;
- query;
- repository;
- service;
- validation;
- business logic;
- transaction flow;
- stock calculation;
- FEFO calculation;
- price calculation;
- margin calculation;
- discount calculation;
- prescription workflow;
- compounding workflow;
- cashier workflow;
- admin workflow;
- pharmacist workflow;
- stock mutation;
- stock deduction;
- database relationship;
- API response;
- API request;
- endpoint;
- route name;
- table name;
- field name;
- status value.

JANGAN melakukan refactor logic.

JANGAN memperbaiki logic yang tidak berhubungan dengan visual.

JANGAN mengganti library.

JANGAN mengganti shadcn/ui.

JANGAN mengganti Ponytail.

JANGAN mengganti vue-query-cache.

JANGAN menambahkan state baru hanya untuk kebutuhan dekorasi jika tidak benar-benar diperlukan.

---

# 3. IMPORTANT — AUDIT FIRST

Sebelum menulis kode:

1. Audit struktur frontend existing.
2. Identifikasi design token / CSS variable existing.
3. Identifikasi penggunaan Tailwind color.
4. Identifikasi penggunaan shadcn/ui.
5. Identifikasi global CSS.
6. Identifikasi komponen layout utama.
7. Identifikasi sidebar.
8. Identifikasi header.
9. Identifikasi card.
10. Identifikasi button.
11. Identifikasi badge.
12. Identifikasi table.
13. Identifikasi form/input.
14. Identifikasi alert/status.
15. Identifikasi chart.
16. Identifikasi halaman Dashboard sebagai Master Design Reference.

JANGAN langsung melakukan mass replacement.

Pahami terlebih dahulu bagaimana sistem saat ini membangun warna.

Prioritaskan perubahan pada:

> shared design tokens → shared components → page-specific styling

bukan mengulang styling yang sama di setiap halaman.

---

# 4. MASTER DESIGN PRINCIPLE

Gunakan prinsip:

> NEUTRAL FOUNDATION + FUNCTIONAL COLOR + CONTROLLED ACCENT

Jangan membuat seluruh UI berwarna.

Neutral tetap menjadi fondasi.

Warna digunakan untuk:

- identity;
- hierarchy;
- action;
- status;
- feedback;
- navigation;
- emphasis.

Bukan sekadar dekorasi.

---

# 5. COLOR PHILOSOPHY

Apotek Kinanthi adalah sistem Healthcare SaaS.

Visual harus memberi kesan:

- terpercaya;
- bersih;
- aman;
- profesional;
- modern;
- mudah digunakan;
- tidak melelahkan mata.

## PRIMARY — BLUE

Blue adalah identitas utama Apotek Kinanthi.

Blue digunakan untuk:

- primary action;
- active navigation;
- selected state;
- link;
- focus;
- primary icon;
- CTA;
- primary chart;
- important information.

Suggested palette:

```txt
Primary 950: #172554
Primary 900: #1E3A8A
Primary 800: #1E40AF
Primary 700: #1D4ED8
Primary 600: #2563EB
Primary 500: #3B82F6
Primary 400: #60A5FA
Primary 300: #93C5FD
Primary 200: #BFDBFE
Primary 100: #DBEAFE
Primary 50 : #EFF6FF
```

Gunakan token existing apabila sistem sudah mempunyai token yang setara.

JANGAN membuat duplikasi token tanpa alasan.

---

# 6. SECONDARY COLOR — GREEN

Green menjadi secondary healthcare color.

Gunakan untuk:

- success;
- valid;
- available;
- completed;
- healthy stock;
- positive state;
- successful transaction;
- completed prescription;
- successful compounding;
- positive feedback.

Suggested palette:

```txt
Green 900: #14532D
Green 800: #166534
Green 700: #15803D
Green 600: #16A34A
Green 500: #22C55E
Green 400: #4ADE80
Green 300: #86EFAC
Green 200: #BBF7D0
Green 100: #DCFCE7
Green 50 : #F0FDF4
```

Green harus digunakan secara fungsional.

JANGAN membuat seluruh card menjadi hijau.

---

# 7. WARNING COLOR — AMBER

Gunakan untuk:

- stok menipis;
- hampir minimum;
- mendekati expired;
- membutuhkan perhatian;
- pending action;
- warning.

Suggested palette:

```txt
Amber 900: #78350F
Amber 800: #92400E
Amber 700: #B45309
Amber 600: #D97706
Amber 500: #F59E0B
Amber 400: #FBBF24
Amber 300: #FCD34D
Amber 200: #FDE68A
Amber 100: #FEF3C7
Amber 50 : #FFFBEB
```

---

# 8. DANGER COLOR — RED

Gunakan hanya untuk:

- critical stock;
- expired;
- destructive action;
- failed;
- error;
- invalid;
- cancellation;
- critical alert.

Suggested palette:

```txt
Red 900: #7F1D1D
Red 800: #991B1B
Red 700: #B91C1C
Red 600: #DC2626
Red 500: #EF4444
Red 400: #F87171
Red 300: #FCA5A5
Red 200: #FECACA
Red 100: #FEE2E2
Red 50 : #FEF2F2
```

Red harus tetap memiliki makna.

Jangan menggunakan red sebagai decorative accent.

---

# 9. NEUTRAL SYSTEM

Neutral tetap dominan.

Gunakan neutral untuk:

- background;
- card;
- text;
- border;
- divider;
- secondary information.

Preferred foundation:

```txt
Background: #F5F9FF
Surface:    #FFFFFF
Border:     #E8EEF6

Text Strong:    #0F172A
Text Primary:   #1E293B
Text Secondary: #64748B
Text Muted:     #94A3B8
```

Jangan mengubah interface menjadi full-color background.

---

# 10. COLOR RATIO

Target visual:

```txt
70–80%
Neutral / White / Soft Surface

10–15%
Blue

5–10%
Green

Small semantic portions:
Amber
Red
```

Ini bukan aturan matematis yang harus dihitung per pixel.

Ini adalah visual direction.

Tujuannya:

> UI tetap clean tetapi tidak monochrome.

---

# 11. SIDEBAR

Sidebar saat ini sudah memiliki struktur yang baik.

JANGAN mengganti struktur sidebar.

JANGAN membuat sidebar full blue.

JANGAN membuat sidebar gradient.

JANGAN membuat sidebar glassmorphism.

JANGAN menambahkan illustration.

Yang diperbaiki:

## Active navigation

Gunakan:

- soft blue background;
- blue text;
- blue icon;
- subtle left indicator.

Visual:

```txt
Normal:
transparent / neutral

Hover:
soft blue tint

Active:
blue-50 background
blue-700 text
blue-600 icon
subtle blue indicator
```

Active state harus mudah dikenali tetapi tidak berlebihan.

---

# 12. HEADER

Header tetap clean.

Jangan membuat header penuh warna.

Gunakan blue hanya untuk:

- search focus;
- interactive controls;
- notification states;
- active controls;
- user interaction.

User profile tetap neutral.

---

# 13. DASHBOARD KPI CARDS

Pertahankan:

- white card;
- border;
- radius;
- shadow;
- layout;
- typography hierarchy.

JANGAN membuat setiap card memiliki background warna penuh.

Gunakan:

> COLOR ACCENT

Contoh:

- Transaksi Kasir → Blue accent.
- Pendapatan → Green accent.
- Resep Aktif → Blue accent.
- Menunggu Harga → Amber accent.
- Katalog Inventaris → Blue/neutral accent.
- Alert Stok Kritis → Red accent.
- Prioritas FEFO → Amber/blue sesuai semantic meaning.
- Antrean Racikan Lab → Blue/green sesuai status.

Color harus membantu pengguna memahami fungsi card.

---

# 14. KPI ICONS

Icon jangan semuanya gray.

Gunakan semantic color.

```txt
Primary information → Blue
Success → Green
Warning → Amber
Critical → Red
Neutral → Slate
```

Icon tetap outline.

Jangan memperbesar icon hanya untuk dekorasi.

---

# 15. KPI NUMBER

Angka utama tetap dark/neutral.

Jangan membuat nilai seperti:

```txt
Rp 8.500.000
```

menjadi hijau besar hanya karena pendapatan.

Gunakan accent pada icon, label kecil, atau supporting indicator.

Typography harus tetap profesional.

---

# 16. BUTTON SYSTEM

Primary button:

```txt
Blue
```

Success action:

```txt
Green
```

Warning action:

```txt
Amber
```

Destructive:

```txt
Red
```

Secondary:

```txt
White / neutral border
```

Hover harus memiliki perubahan visual yang jelas tetapi subtle.

Pressed state harus tetap konsisten.

Focus state harus menggunakan primary blue.

Jangan menggunakan gradient button.

---

# 17. BADGE SYSTEM

Badge harus menjadi salah satu sumber warna utama.

Contoh:

```txt
Aktif
→ green

Diproses
→ blue

Menunggu
→ amber

Kritis
→ red

Expired
→ red

Selesai
→ green

Draft
→ slate
```

Gunakan soft background + readable text.

Jangan menggunakan saturated background secara berlebihan.

---

# 18. TABLE

Table tetap clean.

Tambahkan visual hierarchy melalui:

- status badge;
- colored icon;
- hover background;
- active row;
- selected row;
- contextual action.

Row hover:

```txt
very light blue
```

bukan gray gelap.

Selected row:

```txt
blue-50
```

dengan indicator yang subtle.

---

# 19. FORM INPUT

Input tetap white.

Jangan membuat input berwarna-warni.

Normal:

```txt
white
neutral border
```

Hover:

```txt
slightly darker border
```

Focus:

```txt
blue border
blue focus ring
```

Error:

```txt
red border
red message
```

Success:

```txt
green feedback
```

---

# 20. ALERT / NOTIFICATION

Gunakan semantic color.

Information:

```txt
blue-50
blue text
blue icon
blue border
```

Success:

```txt
green-50
green text
green icon
green border
```

Warning:

```txt
amber-50
amber text
amber icon
amber border
```

Danger:

```txt
red-50
red text
red icon
red border
```

---

# 21. CHART

Chart harus menjadi salah satu focal point visual.

Gunakan maksimal:

```txt
Primary → Blue
Secondary → Green
Warning → Amber
Critical → Red
```

Untuk dashboard penjualan:

```txt
Penjualan → Blue
Transaksi → Green
```

Jangan menambahkan purple/pink/cyan hanya agar terlihat lebih colorful.

Chart harus tetap professional.

---

# 22. FEFO / STOCK VISUAL LANGUAGE

Karena ini adalah pharmacy management system, status inventory harus mudah dibaca.

Gunakan:

```txt
AMAN
→ Green

MENIPIS
→ Amber

KRITIS
→ Red

AKAN EXPIRED
→ Amber

EXPIRED
→ Red
```

Color coding harus konsisten di:

- Dashboard;
- Katalog Obat;
- Stock Check;
- FEFO;
- Stock Opname;
- Distribusi Stok;
- Log Stok;
- Kasir;
- laporan yang memiliki status inventory.

Jangan membuat setiap halaman memiliki interpretasi warna sendiri.

---

# 23. PRESCRIPTION VISUAL LANGUAGE

Untuk workflow resep:

```txt
Baru
→ Blue / Neutral

Diproses
→ Blue

Menunggu
→ Amber

Selesai
→ Green

Error / invalid
→ Red
```

Jangan mengubah status backend.

Ini hanya visual mapping.

---

# 24. COMPOUNDING VISUAL LANGUAGE

Untuk racikan:

```txt
Formula aktif
→ Blue

Sedang diproses
→ Blue

Menunggu bahan
→ Amber

Valid / selesai
→ Green

Bahan expired / invalid
→ Red
```

Tidak boleh mengubah workflow.

Hanya visual representation.

---

# 25. DISTRIBUSI STOK

Gunakan visual distinction:

```txt
Gudang
→ Blue / Neutral

Kasir
→ Green / Blue

Transfer keluar
→ Blue

Transfer masuk
→ Green

Warning stock
→ Amber

Critical
→ Red
```

Tetap jangan mengubah logic distribusi.

---

# 26. MICRO-INTERACTION

Tambahkan hanya jika sudah didukung oleh struktur existing.

Allowed:

- hover transition;
- color transition;
- subtle background transition;
- focus transition;
- active state;
- button feedback;
- badge emphasis;
- row hover.

Jangan menambahkan:

- excessive animation;
- floating animation;
- bouncing;
- pulsing everywhere;
- gradient animation;
- decorative motion.

Healthcare SaaS harus terasa tenang.

---

# 27. ANTI AI-SLOP RULES

WAJIB menghindari:

- purple-blue gradient;
- blue-purple SaaS gradient;
- neon;
- glassmorphism;
- excessive rounded container;
- excessive shadows;
- colorful cards everywhere;
- huge decorative icons;
- blobs;
- abstract floating shapes;
- unnecessary illustrations;
- rainbow charts;
- excessive gradients;
- excessive animations;
- giant typography;
- marketing-style hero section;
- decorative whitespace yang tidak memiliki fungsi.

Target:

> Mature SaaS product, not AI-generated concept UI.

---

# 28. SHADCN/UI PRESERVATION

shadcn/ui HARUS tetap digunakan.

Jangan mengganti component architecture hanya karena ingin mengubah warna.

Pertahankan komponen shadcn/ui yang sudah digunakan:

- Button;
- Card;
- Badge;
- Input;
- Select;
- Dialog;
- Dropdown;
- Table;
- Tabs;
- Accordion;
- Tooltip;
- Toast;
- Alert;
- dan komponen lainnya.

Jika warna dapat diperbaiki melalui design token / CSS variable:

> prioritaskan cara tersebut.

Jangan membuat duplicate component hanya untuk mengubah warna.

---

# 29. PONYTAIL CODING STYLE

Ikuti kebiasaan Ponytail dalam menulis kode:

> SIMPLE
> SHORT
> DIRECT
> REUSABLE
> MINIMAL

Jangan menghasilkan:

- abstraction berlebihan;
- utility function yang hanya dipakai sekali;
- component wrapper yang tidak perlu;
- configuration berlapis;
- duplicate class;
- CSS panjang;
- CSS redundant;
- unnecessary computed;
- unnecessary reactive state.

Jika satu perubahan dapat dilakukan dengan satu design token:

> gunakan satu design token.

Jika satu perubahan dapat dilakukan melalui shared component:

> ubah shared component.

Jangan memperbaiki halaman satu per satu jika akar masalahnya berada pada shared design system.

---

# 30. PREFER TOKEN-LEVEL CHANGES

Prioritas implementasi:

```txt
1. Existing design token
        ↓
2. Global CSS variables
        ↓
3. Shared shadcn component
        ↓
4. Shared layout component
        ↓
5. Page-specific styling
```

Hindari:

```txt
Page A → custom blue
Page B → custom blue
Page C → another blue
Page D → another green
```

Harus:

```txt
Design Token
      ↓
Shared Components
      ↓
All Pages
```

---

# 31. CONSISTENCY RULE

Satu semantic meaning harus mempunyai satu semantic color.

Contoh:

```txt
Success
→ selalu Green

Warning
→ selalu Amber

Danger
→ selalu Red

Primary
→ selalu Blue
```

Jangan membuat satu status memiliki warna berbeda di tiap modul.

---

# 32. DARK TEXT MUST REMAIN

Jangan menghilangkan dark typography.

Gunakan dark text sebagai foundation:

```txt
Heading → dark
Important number → dark
Body → slate
Supporting text → muted slate
```

Color accent digunakan untuk hierarchy.

Bukan menggantikan typography.

---

# 33. BRAND PERSONALITY

Apotek Kinanthi harus terasa:

```txt
Healthcare
+
Professional SaaS
+
Operational
+
Modern
+
Friendly
+
Trustworthy
```

Bukan:

```txt
Hospital dashboard
```

dan bukan:

```txt
Generic admin template
```

Warna harus membantu memberikan personality tersebut.

---

# 34. WHAT NOT TO TOUCH

Jangan mengubah:

- layout grid;
- sidebar width;
- card placement;
- dashboard information architecture;
- navigation structure;
- menu naming;
- route;
- page structure;
- database;
- backend;
- API;
- business logic;
- workflow;
- state machine;
- data fetching;
- vue-query-cache;
- authentication;
- authorization;
- stock logic;
- prescription logic;
- compounding logic;
- cashier logic.

Jika menemukan masalah functional:

> jangan diperbaiki dalam task ini.

Catat sebagai finding dan lanjutkan color refinement.

---

# 35. NO UNNECESSARY DEPENDENCIES

Jangan menambahkan library baru.

Jangan mengganti:

- shadcn/ui;
- Ponytail;
- vue-query-cache;
- Tailwind;
- existing icon library.

Gunakan dependency yang sudah tersedia.

---

# 36. RESPONSIVE

Color refinement tidak boleh merusak:

- desktop;
- tablet;
- mobile.

Jangan membuat horizontal overflow.

Jangan mengubah breakpoint hanya untuk kebutuhan warna.

---

# 37. ACCESSIBILITY

Pastikan perubahan warna tetap memiliki contrast yang baik.

Jangan mengandalkan warna saja untuk status penting.

Contoh:

```txt
🔴 Kritis
```

lebih baik daripada hanya:

```txt
[red dot]
```

Gunakan:

- text;
- icon;
- badge;
- border;
- color

secara kombinatif.

---

# 38. IMPLEMENTATION STRATEGY

Lakukan dalam urutan:

## PHASE 1 — AUDIT

Identifikasi:

- existing tokens;
- existing color variables;
- shared components;
- duplicate color definitions;
- hardcoded colors;
- shadcn theme;
- Tailwind usage.

## PHASE 2 — DESIGN TOKEN

Perbaiki color foundation.

## PHASE 3 — SHARED COMPONENTS

Perbaiki:

- Button;
- Badge;
- Card accent;
- Input;
- Alert;
- Tabs;
- Table;
- navigation.

## PHASE 4 — LAYOUT

Perbaiki:

- Sidebar;
- Header;
- global background.

## PHASE 5 — DASHBOARD

Apply semantic color.

## PHASE 6 — DOMAIN MODULES

Apply semantic colors secara konsisten pada:

- Admin;
- Apoteker;
- Kasir.

## PHASE 7 — VERIFICATION

Pastikan tidak ada functional regression.

---

# 39. DO NOT MASS-REWRITE

Jangan melakukan:

```txt
find/replace seluruh project
```

untuk mengganti warna secara membabi buta.

Contoh yang dilarang:

```txt
replace all gray → blue
replace all black → blue
replace all border-gray → blue
```

Karena akan merusak hierarchy.

Warna harus dipilih berdasarkan fungsi element.

---

# 40. VISUAL TARGET

Target visual akhirnya:

```txt
┌─────────────────────────────────────────────┐
│ WHITE / CLEAN HEADER                        │
├───────────────┬─────────────────────────────┤
│               │                             │
│ WHITE         │  SOFT BLUE BACKGROUND      │
│ SIDEBAR       │                             │
│               │  ┌──────┐ ┌──────┐         │
│ Blue active   │  │ KPI  │ │ KPI  │         │
│ state         │  │blue  │ │green │         │
│               │  └──────┘ └──────┘         │
│               │                             │
│               │  ┌─────────────────────┐   │
│               │  │                     │   │
│               │  │ BLUE / GREEN CHART  │   │
│               │  │                     │   │
│               │  └─────────────────────┘   │
│               │                             │
└───────────────┴─────────────────────────────┘
```

Tetap:

> clean + white + neutral

tetapi memiliki:

> blue identity + green healthcare accent + semantic status colors.

---

# 41. SUCCESS CRITERIA

Implementasi dianggap berhasil jika:

## Visual

- UI tidak lagi terasa monochrome;
- warna lebih hidup;
- blue identity terasa jelas;
- healthcare character lebih terasa;
- green memberikan positive accent;
- warning mudah dikenali;
- critical state mudah dikenali;
- dashboard tetap terlihat seperti SaaS profesional;
- tidak terlihat seperti AI-generated dashboard;
- tidak terlihat seperti template colorful.

## Consistency

- warna semantic konsisten;
- sidebar konsisten;
- button konsisten;
- badge konsisten;
- status konsisten;
- table konsisten;
- form konsisten;
- chart konsisten.

## Technical

- shadcn/ui tetap digunakan;
- Ponytail tetap digunakan;
- vue-query-cache tetap digunakan;
- tidak ada dependency baru;
- tidak ada migration;
- tidak ada schema change;
- tidak ada backend logic change;
- tidak ada route change;
- tidak ada API change;
- tidak ada business logic change.

---

# 42. VERIFICATION CHECKLIST

Setelah implementasi:

1. Run frontend build.
2. Pastikan build berhasil.
3. Check console error.
4. Check browser warning.
5. Check responsive.
6. Check Dashboard.
7. Check Sidebar.
8. Check Header.
9. Check Card.
10. Check Table.
11. Check Form.
12. Check Button.
13. Check Badge.
14. Check Alert.
15. Check Admin.
16. Check Apoteker.
17. Check Kasir.
18. Check dark/strong typography.
19. Check contrast.
20. Check no horizontal overflow.

Pastikan tidak ada perubahan logic.

---

# 43. DIFF DISCIPLINE

Setelah selesai:

## Files changed

Daftar file yang benar-benar berubah.

## Why changed

Alasan setiap file berubah.

## Logic safety

Konfirmasi bahwa:

- backend tidak berubah;
- API tidak berubah;
- database tidak berubah;
- route tidak berubah;
- business logic tidak berubah.

## Design system changes

Jelaskan:

- primary;
- secondary;
- semantic colors;
- sidebar;
- cards;
- buttons;
- badges;
- forms;
- charts.

## Verification

Tampilkan hasil:

```txt
Build:
PASS / FAIL

Console:
PASS / FAIL

Responsive:
PASS / FAIL

Logic:
UNCHANGED

Database:
UNCHANGED

API:
UNCHANGED

Routes:
UNCHANGED
```

---

# 44. FINAL RULE

Jika ragu antara:

> membuat perubahan visual yang lebih banyak

dan

> mempertahankan existing design

pilih:

> PERTAHANKAN EXISTING DESIGN.

Tujuan task ini bukan membuat UI baru.

Tujuan task ini adalah:

> membuat UI Apotek Kinanthi yang sekarang menjadi lebih hidup melalui warna tanpa kehilangan kesederhanaan, profesionalitas, struktur SaaS, dan karakter shadcn/ui.

Final visual philosophy:

```txt
CLEAN
+
COLORFUL ENOUGH
+
NOT MONOCHROME
+
NOT OVERDESIGNED
+
FUNCTIONAL COLOR
+
HEALTHCARE IDENTITY
+
PROFESSIONAL SaaS
+
SHADCN/UI
+
PONYTAIL STYLE
=
APOTEK KINANTHI
```

Implementasikan dengan perubahan seminimal mungkin dan hasil visual semaksimal mungkin.
