# Pemantauan Kesiapan Siswa Menghadapi Tes Kemampuan Akademik (TKA) Kementerian

**Posisi Produk:**  
Menu **Pemantauan Kesiapan TKA** (`/guru/tka-kesiapan`, `/kepsek/tka-kesiapan`, `/admin/tka-kesiapan`, `/siswa/tka-kesiapan`, `/ortu/tka-kesiapan`) merangkum bukti kesiapan belajar siswa dari waktu ke waktu (lintasan Kelas 4 → Kelas 5 → Kelas 6 per tahun ajaran dan semester) sebagai **alat bantu internal sekolah** untuk merencanakan dukungan dan intervensi belajar.

> [!IMPORTANT]
> **Disclaimer Wajib (ditampilkan di antarmuka & ekspor CSV):**  
> Hasil ini adalah pemantauan internal untuk membantu perencanaan dukungan belajar, bukan nilai, prediksi, atau hasil resmi TKA. Pemetaan dan aturan internal perlu ditinjau sekolah.

---

## 1. Verifikasi Kerangka Resmi & Pelabelan Indikator

### Status Verifikasi Dokumen Primer
- **Penyelenggara:** Badan Standar, Kurikulum, dan Asesmen Pendidikan (BSKAP) & Pusat Asesmen Pendidikan (Pusmendik), Kemendikdasmen.
- **Acuan Regulasi yang Diverifikasi Metadatanya:**
  - *Peraturan Kepala BSKAP Nomor 047/H/AN/2025* tentang Kerangka Asesmen Tes Kemampuan Akademik (TKA) Jenjang SD/MI/Sederajat dan SMP/MTs/Sederajat (ditetapkan `2025-07-24`).
  - *Permendikdasmen Nomor 9 Tahun 2025* tentang Tes Kemampuan Akademik.
  - *Kepmendikdasmen Nomor 95/M/2025* tentang Pedoman Penyelenggaraan TKA.
- **Lokasi / Portal:** `https://pusmendik.kemendikdasmen.go.id/`
- **Tanggal Verifikasi:** `2026-11-16`
- **Status Naskah Primer di Repository:** `dokumen_primer_tersedia = false` (salinan PDF primer lengkap belum disimpan di repository; metadata diverifikasi melalui portal/ringkasan web).

### Pemisahan Klasifikasi Indikator (`config/tka_kesiapan.php` & `KesiapanLabel`)
Karena salinan PDF primer belum ada di repository, seluruh pemetaan indikator disematkan label keterbukaan yang ketat dan dipisahkan dari indikator penunjang internal:

1. **Pemetaan Sementara Selaras Ringkasan Regulasi (`asal = pemetaan_sementara`):**
   Label UI: *"Pemetaan sementara — perlu verifikasi sekolah"* (baru berubah menjadi `kerangka_terverifikasi` setelah sekolah memverifikasi naskah primer secara langsung).
   - `bahasa_indonesia` — *Bahasa Indonesia (literasi membaca)*: keterampilan membaca teks informasi dan teks fiksi (menemukan informasi, interpretasi/integrasi, evaluasi/refleksi).
   - `matematika` — *Matematika*: bilangan, geometri dan pengukuran, serta data.
2. **Indikator Penunjang Internal SIMANTAP (`asal = penunjang_internal`):**
   Label UI: *"Indikator penunjang internal SIMANTAP — perlu validasi sekolah"*. Bukan mata uji utama TKA SD/MI pada ringkasan regulasi.
   - `ipas` — *IPAS (penunjang internal)*
   - `penalaran_umum` — *Penalaran & pemecahan masalah (penunjang internal)*

---

## 2. Arsitektur Sumber Bukti (Read-Only) & Audit Skema

Layanan `App\Services\Tka\Kesiapan\BuktiCollector` membaca secara **strictly read-only** (tanpa memodifikasi nilai asal CBT, Tryout, kuis, nilai kelas, e-Rapor, atau TKA) dengan kueri terkelompok per sumber untuk menghindari N+1.

### A. Tabel Sumber yang Terintegrasi

