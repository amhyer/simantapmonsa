# Rencana Induk Perbaikan dan Pengembangan SIMANTAP

**Dokumen kerja terpadu** untuk mencatat kondisi aplikasi, pekerjaan yang perlu diselesaikan, dan ide pengembangan agar sekolah lebih mudah menggunakan SIMANTAP. Dokumen ini menggabungkan hasil pembahasan menu, isolasi sekolah, Dapodik, presensi, dan usulan fitur ke depan.

**Pembaruan:** 7 November 2026. Rujukan nama file audit lama dapat memakai tanggal berbeda; status di bawah merangkum keadaan pekerjaan pada saat dokumen ini dibuat.

## 1. Cara membaca status

| Penanda | Arti |
|---|---|
| **Sudah ada** | Fitur atau menu ditemukan dalam kode. Tidak berarti seluruh fungsinya sudah teruji. |
| **Kode disiapkan — belum teruji** | Perubahan atau tes telah ditulis, tetapi hasilnya belum dibuktikan melalui pengujian Laravel, database, dan browser. |
| **Dibatasi sementara** | Fitur sengaja ditolak atau disembunyikan pada kondisi tertentu untuk mengurangi risiko. Bukan berarti fitur pengganti sudah tersedia. |
| **Belum dikerjakan** | Masalah yang masih memerlukan implementasi atau penyelesaian. |
| **Usulan** | Ide ke depan, belum merupakan keputusan final atau janji jadwal. |

> **Penting:** audit ini terutama berdasarkan pemeriksaan statis kode dan dokumen pekerjaan. PHP/Composer native dan dependensi `vendor` belum tersedia di lingkungan kerja sehingga PHPUnit, migrasi, pengujian database, Blade, dan browser belum dijalankan. Lint sintaks PHP WebAssembly terhadap 64 file serta `git diff --check` telah lolos pada pemeriksaan terakhir; itu bukan bukti aplikasi berfungsi atau aman di produksi.

## 2. Ringkasan untuk pengambil keputusan

SIMANTAP sudah memiliki banyak menu: pengelolaan sekolah dan pengguna, data siswa dan guru, pembelajaran, e-Rapor, CBT/Tryout/TKA, presensi, pemantauan kepala sekolah, serta halaman siswa dan orang tua. Sebelum menambah banyak menu baru, prioritasnya adalah:

1. **Amankan dan uji fitur yang sudah ada**, terutama isolasi data antar-sekolah, presensi, backup, dan integrasi.
2. **Pulihkan fungsi yang dibatasi** dengan rancangan yang benar-benar terisolasi per sekolah.
3. **Permudah pekerjaan harian** melalui notifikasi/tugas, pemeriksaan kelengkapan data, dan panduan.
4. Tambahkan fitur baru secara bertahap berdasarkan kebutuhan sekolah, tanpa memenuhi sidebar dengan menu yang berulang.

**Keputusan saat ini:** mode presensi offline belum diaktifkan; penerapannya menunggu rancangan konflik, keamanan perangkat, dan privasi yang diuji.

## 3. Peta menu dan tindak lanjut per peran

Ini adalah ringkasan menu utama dari pemeriksaan kode, bukan jaminan bahwa seluruh tombol berfungsi pada setiap konfigurasi.

### 3.1 Super Admin

**Sudah ada:** dashboard platform, katalog dan pendaftaran sekolah, serta daftar akun pengguna. Pengaturan langganan berada pada pengelolaan sekolah.

- **Kode disiapkan — belum teruji:** tautan tambah sekolah diarahkan ke formulir; penamaan grup pengguna diperjelas.
- **Dibatasi sementara:** penghapusan sekolah dan pemindahan langsung akun antar-sekolah melalui antarmuka ditolak untuk mencegah data lama berubah menjadi data global atau berpindah tanpa rekonsiliasi.
- **Perlu diselesaikan:** prosedur aman menonaktifkan sekolah, memindahkan akun/data dengan verifikasi, dan mengelola langganan; uji role dan isolasi di browser.
- **Usulan:** ringkasan kesehatan layanan, kapasitas, status backup platform, dan sekolah yang perlu bantuan. Batasi informasi pribadi sesuai kewenangan Super Admin.

