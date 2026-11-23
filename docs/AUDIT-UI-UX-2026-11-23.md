# Audit UI/UX — Tombol, Tata Letak, dan Konsistensi

**Tanggal:** 23 November 2026
**Ruang lingkup:** 335 berkas Blade, 691 nama route, 616 `<button>`, 357 `<form>`, 335 `<table>`, 1.194 elemen input
**Alat ukur:** `python3 tools/audit-ui.py` + guard regresi `tests/Unit/KualitasAntarmukaBladeTest.php` (10 invarian)
**Status:** 1 bug fungsional + seluruh backlog P1 (`label for` & penyatuan token `--bad`) diperbaiki; sisa temuan P2–P3 dipetakan jadi backlog terprioritas

---

## 1. Ringkasan eksekutif

| Temuan | Sebelum | Sesudah | Dampak pengguna |
|---|---:|---:|---|
| `route()` menunjuk nama route tak terdaftar | 1 | 0 | **Tombol "Kerjakan Kuis" tidak pernah muncul** (fitur mati) |
| `<button>` di dalam form tanpa `type` eksplisit | 20 | 0 | Risiko submit tak sengaja saat form ditambah tombol baru |
| Tombol ikon tanpa nama aksesibel (`aria-label`/`title`) | 20 | 0 | Pembaca layar hanya membacakan "tombol" |
| `<img>` tanpa `alt` | 4 | 0 | Blok tanda tangan PDF tidak terbaca pembaca layar |
| `<label>` tanpa `for` (tidak membungkus field) | 706 | 0 | Seluruh label terhubung ke field; judul grup radio/checkbox/tabel memakai `role="group"` + `aria-labelledby` |
| `label for="..."` tanpa pasangan `id` | 0 | 0 | Setiap `for` statis menunjuk `id` unik yang ada di berkas yang sama |
| Varian warna merah aksi destruktif / status negatif | 5+ | 1 (`--bad`) | Seluruh aksi destruktif/error memakai `var(--bad)`; tombol non-destruktif (Copy/Excel/Print/Batal) tidak lagi berwarna merah |
| `aria-label` total di seluruh view | 31 | 84 | +53 nama aksesibel baru |
| `href="#"` / `javascript:void(0)` | 0 | 0 | Tidak ada tautan mati |
| Form POST tanpa `@csrf` | 0 | 0 | Tidak ada |
| `@push` tanpa `@stack` | 0 | 0 | Tidak ada |
| Kolom `<th>` vs `<td>` tak konsisten | 0 | 0 | Tidak ada tabel yang bergeser |

**Kesimpulan:** struktur tabel, form, tautan label-ke-kontrol, dan semantik warna aksi destruktif kini bersih dan dijaga 10 invarian regresi otomatis. Sisa utang UI/UX terkonsentrasi pada **utilitas tata letak/inline style** (P2–P3, lihat §4–§5).

---

## 2. Metode (dapat diulang)

```bash
python3 tools/audit-ui.py          # ringkasan + temuan
python3 tools/audit-ui.py --json   # untuk membandingkan antar commit
vendor/bin/phpunit --filter KualitasAntarmukaBladeTest
```

Tool mem-parse `routes/web.php` + `routes/api.php` (termasuk prefix grup, route resource, dan grup bernama) lalu mencocokkannya dengan setiap `route('...')` di Blade. Regex tag dibuat tahan terhadap `-> ` di dalam `{{ $x->y }}` — sumber utama false positive pada pemeriksaan naif (`<img ...>` terpotong di `->`).

Setiap kandidat diverifikasi manual sebelum diperbaiki; kandidat yang ternyata perilaku sah dicatat di §6 agar presisi audit tidak menyesatkan.

---

## 3. Temuan yang diperbaiki

### 3.1 Bug fungsional: tombol kuis tidak pernah tampil (P1)

`resources/views/siswa/kelas-digital/pertemuan.blade.php`

```blade
@if(\Illuminate\Support\Facades\Route::has('siswa.kuis.kerjakan') && $pertemuan->kuis->aktif)
    <a href="{{ route('siswa.kuis.kerjakan', $pertemuan->kuis->id) }}" ...>
```

Route yang terdaftar adalah `siswa.kuis.show` dan `siswa.kuis.submit` (`routes/web.php:818-820`); `siswa.kuis.kerjakan` tidak pernah ada. Karena dijaga `Route::has()`, halaman tidak error — **tombolnya sekadar tidak pernah dirender**, sehingga siswa tidak punya jalan masuk ke kuis dari halaman pertemuan. Diperbaiki menjadi `siswa.kuis.show` dan guard disederhanakan menjadi `@if($pertemuan->kuis->aktif)`.

### 3.2 Tombol di dalam form tanpa `type` (20 tombol)