| Kode Sumber (`config/tka_kesiapan.php`) | Tabel & Kolom Aktual | Relasi Siswa & Tenant | Skala Asli & Normalisasi (0–100) | Aturan Validitas & Deduplikasi |
| :--- | :--- | :--- | :--- | :--- |
| `nilai_harian` | `nilai` (`id`, `siswa_id`, `kuis_id`, `tanggal`, `jenis`, `judul_penilaian`, `mata_pelajaran`, `nilai`, `rekaman`, `deleted_at`) | `siswa_id`, `tenant_id` | `0–100` (nilai guru) | Baris dengan `kuis_id IS NOT NULL` dilewati (`duplikat_kuis_nilai`) agar tidak ganda dengan `hasil_kuis`. Baris dengan `id` di `tka_erapor_sync_logs.nilai_id` atau `rekaman['sumber'] === 'tka_kognitif'` dilewati (`duplikat_tka_sync`). Nilai null/non-numerik/`< 0`/`> 100` diabaikan. |
| `kuis` | `hasil_kuis` JOIN `kuis` (`k.deleted_at IS NULL`) | `h.siswa_id`, `h.tenant_id` | `benar ÷ total` → `(benar / total) * 100` | Ditolak bila `total <= 0`, `benar < 0`, atau `benar > total` (`skala_atau_nilai_kosong`). Kelas diambil dari `kuis.kelas`. |
| `erapor` | `nilai_erapor` (`id`, `siswa_id`, `mata_pelajaran`, `kelas`, `semester`, `tahun_ajaran`, `nilai_formatif`, `nilai_sumatif`, `nilai_sumatif_akhir`, `nilai_akhir`) | `siswa_id`, `tenant_id` | `0–100` (`nilai_akhir`) | Ditolak bila seluruh komponen `<= 0`. Dicek terhadap `tka_erapor_sync_logs.nilai_erapor_id`: bila seluruh komponen non-nol cocok (`±0.05`) dengan `skor_tka_konversi`, dianggap duplikat sinkronisasi penuh dan dilewati (`duplikat_tka_sync`); bila hanya sebagian, tetap dibaca dengan catatan tinjauan. Tahun ajaran/semester invalid ditolak & dicatat ke `kalender_tidak_konsisten`. |
| `ujian` (Engine Utama) | `exam_attempts` + `exam_results` + `exam_sessions` + `exam_banks` + `rombel` + `semesters` | `siswa_id`, `exam_attempts.tenant_id` | `skor ÷ maksimal` → `* 100` | Mengabaikan sesi/bank yang dihapus (`deleted_at IS NOT NULL`) atau berstatus `draft`/`dibatalkan`/`cancelled`/`batal` (`ujian_dibatalkan_atau_draft`). Hanya menerima attempt berstatus `dikumpulkan`/`dinilai` dengan `selesai_at IS NOT NULL` (`ujian_belum_final`). Deduplikasi attempt ulang per `(session_id, siswa_id)`: mengambil `attempt_number` terbaru (`duplikat_attempt_ulang`). Jika `exam_results.detail_per_section` berisi rincian per mapel/bagian (mis. Tryout multisesion), tiap section valid (`maksimal > 0`) dipetakan ke mapelnya via `mata_pelajaran_id`/`nama_mapel`. |
| `ujian` (Fallback Legacy) | `cbt_results` (`cbt_sessions`, `cbt_banks`) & `tryout_results` (`tryout_sessions`, `rombel`, `semesters`) | `siswa_id`, `tenant_id` | CBT: `0–100`; Tryout: `detail_nilai_mapel` atau `nilai_total ÷ nilai_maksimal` | Hanya membaca rekaman yang **belum** dibackfill ke `exam_attempts` (pengecekan `sumber_tabel:sumber_id`). Tetap menolak sesi `draft`/`dibatalkan`/`cancelled`/`batal` dan hasil belum selesai (`submitted_at`/`waktu_selesai IS NULL`). |
| `asesmen_tka` | `tka_student_assessments` (`skor_per_dimensi`, `tanggal_penilaian`, `semester_id`) + `semesters` | `siswa_id`, `tka_student_assessments.tenant_id` | `total_skor ÷ maksimal` per dimensi | Hanya dimensi dengan `maksimal > 0`. Tiap dimensi dipetakan sendiri melalui `PemetaanResolver`. |
| `simulasi_tka` | `tka_adaptive_simulations` (`skor_indikator`, `mode_simulasi`, `paket_judul`, `status`, `submitted_at`) | `siswa_id`, `tenant_id` | `0–100` per entri `skor_indikator` | Wajib `status = 'selesai'` DAN `submitted_at IS NOT NULL`. Diberi catatan bahwa skor simulasi adaptif (CAT) dipengaruhi tingkat kesulitan soal dinamis. |