### 3.2 Admin sekolah

**Sudah ada:** dashboard; pengaturan sekolah, semester, guru, siswa, rombel, pembelajaran, akun, Dapodik, presensi, kartu QR, penilaian/e-Rapor, transkrip, cetak, modul asesmen, dan sebagian pengaturan sistem.

- **Kode disiapkan — belum teruji:** navigasi tab/menu dan indikator sinkronisasi; pembatasan pengguna, kartu, koreksi presensi, dan sebagian referensi ke sekolah aktif.
- **Dibatasi sementara pada instalasi ber-tenant:** backup/restore/wipe global, sebagian log aktivitas, bulk account, Bridge/push/autosync Dapodik lama, serta tarik langsung mesin presensi global. Detail dampaknya ada pada bagian 4 dan 5.
- **Perlu diselesaikan:** isolasi fitur global yang tersisa, impor dan backup per sekolah, pengujian cetak dan alur administrasi, serta validasi data sebelum sekolah mulai beroperasi.
- **Usulan:** menu **Validasi & Kelengkapan Data** dan pusat tugas. Sebagian notifikasi lebih baik muncul di dashboard daripada membuat banyak item sidebar.

### 3.3 Guru

**Sudah ada:** dashboard, materi, kuis dan penilaian, nilai/e-Rapor, asesmen sesuai konfigurasi, kehadiran siswa, presensi sendiri, catatan siswa, kebiasaan, laporan, dan pengaturan.

- **Kode disiapkan — belum teruji:** penanda menu aktif, pembenahan presensi/status, jumlah yang belum dicatat, dan halaman verifikasi izin orang tua.
- **Perlu diselesaikan:** uji pembelajaran dan penilaian setiap kelas, ketepatan rekap, sinkronisasi Google Sheets, alias mapel/agama, serta hak akses ke kelas dan siswa.
- **Usulan:** **Tugas Hari Ini**, agenda mengajar, pengingat penyelesaian nilai, dan komunikasi terbatas dengan orang tua. Jangan duplikasi halaman kehadiran yang sudah ada.

### 3.4 Siswa

**Sudah ada:** beranda, kartu pelajar, materi, kuis, nilai, profil, dan fitur ujian/asesmen sesuai konfigurasi.

- **Kode disiapkan — belum teruji:** pembenahan navigasi, penanda menu aktif, dan penyajian menu TKA mandiri sesuai rute yang tersedia.
- **Perlu diselesaikan:** uji akses hanya ke akun/data sendiri, kartu QR, materi, kuis, nilai, serta perilaku modul asesmen aktif dan nonaktif.
- **Usulan:** agenda pelajaran dan ujian, notifikasi materi/tugas, serta panduan singkat. Pastikan informasi dan tampilan sesuai usia siswa.

### 3.5 Orang tua

**Sudah ada:** ringkasan, kebiasaan, perkembangan nilai, kehadiran anak, catatan guru, laporan/rapor, dan laporan TKA sesuai konfigurasi.

- **Kode disiapkan — belum teruji:** tampilan persentase berdasarkan hari yang tercatat dan pengajuan izin/sakit untuk anak yang tertaut, dengan verifikasi wali kelas.
- **Perlu diselesaikan:** uji orang tua dengan satu/beberapa anak, akun yang belum tertaut, pemisahan data antar-sekolah, status izin, dan tampilan ponsel.
- **Usulan:** notifikasi keputusan izin, agenda sekolah/kelas, dan permintaan komunikasi dengan guru. Hindari menampilkan data anak lain atau rincian sensitif di notifikasi.

### 3.6 Kepala sekolah

