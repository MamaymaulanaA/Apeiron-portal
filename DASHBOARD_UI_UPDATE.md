# Dashboard UI Update — Portal Apeiron

## 📋 Overview

Dashboard Portal Apeiron telah disesuaikan tampilannya mengikuti referensi visual `redashboard.jpg` yang ada di folder root. Update ini **hanya mengubah presentation layer/UI** tanpa menyentuh logic, data, API, atau behavior existing.

## 🎨 Design Reference

Referensi visual: `/redashboard.jpg`

Design system menggunakan:
- **Color palette**: Soft pastel dengan aksen coral/pink (#f4515e)
- **Layout**: Floating shell design untuk desktop, flat untuk mobile
- **Typography**: Inter font family dengan hierarchy yang jelas
- **Components**: Modern card-based design dengan minimal shadows

## ✅ Apa yang Sudah Diimplementasikan

### 1. **Color & Theme**
- ✅ Warna aksen coral/pink: `#f4515e`
- ✅ Background page lavender soft: `#eceaf6`
- ✅ Kartu pastel: pink, biru, ungu, hijau, amber
- ✅ Border tipis dengan warna soft
- ✅ Shadow minimal (hampir tidak ada)

### 2. **App Shell (Desktop ≥1025px)**
- ✅ Panel putih melayang di atas background lavender
- ✅ Border radius: 22px
- ✅ Padding: 18px
- ✅ Sidebar dan main content menyatu dalam satu shell

### 3. **Sidebar**
- ✅ Background putih solid (no glassmorphism)
- ✅ Nav items datar, tanpa background pill
- ✅ Active state: warna aksen tanpa background
- ✅ Icon size: 17-18px dengan stroke 1.7
- ✅ User card di footer dengan background abu-abu soft

### 4. **Topbar**
- ✅ Background putih solid dengan border tipis
- ✅ Height: 58px
- ✅ Search box: background abu-abu soft #f7f8fb
- ✅ User avatar: border radius 9px
- ✅ Notification badge: background aksen coral

### 5. **Dashboard Content**

#### Hero/Header Banner
- ✅ Background putih dengan border tipis
- ✅ Badge: background pastel pink dengan border
- ✅ Typography: 22px bold untuk title
- ✅ Nama user dengan gradient pink
- ✅ Date display dengan background abu-abu soft

#### Kartu Statistik
- ✅ **Layout**: Teks kiri, ikon bulat kanan
- ✅ **Background**: Warna pastel sesuai varian
- ✅ **Border**: Tipis dengan warna pastel matching
- ✅ **Border radius**: 14px
- ✅ **Padding**: 16px 18px
- ✅ **Min height**: 88px
- ✅ **Ikon**: 
  - Ukuran 42×42px
  - Border radius 50% (bulat penuh)
  - Background warna solid cerah
  - Glyph putih
  - SVG icon 19px
- ✅ **Typography**:
  - Value: 28px bold
  - Label: 11.5px medium
  - Susunan: label atas, value bawah

#### Content Cards
- ✅ Background putih
- ✅ Border tipis: 1px solid #eef0f4
- ✅ Border radius: 14px
- ✅ Shadow minimal
- ✅ Hover: translateY(-2px) dengan soft shadow

### 6. **Responsive Design**
- ✅ Desktop (>1024px): Shell melayang aktif
- ✅ Tablet (≤1024px): Shell off, sidebar drawer
- ✅ Mobile (≤768px): 1 kolom, padding reduced
- ✅ Small (≤560px): Force 1 kolom grid, hide date

### 7. **Components**
- ✅ Buttons: border radius 8-10px
- ✅ Badges: border radius 20px (pill)
- ✅ Alerts: pastel background matching severity
- ✅ Tables: minimal styling dengan hover soft
- ✅ Forms: focus state dengan outline pink

### 8. **Typography**
- ✅ Font: Inter (Google Fonts)
- ✅ Base: 15px
- ✅ Heading: -0.01em letter spacing
- ✅ Label uppercase: 0.08em letter spacing
- ✅ Weights: 400 (regular), 500 (medium), 600 (semibold), 700 (bold)

## 📁 File yang Dimodifikasi

### Modified
- `/assets/css/dashboard-override.css` — **Main styling update**
  - Line 1-2000: Design tokens, shell, sidebar, topbar, dashboard components
  - Ukuran stat value diupdate: 26px → 28px (lebih dekat dengan referensi)

### Not Modified (Preserved)
- ❌ `/assets/index-*.js` — Bundle JS tidak disentuh
- ❌ `/assets/index-*.css` — Bundle CSS tidak disentuh
- ❌ `/api/**` — Logic API tidak disentuh
- ❌ `/src/**/*.php` — Controller & view PHP tidak disentuh
- ❌ `/config/**` — Konfigurasi tidak disentuh
- ❌ Data, routes, authentication, event handlers — Semua tetap utuh

## 🎯 Scope & Constraints

### ✅ Yang Diubah
- **Presentation layer only**: Colors, spacing, typography, layout
- **CSS override**: Menimpa bundle CSS existing
- **Visual appearance**: Mengikuti referensi redashboard.jpg

### ❌ Yang TIDAK Diubah
- Logic aplikasi
- Data flow & state management
- API endpoints & responses
- Database schema
- Route definitions
- Authentication & authorization
- Event handlers & behavior
- Bundle Vue.js & dependencies
- PHP controllers & models

## 🚀 How It Works

1. **Hybrid Architecture**: Portal Apeiron menggunakan Vue.js SPA dengan PHP backend
2. **CSS Loading Order**:
   ```html
   <link rel="stylesheet" href="/assets/index-BFKWD8yX.css">  <!-- Bundle -->
   <link rel="stylesheet" href="/assets/css/dashboard-override.css">  <!-- Override -->
   ```
3. **Scoped Styling**: CSS menggunakan `data-v-*` attributes dari Vue SFC
4. **Specificity**: Override CSS menggunakan attribute selectors dengan `!important` jika perlu

## 📊 Design Tokens Reference

```css
/* Primary accent */
--accent: #f4515e;
--accent-strong: #e63c4a;
--accent-soft: #fdecee;

/* Pastel card colors */
--past-pink-bg: #fdecee;
--past-pink-ic: #f4515e;
--past-blue-bg: #eaf2fe;
--past-blue-ic: #3d7ef0;
--past-purple-bg: #f3edfe;
--past-purple-ic: #8b5cf6;
--past-green-bg: #e9f8f0;
--past-green-ic: #2fb87a;
--past-amber-bg: #fff6e8;
--past-amber-ic: #f0a020;

/* Background */
--page-bg: #eceaf6;  /* Lavender soft */
--bg-body: #f8f9fc;
--bg-card: #ffffff;

/* Text */
--text: #1f2437;
--text-secondary: #5b6478;
--text-muted: #9aa2b4;

/* Border */
--border: #eef0f4;
--border-light: #f3f4f8;
```

## 📱 Responsive Breakpoints

```css
/* Desktop: Shell aktif */
@media (min-width: 1025px) { /* Floating shell */ }

/* Tablet: Shell off */
@media (max-width: 1024px) { /* Sidebar drawer */ }

/* Mobile: Simplified */
@media (max-width: 768px) { /* 1 column, reduced padding */ }

/* Small mobile */
@media (max-width: 560px) { /* Force 1 column */ }
@media (max-width: 480px) { /* Minimal padding */ }
```

## 🔍 Testing Checklist

Untuk memverifikasi implementasi sudah benar:

### Visual Check
- [ ] Background page lavender soft terlihat
- [ ] Panel putih melayang (desktop ≥1025px)
- [ ] Sidebar background putih solid, nav items datar
- [ ] Topbar background putih dengan border tipis
- [ ] Hero banner dengan badge pink
- [ ] Kartu statistik pastel: teks kiri, ikon bulat kanan
- [ ] Ikon bulat 42px dengan background solid cerah
- [ ] Angka statistik 28px bold
- [ ] Border tipis pada semua card
- [ ] Shadow minimal (hampir tidak ada)

### Layout Check
- [ ] Desktop: Shell border radius 22px
- [ ] Spacing antar card: 14-16px
- [ ] Card padding: 16-18px
- [ ] Stat card min-height: 88px
- [ ] Typography hierarchy jelas

### Responsive Check
- [ ] Desktop (>1024px): Shell melayang
- [ ] Tablet (≤1024px): Shell off, sidebar drawer
- [ ] Mobile (≤768px): 1 kolom, padding reduced
- [ ] Small (≤560px): Date hidden, minimal padding

### Interaction Check
- [ ] Card hover: translateY(-2px)
- [ ] Button hover: color change
- [ ] Focus state: outline pink
- [ ] Nav active: warna aksen tanpa background

## 🎨 Comparison with Reference

| Aspect | Reference (redashboard.jpg) | Implementation | Status |
|--------|----------------------------|----------------|--------|
| Color accent | Coral/Pink | #f4515e | ✅ |
| Background | Lavender soft | #eceaf6 | ✅ |
| Card layout | Text left, icon right | flex-direction: row-reverse | ✅ |
| Icon style | Solid circle | 50% border-radius | ✅ |
| Icon size | ~40-42px | 42px | ✅ |
| Card bg | Pastel colors | Pink/Blue/Purple/Green/Amber | ✅ |
| Typography | Clean, bold numbers | 28px Inter bold | ✅ |
| Border | Thin, subtle | 1px solid pastel | ✅ |
| Shadow | Minimal | 0 1px 2px rgba | ✅ |
| Spacing | Generous | 14-18px gaps | ✅ |
| Shell | Floating white panel | 22px radius, 18px pad | ✅ |

## 📝 Notes

1. **CSS Override Strategy**: File ini ditaruh paling akhir di index.html sehingga memiliki specificity tertinggi
2. **Vue Scoped Attributes**: Menggunakan `data-v-*` attributes untuk targeting komponen spesifik
3. **!important Usage**: Digunakan sparingly hanya untuk override bundle CSS yang sangat specific
4. **Backward Compatibility**: Semua existing functionality tetap berfungsi normal
5. **No Breaking Changes**: Tidak ada perubahan pada API, data flow, atau behavior

## 🚦 Status

✅ **SELESAI** — Dashboard UI sudah disesuaikan dengan referensi `redashboard.jpg`

### Last Updated
- **Date**: 2026-08-23
- **Changes**: 
  - Initial UI overhaul berdasarkan redashboard.jpg
  - Font size stat value: 26px → 28px untuk lebih akurat dengan referensi

---

**Catatan Penting**: Update ini **HANYA mengubah tampilan visual** (presentation layer). Semua logic, data, API, authentication, dan behavior aplikasi existing **tetap sama persis** seperti sebelumnya.