### B. Tabel yang Tidak Diintegrasikan ke Skor Kesiapan (Terdokumentasi)
- `nilai_mapel`, `capaian_pembelajaran`, `nilai_cp`: skema migrasi ada di database, tetapi belum memiliki alur/controller aktif di aplikasi.
- `transkrip_nilai`: merupakan agregasi kelulusan akhir tanpa konteks tanggal/semester terperinci; dikecualikan agar tidak duplikat dengan `nilai_erapor`.
- `materi` & `tujuan_pembelajaran` (di `pengaturan_guru`): perencanaan pembelajaran tingkat guru/kelas, bukan skor individu siswa.
- `dimensi`, `p5_penilaian`, `kebiasaan`, `catatan`, `kehadiran`: penilaian karakter/P5/kebiasaan/kehadiran/catatan kualitatif tidak digabungkan ke skor indikator kognitif karena perbedaan konstruk & skala.
- `kenaikan_kelas`: **tidak dipakai sebagai skor**, tetapi dibaca read-only untuk mendeteksi peristiwa historis `tinggal_kelas` (`naik_kelas = false`).

### C. Pemetaan Label & Non-Equivalence
- `App\Services\Tka\Kesiapan\PemetaanResolver` memetakan label sumber lewat: (1) tabel `tka_kesiapan_pemetaan` per tenant, lalu (2) alias bawaan di `config/tka_kesiapan.php` (pencocokan kata/frasa utuh, alias terpanjang dahulu).
- Label yang tidak cocok tidak dipaksa masuk kategori tertentu (`indikator = null`) dan ditampilkan pada ringkasan `tidak_terpetakan`.
- Normalisasi ke `0–100` hanya untuk membuat ringkasan internal per jenis sumber (`ringkasan_sumber` menampilkan jumlah, rata-rata, rentang, skala asli, dan tanggal terbaru). Instrumen berbeda **tidak** dianggap otomatis sebanding.

---

## 3. Aturan Perhitungan (`KesiapanCalculator`)

Seluruh nilai aturan dapat disesuaikan dan diaudit per sekolah melalui menu Admin (`tka_kesiapan_pengaturan`):

1. **Syarat Kecukupan Bukti (`bukti_status`):**
   - `cukup` hanya bila `jumlah_bukti >= min_bukti` (default `3`) **DAN** `jumlah_tanggal >= min_tanggal_berbeda` (default `2` tanggal kalender berbeda).
   - Jika belum terpenuhi: `bukti_status = 'belum_cukup'`, sedangkan `estimasi_indeks_internal`, `skor_rata`, `kecenderungan`, dan `keyakinan` tetap **`null`** (*"Data belum cukup dinilai"*). Ketiadaan data **tidak pernah** diubah menjadi skor `0` atau `"belum siap"`.
2. **Prioritas Tampilan & Indeks Estimasi Internal (`estimasi_indeks_internal` / `skor_rata`):**
   - Antarmuka mengutamakan **ringkasan per jenis sumber**, **kelengkapan/kebaruan bukti**, serta **tren tervalidasi** sebelum menampilkan angka indeks estimasi internal.
   - Formula indeks estimasi internal (dilabeli eksplisit sebagai *Estimasi Indeks Internal SIMANTAP*): rata-rata per jenis sumber dihitung lebih dahulu, lalu dirata-rata terbobot antar-jenis sumber (`bobot_sumber` default kosong = bobot setara `1.0` per jenis sumber yang ada).
   - **Tidak ada skor gabungan tunggal lintas indikator.**
3. **Ambang Kecenderungan Internal (bila bukti `cukup`):**
   - `< 60.0` (`ambang_perlu_dukungan`) → `perlu_dukungan` (*Perlu dukungan*)
   - `60.0 – < 75.0` (`ambang_cukup_mantap`) → `berkembang` (*Berkembang*)
   - `>= 75.0` → `cukup_mantap` (*Cukup mantap*)
4. **Keyakinan Analisis Anti-Repetisi (`keyakinan`):**
   - Mengevaluasi 4 kriteria: jumlah (`>= keyakinan_target_jumlah` default `8` **dan** `>= 3` tanggal berbeda), kebaruan (`0 <= hari_sejak <= keyakinan_kebaruan_hari` default `120`), cakupan (`>= keyakinan_min_jenis_sumber` default `2` jenis sumber), konsistensi (simpangan baku `<= keyakinan_maks_sebaran` default `15.0` dan `>= 2` tanggal berbeda).
   - Level `tinggi` **wajib** memenuhi variasi sumber (`cakupan`), `kebaruan`, `>= 3` tanggal berbeda, serta minimal 3 kriteria. Rekaman berulang dari satu jenis sumber **tidak pernah** diberi keyakinan `tinggi`. Alasan dituliskan pada `keyakinan_alasan`.