**Sudah ada:** dashboard, rekap, presensi sendiri, pemantauan, rekap kebiasaan, dan menu peta kelas/hasil belajar.

- **Kode disiapkan — belum teruji:** pembatasan sebagian dashboard, aktivitas, guru, dan rekap pada sekolah aktif.
- **Dibatasi sementara:** Peta Kelas dan Hasil Belajar untuk instalasi ber-tenant karena sebagian pengaturan sumber masih global. KKM sementara 70 dipakai pada area tertentu dan diberi peringatan; jangan dijadikan keputusan akademik final sebelum divalidasi.
- **Perlu diselesaikan:** pengaturan KKM serta sumber pemetaan per sekolah, data historis, dan pemeriksaan rincian yang mendasari angka rekap.
- **Usulan:** ringkasan **Rekap & Tindak Lanjut** yang dapat diklik menuju kelas atau data bermasalah, bukan dashboard baru yang mengulang statistik lama.

### 3.7 Tenaga kependidikan

**Sudah ada:** presensi sendiri, kartu identitas, dan ganti kata sandi.

- **Perlu diselesaikan:** uji seluruh alur presensi dan hak akses akun tenaga kependidikan.
- **Usulan:** tugas administrasi dan dokumen sekolah sesuai penugasan. Jangan memberi akses admin penuh hanya untuk menyediakan menu tambahan.

## 4. Perbaikan paling mendesak: keamanan dan isolasi sekolah

### 4.1 Isolasi lintas sekolah dan hak akses

**Masalah:** beberapa model, konfigurasi, cache, pekerjaan antrean, serta tabel lama bersifat global atau memiliki data historis tanpa pemilik sekolah yang dapat dipastikan. Pemeriksaan satu halaman saja tidak membuktikan seluruh aplikasi terisolasi.

**Kode disiapkan — belum teruji:**

- Pemeriksaan konteks sekolah pada grup peran dan pembatasan sejumlah query akun, referensi, dashboard, presensi, dan log.
- Identifikasi tenant sebelum route model binding pada web; pemeriksaan konflik identitas sekolah dan header.
- Penanda kepemilikan pada beberapa data agar data yatim tidak otomatis dianggap data sekolah tunggal.
- Tes regresi awal untuk dua sekolah, akses lintas ID, akun tanpa sekolah, log, dan referensi.

**Yang masih perlu dilakukan:**

- Audit semua jalur baca/tulis, API, ekspor, relasi, cache, antrean, dan penghapusan: bukan hanya menu yang terlihat.
- Uji dua sekolah dan berbagai peran, termasuk URL/ID langsung, header palsu, akun nonaktif, akun tanpa sekolah, serta data setelah perubahan status tenant.
- Audit keamanan aplikasi dan lakukan pengujian penetrasi sebelum penggunaan SaaS produksi.
- Pertahankan kompatibilitas sekolah tunggal dan uji secara terpisah.

### 4.2 Data lama, identitas, dan akun

**Belum selesai:** sebagian data lama belum dapat dipetakan dengan aman ke sekolah. Username dan NIS masih memiliki batasan unik global yang dapat menyebabkan benturan antar-sekolah; sandi lama yang lemah tidak otomatis diperbaiki.

**Tindak lanjut:**

1. Inventarisasi data lama dan sumber kepemilikan yang tepercaya.
2. Lakukan rekonsiliasi dengan bukti, pencatatan perubahan, dan backup; jangan menebak pemilik hanya dari NISN, NPSN, atau akun yang saat ini terhubung.
3. Rancang perubahan identitas/login dan constraint unik beserta migrasi serta rollback yang diuji.
4. Sediakan prosedur reset/distribusi sandi yang aman. Jangan mengungkap sandi atau informasi sensitif melalui log/notifikasi.

### 4.3 Backup dan pemulihan

**Dibatasi sementara:** backup, restore, dan penghapusan data global tidak tersedia bagi tenant karena belum terisolasi. Pendaftaran beberapa tabel presensi ke registry backup sekolah tunggal sudah disiapkan, tetapi belum diuji restore.