Tombol tanpa `type` berperilaku `submit`. Selama form hanya punya satu tombol ini tidak terasa; begitu ada tombol kedua (mis. "Batal" atau aksi AJAX), form ikut terkirim. Semua 20 tombol kini eksplisit `type="submit"`; 26 tombol ikon mendapat `aria-label`.

Contoh: `admin/api-keys/index.blade.php` (toggle + hapus), `admin/semester/index.blade.php`, `guru/pm/*` (hapus baris), `tka/kesiapan/*` (simpan filter/aturan).

### 3.3 Nama aksesibel untuk tombol ikon dan tautan ikon

Sebelumnya hanya 31 `aria-label` untuk 616 tombol. Ditambahkan 32 label kontekstual: "Hapus", "Cari", "Tutup", "Hapus baris", "Tampilkan atau sembunyikan token", "Buka atau tutup menu navigasi", "Pindahkan siswa ke kelas lain", "Keluarkan siswa dari kelas".

### 3.4 `alt` pada gambar tanda tangan PDF

4 `<img>` pada `pengawas/pdf/laporan-semester.blade.php` dan `pengawas/pdf/visitasi.blade.php` tidak punya `alt`; kini `alt="Tanda tangan Kepala Sekolah"` / `alt="Tanda tangan Pengawas Sekolah"`.

### 3.5 Label tidak terhubung ke field (706 → 0)

Label tanpa `for` tidak memfokuskan field saat diklik — terasa terutama pada form panjang dan pengguna layar sentuh:

- Tahap 1: 121 label yang field-nya sudah punya `id` ditautkan ke `id` eksisting.
- Tahap 2 (P1 tuntas): sisa 591 `<label>` diselesaikan seluruhnya:
  - **570 label kontrol tunggal** diberi pasangan `for` + `id` unik per berkas/perulangan (termasuk `id` dinamis di dalam `@for`/`@foreach`/`@forelse` pada `guru/kelas-digital/show.blade.php`, `siswa/kelas-digital/pertemuan.blade.php`, `pengawas/visitasi/index.blade.php`, dan `admin/cetak/modifikasi-tampilan.blade.php`).
  - **21 elemen `<label>` yang sebenarnya judul grup** (grup radio/checkbox, tabel dinamis multi-baris, atau nilai baca-saja seperti URL Spreadsheet) diubah ke elemen blok semantik dengan `id` + `role="group"` / `aria-labelledby`.

### 3.6 Penyatuan varian warna aksi destruktif ke token `--bad` (P1 tuntas)

Sebelumnya lima varian merah dipakai bergantian untuk satu makna risiko/error (`#B42318`, `var(--bad)`, `#991B1B`, `#DC2626`, `#D92D20`, ditambah sisa `#EF4444`/`#DC3545`/`#B91C1C`):

- Seluruh aksi destruktif, zona berbahaya, pesan galat, dan indikator negatif disatukan ke `var(--bad)` (serta pembacaan `getComputedStyle(document.documentElement).getPropertyValue('--bad').trim()` pada grafik Canvas/Chart.js; halaman mandiri tanpa `layout.css` mendeklarasikan `--bad: #B42318` di `:root`).
- Tombol **non-destruktif** yang sebelumnya ikut diwarnai merah marun/merah terang (`Copy`, `Excel`, `Pdf`, `Print`, `Batal` modal, `Tambah Pengguna`, `Edit Gelar Guru`, `Data Pengguna`, `Pratinjau PDF`) dikembalikan ke varian netral/utama (`btn-ghost`, `btn-secondary`, `btn-primary`) agar warna `--bad` eksklusif menandakan risiko.

---

## 4. Metrik konsistensi desain

Angka diukur dengan `tools/audit-ui.py` setelah penyelesaian P1.

| Metrik | Nilai | Interpretasi |
|---|---:|---|
| Atribut `style="..."` inline | 8.356 | Hampir seluruh tata letak diatur inline; tidak ada satu sumber kebenaran untuk spacing |
| Warna hex hardcoded | 3.579 | Turun dari 3.824 setelah penyatuan ke `--bad`; warna non-merah masih melampaui token `layout.css` |
| Pemakaian token `--bad` | 336 | Satu sumber kebenaran untuk aksi destruktif & status negatif (0 merah hardcoded di luar `:root`/PDF) |
| `!important` | 77 | Tanda perang spesifisitas CSS |
| Skala padding inline | 6+ nilai dominan: `10px` (505), `11px` (368), `8px` (261), `12px` (132), `7px` (125), `6px` (102) | Tidak ada skala 4/8px; garis tabel tidak sejajar antar halaman |
| Kelas tombol | `btn-ghost` 386, `btn-primary` 189, `btn-secondary` 113, `btn-outline` 48, `btn-danger` 18 | Warna aksi destruktif kini seragam memakai `--bad` |
| Elemen input memakai `.form-control` | 405 dari 1.194 (34%) | 66% field memakai padding/border inline → tinggi field tidak seragam satu halaman dengan halaman lain |
| Sistem wrapper tabel | `.table-wrapper` 264 vs `.table-responsive` 4 | Dua sistem; `.table-responsive` tidak punya style padding sel |
| Ikon | FontAwesome 1.333 vs Lucide 5 | Dua bahasa ikon (solid vs stroke) |
| `onsubmit="return confirm(...)"` | 76 | Dialog konfirmasi bawaan browser, tidak bisa digaya dan tidak konsisten di mobile |
| Tabel tanpa wrapper scroll | 22 | Semua di view cetak/pratinjau cetak (`admin/cetak/*`, `transkrip/cetak-show`, `siswa/rapor/pelengkap-show`) — lihat backlog P3 |