5. **Tren & Lintasan Faktual (`tren`, `lintasan`):**
   - Setiap periode dinilai layak bila memiliki `>= min_bukti_per_periode` (default `2`) bukti. `tren` membandingkan 2 periode layak terakhir dengan selisih `>= ambang_tren` (default `5.0`) → `meningkat`, `<= -5.0` → `menurun`, atau `stabil`.
   - `lintasan` menyertakan **hanya** bukti dengan `tingkat_valid = true` dan mengurutkannya kronologis (`tahun_ajaran`, `semester`), sehingga siswa tinggal kelas tetap terlihat kronologis antar-periode tanpa menimpa kelas yang sama.

---

## 4. Validasi Histori Kelas 4 → 5 → 6 & Kasus Khusus

`BuktiCollector::validasiJenjangHistoris()` menerapkan aturan ketat:
- **Tanpa Penebakan Otomatis:** Jenjang kelas masa lalu **tidak pernah** dihitung dari `siswa.kelas` sekarang dikurangi selisih tahun.
- **Sumber Valid:** Jenjang dianggap valid (`tingkat_valid = true`) hanya jika tertulis eksplisit pada bukti (`nilai_erapor.kelas`, `kuis.kelas`, `exam_sessions.kelas`/`rombel.tingkat`, `tryout_sessions`) **atau** diwarisi dari rekaman faktual pada `(tahun_ajaran, semester)` yang persis sama tanpa konflik rombel (`tingkat_sumber = 'periode_tervalidasi'`).
- **Jenjang Tidak Diketahui:** Bukti tanpa kelas sumber valid (mis. `nilai` lama atau `tka_adaptive_simulations` tanpa `nilai_erapor`/ujian faktual di periode sama, atau terjadi konflik pindah rombel) tetap ditampilkan dalam daftar terpisah *"Jenjang historis tidak diketahui"* dan **dikecualikan dari tren faktual kelas 4 → 5 → 6**.
- **Deteksi Kondisi Akademik Khusus (`histori_kondisi`):**
  - `tinggal_kelas`: dari `kenaikan_kelas.naik_kelas = false` atau satu jenjang faktual tercatat pada lebih dari satu tahun ajaran.
  - `akselerasi`: loncatan jenjang faktual `>= 2` tingkat dalam tahun ajaran berurutan.
  - `pindah_rombel`: lebih dari satu kelas/tingkat faktual tercatat pada satu periode `(tahun_ajaran, semester)` → periode tersebut ditandai konflik agar tidak terjadi pewarisan kelas silang.
  - `kalender_tidak_konsisten`: format `tahun_ajaran`/`semester` e-Rapor invalid, `semester_id` siswa hilang/invalid, atau tanggal bukti tidak sesuai rentang `tahun_ajaran`.
  - `catatan_mutasi`: siswa berstatus non-aktif (`lulus`, `pindah`, `keluar`, dll.).

---

## 5. Rekomendasi, Tindak Lanjut, Privasi & Audit

- **Rekomendasi (`RekomendasiService`):** Disusun hanya atas indikator dengan bukti `cukup` yang berada pada kecenderungan `perlu_dukungan` **atau** mengalami `tren.arah = 'menurun'`, disertai alasan pemicu dan kutipan contoh bukti konkret. Guru meninjau, menyunting teks/status (`disarankan`, `ditinjau`, `berjalan`, `selesai`, `ditolak`), serta dapat mencatat **`alasan`** revisi ke `tka_kesiapan_tindak_lanjut_revisi`.
- **Otorisasi & Kebijakan (`KesiapanAkses`, `TkaKesiapanPolicy`, `KesiapanController`):**
  - Seluruh akses `tenant_id` difilter ketat (termasuk `whereNull('tenant_id')` bila tabel `tenants` terisi).
  - Filter `rombel_id` divalidasi lewat `KesiapanAkses::rombelDiizinkan()` (mencegah IDOR lintas guru/tenant).
  - Siswa diarsipkan (`archived_at IS NOT NULL`) dikecualikan dari `KesiapanAkses::siswaQuery()`.
  - **Guru:** Melihat & mengekspor data kelas yang diampunya serta mengelola tindak lanjut siswanya (`TkaKesiapanPolicy`).
  - **Kepala Sekolah & Admin:** Hanya melihat **agregat kelas/rombel dan ekspor CSV agregat** (tanpa daftar nama siswa, tanpa akses profil individu/ekspor baris siswa → `403`).
  - **Perlindungan Kelompok Kecil (`min_kelompok_agregat`, default `3`):** Pada tampilan agregat Kepsek/Admin dan lintasan tingkat, kelompok dengan `0 < jumlah_siswa < min_kelompok_agregat` disupresi agar filter berulang tidak dapat menyingkap individu.
  - **Siswa & Orang Tua (`/siswa/tka-kesiapan`, `/ortu/tka-kesiapan`):** Lookup siswa mengikuti pola `terhubung_dengan` maupun pencocokan `nama_pengguna` terhadap `nis`/`nisn`/`siswa{NIS}`, memeriksa kepemilikan `siswa_uuid`, dan menyembunyikan peringkat maupun `catatan_guru`.