**Belum dikerjakan:** backup dan restore per sekolah yang terisolasi, termasuk izin akses, media penyimpanan, enkripsi, retensi, uji pemulihan, dan audit. Sampai tersedia, operator perlu backup platform melalui prosedur infrastruktur yang aman dan terkontrol.

## 5. Dapodik, referensi, dan integrasi

### Kondisi dan dampak

- **Kode disiapkan — belum teruji:** isolasi log sinkronisasi baru, cache indikator per sekolah, sebagian API key/status, serta konteks tenant pada job impor siswa.
- **Dibatasi sementara pada sistem ber-tenant:** Bridge penulisan lama, konfigurasi/push dan auto-sync lama, karena sumber konfigurasi/PTK masih global. Status API tertentu dan riwayat yang sudah terscope tetap tersedia sesuai haknya.
- **Belum selesai:** backup per sekolah, konfigurasi dan PTK yang benar-benar per sekolah, penanganan konflik identitas, dan antarmuka unggah manual yang lengkap. Job lama tanpa konteks tenant dapat gagal secara aman dan perlu ditinjau.
- Sebagian pengaturan guru, semester, atau sumber lain masih perlu audit dan rancangan kepemilikan; jangan menganggap seluruh tabel sudah terisolasi.

### Pekerjaan berikutnya

1. Tetapkan pemilik dan konfigurasi integrasi setiap sekolah; jangan memakai satu konfigurasi global untuk data beberapa sekolah.
2. Rancang impor yang dapat mempratinjau penambahan, perubahan, konflik, dan data yang dilewati sebelum disimpan.
3. Rekonsiliasi log dan data lama secara manual berdasarkan bukti. Jangan melakukan backfill otomatis berdasarkan NPSN/akun saat ini saja.
4. Uji dua sekolah, antrean/retry, API key, pembatalan, dan hasil setelah restart worker.
5. Buka kembali fungsi yang dibatasi hanya setelah isolasi dan uji database berhasil.

## 6. Presensi: perbaikan dan pekerjaan tersisa

### 6.1 Keamanan QR, GPS, dan mesin

**Kode disiapkan — belum teruji:**

- Pembatasan akses kartu, koreksi, dan pencocokan pegawai berdasarkan sekolah.
- Pencabutan QR menghapus token aktif dan transisi; penerbitan baru memakai counter dan nonce. Satu token sebelumnya dipertahankan saat rotasi biasa untuk transisi, bukan setelah pencabutan.
- Rollback kartu dibatasi pada token aktif/transisi yang masih tercatat. Kartu fisik eksternal tidak direkonstruksi untuk cetak.
- GPS wajib gagal aman bila zona aktif belum tersedia; koordinat dan akurasi divalidasi.
- Log mesin yang hanya memiliki jam pulang tidak membuat presensi hadir baru. PIN/NIK ambigu tidak dipilih secara diam-diam.

**Dampak operasional:**

- QR mandiri pegawai **mewajibkan GPS secara default**. Setiap sekolah perlu zona aktif; opt-out `PRESENSI_WAJIB_GPS_SCAN_QR_PEGAWAI=false` berarti QR tidak memverifikasi lokasi.
- Migrasi menandai akun/siswa lama tanpa hash QR sebagai dicabut karena kartu yang belum pernah terbit tidak dapat dibedakan dengan aman dari kartu lama yang sudah dilepas. Admin perlu menerbitkan kartu secara eksplisit.
- Tarik/tes langsung database mesin diblokir untuk sistem multi-sekolah; impor file masih tersedia dengan pencocokan sekolah. GPS browser dapat dipalsukan; bukan bukti anti-kecurangan.
- Jangan rollback kode/skema QR sesudah kartu baru diterbitkan tanpa prosedur pencabutan dan penerbitan ulang; kode lama tidak mengenali nonce dan dapat menghidupkan kartu lama.