Catatan penting: `body { overflow-x: hidden }` (`layout.css:34`) menyembunyikan overflow horizontal. Konsekuensinya, **tabel lebar di dalam view tanpa `.table-wrapper` akan terpotong tanpa cara menggulir** — bukan sekadar "tidak rapi".

---

## 5. Backlog terprioritas

| Prioritas | Pekerjaan | Cakupan terukur | Status / Alasan |
|---|---|---|---|
| ~~P1~~ | ~~Selesaikan `label for` sisa 591~~ | ~~591 → 0~~ | **Selesai** — dijaga guard `test_label_terhubung_ke_kontrol_form` & `test_atribut_for_pada_label_menunjuk_id_yang_ada` |
| ~~P1~~ | ~~Satukan token warna aksi destruktif~~ | ~~5 varian → 1 (`--bad`)~~ | **Selesai** — dijaga guard `test_warna_destruktif_memakai_token_bad` |
| P2 | Ganti 8.356 inline style dengan utilitas `.form-control`, `.card`, `.table-wrapper` | 1.194 field, 335 tabel | Menjamin tinggi field, padding sel, dan jarak antar kartu konsisten; prasyarat perbaikan responsif |
| P2 | Skala spacing 4/8px | 6 nilai padding dominan | Merapikan garis tabel dan kartu antar halaman |
| P2 | Ganti 76 `confirm()` dengan modal konfirmasi bawaan aplikasi | 76 titik | Konsisten di mobile, bisa menampilkan detail aksi (nama data) |
| P2 | Standarkan tombol ikon baru wajib `aria-label` | 616 tombol (guard sudah ada) | Mencegah regresi aksesibilitas |
| P3 | Bungkus 22 tabel view cetak dengan `.table-wrapper` | 22 tabel | Hanya relevan bila pratinjau cetak dibuka di layar sempit |
| P3 | Pilih satu sistem ikon (FA atau Lucide) | 1.338 ikon | Konsistensi visual jangka panjang |

---

## 6. Kandidat yang diverifikasi BUKAN bug (presisi audit)

| Kandidat | Jumlah | Alasan bukan bug |
|---|---:|---|
| `id` duplikat (`fotoPreview`, `previewLogoKiri/Kanan`, `fld-cbt-session-kelas`) | 4 | Berada di cabang `@if/@else` — hanya satu yang dirender; JS `fotoPreview` sudah menangani kedua bentuk (ganti `img`/`div`) |
| Tombol tanpa handler (`hamburger`, `darkToggle`, `scrollTop`) | 3 | Diikat lewat `addEventListener` di `resources/js/halaman-publik.js` |
| `field-tanpa-name` (input dalam form) | 20 | Panel konfigurasi Dapodik & filter yang dibaca JS, bukan dikirim; input read-only profil |
| `form action="{{ route('login') }}"` | 1 | `route('login')` bernama untuk GET, tetapi URI `/login` juga punya route POST — submit tetap cocok |
| `<div class="row">` tanpa `col-*` | 15 | `row` di view PDF adalah kelas CSS lokal (`<div class="row"><div class="label">`), bukan grid Bootstrap |
| `thead=14` vs `td=6` (`kepsek/supervisi-pm/pdf-rekap`) | 1 | 9 kolom dihasilkan `@foreach` di dalam baris |
| `tabel-tanpa-wrapper` pada `guru/erapor/pdf.blade.php` dkk. | 16 | Dokumen PDF, bukan halaman layar |

---

## 7. Berkas yang diubah

- `resources/views/siswa/kelas-digital/pertemuan.blade.php` — perbaikan route kuis + tautan `label for` dinamis
- >170 berkas Blade — kelengkapan atribut `type`, `aria-label`, `alt`, penautan seluruh `label for` + `id` unik, serta penyatuan warna destruktif ke `var(--bad)`
- `resources/css/layout.css` — penghapusan fallback merah hardcoded yang berbeda dari `--bad`
- `tests/Unit/KualitasAntarmukaBladeTest.php` — guard 10 invarian
- `tools/audit-ui.py` — alat ukur