- **Log Audit (`tka_kesiapan_audit`):** Mencatat perubahan aturan dan pemetaan sekolah beserta ringkasan `sebelum`, `sesudah`, dan `alasan`.

---

## 6. Feature Flag, Migrasi, Backup & Rollback

### Feature Flag
- Variabel lingkungan `TKA_KESIAPAN_AKTIF` (default `true` via `config('tka_kesiapan.aktif')` dan helper `kesiapanTkaAktif()`).
- Saat bernilai `false`: tautan `*.tka-kesiapan.*` disembunyikan dari seluruh sidebar (admin, guru, kepsek, siswa, ortu), tab `TKA` pada `components/admin-tabs.blade.php` ikut menyesuaikan flag modul terkait, dan seluruh rute controller mengembalikan `404`.
- Helper navigasi `activeAdminTab()` turut menandai tab `TKA` aktif untuk rute `admin.tka-kesiapan.*`.

### Migrasi & Backup
- Migrasi bersifat **aditif penuh**:
  1. `database/migrations/2026_11_16_090000_create_tka_kesiapan_tables.php` (membuat `tka_kesiapan_pengaturan`, `tka_kesiapan_pemetaan`, `tka_kesiapan_tindak_lanjut`, `tka_kesiapan_tindak_lanjut_revisi`, `tka_kesiapan_audit`).
  2. `database/migrations/2026_11_16_120000_add_alasan_to_tka_kesiapan_audit_tables.php` (menambah kolom nullable `alasan` pada `tka_kesiapan_audit` & `tka_kesiapan_tindak_lanjut_revisi` dengan guard `Schema::hasColumn`).
- Kelima tabel terdaftar pada `App\Support\BackupTables` dalam urutan dependensi FK (`tenants`, `users`, `siswa` sebelum tabel `tka_kesiapan_*`) sehingga dicakup dalam backup/restore SIMANTAP.

### Langkah Rollback Cepat
- Nonaktifkan fitur tanpa menyentuh skema database: set `TKA_KESIAPAN_AKTIF=false` di `.env` lalu jalankan `php artisan config:clear`.
- Jika perlu membatalkan migrasi tambahan di lingkungan uji/non-produksi (secara berurutan dari terbaru):
  ```sh
  php artisan migrate:rollback --path=database/migrations/2026_11_16_120000_add_alasan_to_tka_kesiapan_audit_tables.php
  php artisan migrate:rollback --path=database/migrations/2026_11_16_090000_create_tka_kesiapan_tables.php
  ```

---

## 7. Keterbatasan yang Masih Berlaku

1. **Naskah Primer PDF Pusmendik:** Naskah lengkap *Perkaban BSKAP Nomor 047/H/AN/2025* belum disimpan di repository; pemetaan `bahasa_indonesia` dan `matematika` berstatus *"Pemetaan sementara — perlu verifikasi sekolah"*.
2. **Data Historis Tanpa Kolom Kelas:** Tabel `nilai`, `tka_student_assessments`, dan `tka_adaptive_simulations` tidak menyimpan kolom `kelas` historis; bila tidak ada bukti berkelas valid di periode `(tahun_ajaran, semester)` yang sama (mis. `nilai_erapor`, `kuis`, atau `exam_sessions`), bukti tetap ditandai *"Jenjang historis tidak diketahui"* dan tidak dimasukkan ke lintasan faktual Kelas 4–6.
3. **Skala & Simulasi Adaptif:** Normalisasi 0–100 hanyalah estimasi internal untuk ringkasan per sumber; skor simulasi adaptif (CAT) dan lintas instrumen tidak setara langsung dengan ujian linier atau rapor.
4. **Kapasitas Tabel Nilai Mapel/CP:** Tabel `nilai_mapel`, `capaian_pembelajaran`, dan `nilai_cp` belum memiliki modul input aktif sehingga belum dapat dijadikan sumber bukti otomatis.