**Yang masih perlu dilakukan:** uji paralel penerbitan/bind/pencabutan dan keunikan QR fisik lintas tabel pada database produksi; audit impor dan zona dua sekolah; rancang koneksi mesin per sekolah bila fitur tarik langsung akan dibuka kembali.

### 6.2 Akurasi dan rekap

**Kode disiapkan — belum teruji:** validasi urutan jam koreksi, sumber masuk/pulang terpisah, perbaikan waktu hadir massal mapel, perlindungan status sakit/izin terhadap scan, penyesuaian rekap, serta sinkronisasi harian pada beberapa perubahan manual/massal.

**Batas yang perlu dipahami:**

- Persentase orang tua dihitung dari **hari yang sudah dicatat**, bukan dari seluruh hari efektif. Belum dicatat tidak otomatis berarti alpa.
- Keterlambatan impor mesin masih memakai satu batas jam global; scan QR/GPS tidak menebak keterlambatan karena jadwal kerja per sekolah/pegawai belum tersedia.
- Histori metode masuk/pulang yang lama tidak ditebak. Perlu pemeriksaan data nyata sebelum menarik kesimpulan.
- Filter mapel agama hanya berlaku untuk nama/agama yang dikenali; agama kosong tetap ditampilkan agar siswa tidak tersembunyi diam-diam.
- Sinkronisasi Google Sheets bersifat *best effort*, bukan transaksi bersama database. Antrean ulang, rekonsiliasi, dan pengujian performa bulk masih diperlukan.

**Yang masih perlu dilakukan:** uji perubahan status, pembatalan, pencatatan serentak dan konflik; verifikasi laporan, kebijakan jam kerja per sekolah, akurasi hari efektif, serta sinkronisasi gagal/berulang.

### 6.3 Pengajuan izin orang tua dan tindak lanjut

**Kode disiapkan — belum teruji:** orang tua dapat mengajukan izin/sakit bagi anak aktif yang tertaut untuk hari ini hingga tujuh hari sebelumnya. Satu pengajuan per anak/tanggal; wali rombel memeriksa dan menyetujui/menolak. Siswa tanpa rombel memakai penugasan guru pada siswa sebagai fallback. Permohonan pending tidak mengubah kehadiran dan persetujuan tidak menimpa hadir atau jenis izin/sakit berbeda.

**Perlu diselesaikan:** uji otorisasi, perubahan wali/kelas, konflik bersamaan, duplikasi, penolakan, dan kemudahan perbaikan pengajuan; jumlah pending dapat mencakup semua data sementara halaman review dibatasi 100 terbaru. Rancang notifikasi keputusan dan kebijakan masa simpan.

**Privasi:** minta alasan minimum, jangan meminta diagnosis yang tidak diperlukan, batasi akses, serta lindungi backup dan catatan manual. Tidak ada notifikasi otomatis yang dapat dianggap sudah tersedia.

### 6.4 Offline — usulan, belum diaktifkan

Sebelum implementasi, tetapkan identitas dan pencabutan perangkat, enkripsi dan penghapusan data lokal, batas retensi, ID idempotensi per peristiwa, validasi ulang hak dan token oleh server, serta mekanisme review konflik. Perubahan saat offline tidak boleh otomatis menimpa izin, kartu yang sudah dicabut, atau kelas yang sudah berubah. Waktu dan GPS perangkat tidak boleh langsung dianggap tepercaya. Uji kehilangan perangkat, pengiriman ulang, dan sinkronisasi bersamaan.

## 7. Navigasi dan pengalaman pengguna

**Kode disiapkan — belum teruji:** penanda menu aktif pada sebagian halaman detail/edit, pengelompokan sidebar, penjelasan shortcut admin lintas tab, perbaikan tautan tambah sekolah, serta penyesuaian visibilitas beberapa menu TKA terhadap route yang tersedia.

**Masih perlu diverifikasi:**

- Klik setiap menu, submenu, tombol kembali, detail, edit, cetak, dan formulir untuk semua tujuh peran.
- Periksa status yang diharapkan: 200, redirect, 403, atau 404 sesuai hak akses; tes yang hanya mencari error 500 tidak cukup.
- Uji modul CBT/Tryout/TKA dalam konfigurasi aktif dan nonaktif. Beberapa route TKA mandiri berada di luar flag CBT; jangan menganggap semuanya ditutup oleh flag tersebut.
- Uji tab admin yang berpindah konteks, label, sidebar panjang, tampilan ponsel, aksesibilitas, dan pengguna baru.
- Gunakan istilah yang konsisten dan hindari menu ganda bila sebuah fitur sudah tersedia pada halaman yang tepat.

## 8. Usulan fitur dan menu ke depan

Semua item pada bagian ini **masih usulan**, kecuali bila secara eksplisit disebut sebagai penyempurnaan fitur yang sudah ada. Persetujuan, prioritas, dan rincian produk perlu dibahas bersama sekolah.

### Prioritas A — dampak cepat

| Usulan | Pengguna | Fungsi awal yang disarankan | Pertimbangan |
|---|---|---|---|
| **Pusat Notifikasi & Tugas** | Semua peran | Tampilkan izin menunggu, presensi belum dicatat, nilai belum lengkap, agenda dan keputusan; setiap item menuju halaman terkait. | Data harus sesuai peran dan sekolah. Jangan tampilkan alasan sensitif di pratinjau. Mulai dari notifikasi di aplikasi. |
| **Validasi & Kelengkapan Data** | Admin | Daftar siswa tanpa rombel, rombel tanpa wali, jadwal tanpa guru/kelas, akun tanpa sekolah, dan PIN/NIK ambigu. | Setiap masalah memiliki penjelasan dan tautan perbaikan; jangan otomatis mengubah data. |
| **Bantuan & Panduan** | Semua peran | Panduan singkat sesuai tugas dan tombol bantuan pada halaman terkait. | Gunakan bahasa sekolah, langkah sederhana dan kontak bantuan yang dapat diperbarui. |
| **Pencarian menu** | Semua peran | Cari halaman berdasarkan nama/istilah seperti izin, rombel, presensi, atau rapor. | Hanya tampilkan tujuan yang boleh diakses peran pengguna. |

### Prioritas B — koordinasi sekolah

| Usulan | Pengguna | Fungsi awal yang disarankan | Pertimbangan |
|---|---|---|---|
| **Kalender & Agenda Sekolah** | Admin, guru, siswa, orang tua | Jadwal pembelajaran/ujian, tanggal rapor, dan kegiatan sekolah/kelas. | Tampilkan sesuai sekolah, kelas, dan peran. Mulai internal tanpa integrasi eksternal wajib. |
| **Pengumuman Sekolah** | Admin, guru, siswa, orang tua | Pengumuman untuk seluruh sekolah atau sasaran kelas/peran tertentu. | Bedakan dari kalender; atur siapa yang boleh membuat, mengubah, dan melihat. |
| **Rekap & Tindak Lanjut** | Kepala sekolah dan admin | Ringkasan kelas belum tercatat, kelengkapan nilai, izin menunggu, dan masalah data, dengan tautan ke rincian. | Perkuat rekap yang sudah ada, bukan membuat duplikat; jangan menyamakan belum tercatat dengan alpa. |

### Prioritas C — setelah kebutuhan disepakati

| Usulan | Pengguna | Fungsi awal yang disarankan | Pertimbangan |
|---|---|---|---|
| **Komunikasi guru–orang tua** | Guru dan orang tua | Pesan terbatas atau permintaan pertemuan terkait anak. | Melengkapi Catatan Guru yang ada; perlu kontrol akses, moderasi, retensi, dan perlindungan data anak. |
| **Dokumen & Surat Sekolah** | Admin dan tenaga kependidikan yang diberi hak | Template surat keterangan siswa aktif, surat tugas, atau dokumen rutin lainnya. | Format dan prosedur tiap sekolah berbeda; tentukan template, penandatangan, nomor surat, dan audit terlebih dahulu. |
| **Notifikasi eksternal** | Pihak terkait | Email atau kanal lain untuk informasi yang disetujui. | Integrasi WhatsApp/pesan berbayar tidak otomatis diaktifkan; perlu persetujuan, pengelolaan biaya, privasi, dan pengujian. |
| **Presensi offline** | Guru/pegawai | Pencatatan sementara dan sinkronisasi setelah tersambung. | Ditunda sampai syarat keamanan, konflik, dan privasi pada bagian 6.4 terpenuhi. |

## 9. Tahapan kerja yang direkomendasikan

Ini urutan prioritas, **bukan jadwal atau janji penyelesaian**.

### Tahap 0 — verifikasi dan keselamatan data

- [ ] Siapkan lingkungan staging, PHP/Composer proyek, dependensi Laravel, database uji, dan akun tujuh peran.
- [ ] Cadangkan database dan uji pemulihan; inventarisasi perubahan branch dan migrasi yang belum dipasang.
- [ ] Jalankan suite tes, migrasi, uji dua sekolah, uji sekolah tunggal, dan browser/mobile.
- [ ] Perbaiki kegagalan; uji migrasi/rollback dengan database dan salinan data yang sesuai produksi.
- [ ] Putuskan apakah dan kapan perubahan boleh dipasang. Jangan nyatakan siap produksi hanya karena lint lolos.

**Selesai bila:** hasil tes dan risiko diketahui, pemulihan terbukti pada staging, serta penanggung jawab menerima dampak migrasi dan fitur yang dibatasi.

### Tahap 1 — tuntaskan isolasi dan fungsi kritis

- [ ] Audit seluruh permukaan aplikasi per tenant dan per peran; tutup celah yang ditemukan.
- [ ] Rancang dan implementasikan backup/restore, konfigurasi Dapodik, dan integrasi mesin per sekolah.
- [ ] Rekonsiliasi data lama serta rancang identitas unik dan transfer akun yang aman.
- [ ] Validasi KKM dan sumber peta kelas/hasil belajar per sekolah.

**Selesai bila:** akses lintas sekolah dan pemulihan diuji; fitur yang masih belum aman tetap dibatasi dan didokumentasikan.

### Tahap 2 — mudahkan pekerjaan harian

- [ ] Bangun Pusat Notifikasi & Tugas dan validasi kelengkapan data.
- [ ] Tambahkan panduan per peran dan pencarian menu.
- [ ] Tinjau hasil dengan contoh tugas nyata admin, guru, kepala sekolah, siswa, dan orang tua.

**Selesai bila:** pengguna bisa menemukan tugas, memahami masalah, dan menuju halaman yang tepat tanpa melihat data di luar haknya.

### Tahap 3 — kolaborasi dan pengembangan lanjutan

- [ ] Tentukan kebutuhan kalender, pengumuman, komunikasi, dan dokumen sekolah.
- [ ] Definisikan otorisasi, privasi, retensi, biaya, dan prosedur operasional sebelum implementasi.
- [ ] Tinjau rancangan offline secara terpisah; jangan mengaktifkannya sebelum konflik dan keamanan diuji.

**Selesai bila:** fitur disetujui sekolah, terbukti bermanfaat dan teruji, serta tidak mengulang menu yang sudah ada.

## 10. Daftar pengujian dan persiapan penerapan

### Sebelum migrasi

1. Pastikan salinan/backup database dan file tersedia dan dapat dipulihkan. Jangan menguji wipe atau restore pada data produksi.
2. Identifikasi empat migrasi baru pada branch: `2026_11_08_000001` dan `000002` untuk perubahan isolasi sebelumnya; `000003` dan `000004` untuk presensi/izin. Periksa urutan dan dampaknya pada staging sebelum `php artisan migrate`.
3. Siapkan zona GPS setiap sekolah, rencanakan penerbitan QR, dan beri tahu sekolah mengenai perubahan default serta fungsi yang dibatasi.
4. Rencanakan penanganan job lama dan restart worker; bila memakai cache deployment, bersihkan konfigurasi/rute/view sesuai prosedur.

### Tes otomatis yang disiapkan

Jalankan dengan PHP dan dependensi proyek pada lingkungan yang aman:

```sh
php artisan test --filter=PresensiSecurityWorkflowTest
php artisan test --filter=PresensiTest
php artisan test --filter=PresensiMesinImportTest
php artisan test --filter=PresensiMesinTarikTest
php artisan test --filter=SchoolReferenceIsolationTest
php artisan test --filter=DapodikTenantIsolationTest
php artisan test --filter=MenuNavigationRegressionTest
php artisan test
```

Tes ini **sudah disiapkan, tetapi belum dijalankan** di lingkungan kerja saat dokumen dibuat. Tambahkan tes untuk sinkronisasi gagal, impor dan backup dua tenant, race condition, serta seluruh fitur yang belum tercakup. Uji migrasi dan restore dengan jenis database yang sama dengan produksi, bukan SQLite saja.

### Uji manual minimum

- Dua sekolah: masuk dengan tiap peran; daftar, detail, ID langsung, header yang bertentangan, dan data historis.
- Sekolah tunggal: fitur lama tetap berfungsi tanpa mengklaim data tenant lain.
- Presensi: QR lama/hilang/terbit ulang, lokasi kosong/ditolak, izin orang tua, konflik status, scan dan impor hanya-pulang, koreksi, rekap, serta Google Sheets gagal.
- Dapodik dan backup: pastikan fungsi yang tidak aman diblokir, dan backup/restore yang memang tersedia diuji pada lingkungan terisolasi.
- Antarmuka: setiap menu, validasi formulir, tombol, cetak, aksesibilitas dasar, ponsel, dan flag modul aktif/nonaktif.

## 11. Keputusan yang perlu dibahas dengan sekolah

1. Siapa penanggung jawab verifikasi data lama, hak akses, backup, dan persetujuan penerapan?
2. Apakah GPS wajib untuk seluruh pegawai? Bagaimana penanganan kegiatan di luar sekolah dan perangkat tanpa GPS? Opt-out mengurangi perlindungan lokasi.
3. Bagaimana jadwal kerja dan ambang keterlambatan yang berbeda antar-sekolah/pegawai ditetapkan?
4. Siapa wali yang berwenang memverifikasi izin, berapa lama data disimpan, dan bagaimana koreksi pengajuan yang sudah ditolak?
5. Notifikasi apa yang penting dan melalui kanal apa? Apa yang boleh tampil tanpa membuka detail?
6. Apakah kalender, pengumuman, komunikasi, atau dokumen sekolah paling dibutuhkan terlebih dahulu?
7. Apa target operasional untuk pemulihan backup, antrean ulang integrasi, pelatihan pengguna, dan dukungan sekolah?

## 12. Catatan sumber dan pemeliharaan

Dokumen ini adalah ringkasan utama yang dapat diperbarui saat status pekerjaan berubah. Rincian teknis dan jejak audit awal tetap disimpan di:

- `docs/AUDIT-MENU-PER-ROLE-2026-11-08.md` — inventaris dan temuan navigasi awal; sebagian isinya menggambarkan keadaan sebelum perubahan.
- `docs/PERBAIKAN-ISOLASI-DAPODIK-2026-11-08.md` — isolasi dan pembatasan integrasi sebelumnya.
- `docs/PRESENSI-PENGERASAN-DAN-IZIN-2026-11-08.md` — detail operasional presensi, migrasi, dan risiko.

Setiap kali pekerjaan selesai, ubah status hanya setelah ada bukti pengujian, catat tanggal serta hasilnya, dan perbarui bagian pembatasan atau dampak operasional yang berubah.
