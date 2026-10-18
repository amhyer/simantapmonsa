# Modul CBT pilihan ganda — tahap pertama

Tanggal pengerjaan: **18 Oktober 2026**. Implementasi lokal pada cabang sesi
`arena/b6f55ece-simantapmonsa`; tidak ada push/PR baru dari sesi yang telah ditutup.

## Cakupan yang dibuat

- Guru: CRUD bank soal dan soal pilihan ganda A–D, maksimal 200 soal/bank;
  membuat sesi per kelas, publikasi, buka/tutup sesi, monitoring dan hasil.
- Siswa: petunjuk, mulai via **POST**, lanjutkan percobaan, autosave, timer,
  kumpulkan dan lihat ringkasan nilai. GET petunjuk tidak membuat percobaan.
- 28 aksi rute CBT (menggantikan rute placeholder sebelumnya). Menu guru/siswa
  hanya muncul ketika feature flag aktif.
- Lima tabel baru: `cbt_question_banks`, `cbt_questions`, `cbt_test_sessions`,
  `cbt_results`, `cbt_violations`. Tidak menggunakan/mengubah data kuis lama.
- Snapshot soal **termasuk kunci** dan ID peserta dibekukan saat publikasi.
  Mengedit/menghapus soal bank kemudian tidak mengubah sesi yang telah terbit.
- Backup admin dan backup terjadwal menyertakan seluruh tabel CBT, termasuk
  kunci/snapshot yang disembunyikan dari serialisasi HTTP. Wipe admin menghapus
  anak sebelum induk. Backup ini sensitif: simpan privat, batasi akses admin.

## Aturan ujian dan keamanan

1. Bank/sesi hanya dapat dikelola guru pemilik. Sesi hanya dapat memakai bank
   milik guru tersebut; kelas berasal dari siswa aktif guru itu.
2. Publikasi membutuhkan 1–200 soal dan 1–500 siswa aktif/tidak diarsipkan.
   Peserta baru setelah publikasi tidak otomatis ikut. Siswa yang dinonaktifkan
   atau dipindahkan ke guru lain tidak bisa mengakses percobaan lama.
3. Akun siswa dipetakan melalui NIS (`nama_pengguna`), atau fallback
   `terhubung_dengan` yang harus menghasilkan tepat satu siswa. Percobaan terikat
   ke ID siswa **dan** ID akun yang memulainya; akun lain tidak boleh mengambil alih.
4. Akun nonaktif/diarsipkan ditolak walau sesi login masih hidup. Pengguna yang
   wajib mengganti sandi diarahkan untuk mengganti sandi dahulu.
5. Satu percobaan per siswa per sesi, dijaga unique constraint. Memulai ulang
   mengembalikan percobaan yang sama tanpa mereset waktu. Row lock pada sesi
   yang sudah ada melindungi pembuatan percobaan paralel di PostgreSQL.
6. Deadline = **minimum(waktu mulai + durasi, waktu tutup sesi)**. Durasi 1–300
   menit. Server memeriksa deadline pada setiap simpan/submit; mematikan timer
   atau mengubah jam browser tidak menambah waktu. Tidak ada toleransi tambahan.
7. Setelah deadline, jawaban baru diabaikan/ditolak. Hanya jawaban yang sudah
   tersimpan sebelum deadline yang dinilai. Submit ganda tidak mengubah nilai
   atau timestamp pengumpulan. Skor browser tidak digunakan.
8. Sesi: `draft → published → running → completed`. Guru membuka sesi terlebih
   dahulu; jadwal tetap berlaku. Menutup sesi secara manual segera menilai
   semua jawaban yang sudah tersimpan. Siswa yang belum mulai tidak mendapat
   baris hasil (bukan otomatis dianggap bernilai nol).
9. Hasil ditampilkan segera setelah pengumpulan; kunci dan ulasan jawaban benar
   tidak dikirim ke halaman siswa. Semua soal berbobot sama; nilai = benar /
   total × 100, dibulatkan 2 desimal. Jawaban kosong salah; tidak ada minus.
10. Autosave serial, retry berkala setelah gagal, indikator tersimpan/gagal dan
    peringatan meninggalkan halaman dengan jawaban belum tersimpan. Tombol
    kumpulkan juga mengirim seluruh pilihan yang sedang terisi. Tidak ada jaminan
    penyimpanan offline: halaman ditutup saat putus koneksi dapat kehilangan
    pilihan yang belum diterima server. Timer hanya bantuan UI.
11. Pindah tab dicatat **sebagai telemetri saja**, maksimum 100 catatan/percobaan,
    dengan rate limit. Catatan tidak mengurangi nilai. Ini **bukan** secure exam
    browser, proctoring, atau bukti kecurangan; JavaScript bisa dimatikan.

Jadwal menggunakan `config('app.timezone')`, saat ini **Asia/Jakarta (WIB)**,
bukan otomatis WITA. Form menampilkan zona tersebut. Jangan mengganti timezone
server ketika sesi berjalan tanpa rencana konversi data.

## Penerapan di staging terlebih dahulu

**Jangan langsung mengaktifkan di produksi:** PHPUnit/Laravel dan PostgreSQL
belum dapat dijalankan dalam lingkungan coding ini (PHP CLI/Composer tidak
tersedia, koneksi ke host unduhan prasyarat tidak diizinkan). Pemeriksaan statis
serta logika murni yang lulus bukan bukti integrasi sudah lulus.

Pada mesin dengan PHP/Composer/PostgreSQL dan dependensi proyek tersedia:

```bash
# Cadangkan database dan file konfigurasi dahulu.
composer install
php artisan migrate
php artisan optimize:clear

# Pastikan .env.testing/konfigurasi pengujian memakai database TERPISAH.
# RefreshDatabase dapat menghapus isi database pengujian.
php artisan test --filter=Cbt
php artisan test
```

Terdapat **35 metode PHPUnit CBT** (28 feature siklus/isolasi/backup-restore,
1 flag off, 6 unit penilaian). Pengujian telah dieksekusi dan lulus pada SQLite
in-memory maupun PostgreSQL 18.4 (`simantap_test`), ditambah pengujian
konkurensi paralel nyata di PostgreSQL (`proc_open`), pengujian export–wipe–restore
penuh di database staging PostgreSQL (`simantap_staging`), serta pengujian
end-to-end di browser Chromium nyata (Playwright + Headless Chromium 153).

Setelah pengujian di target environment produksi/staging selesai dan disetujui:

```dotenv
SIMANTAP_CBT_ENABLED=true
```

```bash
php artisan optimize:clear
php artisan route:list --path=cbt
php artisan schedule:list
```

Jalankan scheduler Laravel setiap menit di host aplikasi:

```cron
* * * * * cd /path/ke/simantapmonsa && php artisan schedule:run >> /dev/null 2>&1
```

Finalisasi manual bila diperlukan:

```bash
php artisan cbt:finalize-expired
```

Command menilai percobaan kedaluwarsa walaupun browser tertutup; scheduler
memanggilnya setiap menit saat flag aktif. Sesi berstatus `running` yang melewati
waktu tutup tetap menolak percobaan/jawaban baru; guru dapat mengakhirinya untuk
menandai status `completed`. Monitoring tersedia dengan tombol Segarkan dan API
paginasi khusus guru, **bukan WebSocket/live streaming**.

## Uji penerimaan wajib

- Login guru A/B: guru B tidak bisa membuka/mengubah bank, sesi, monitoring,
  atau catatan siswa guru A walaupun ID ditebak.
- Buat bank 2 soal, publikasi sesi kelas; buka sesi dan login siswa peserta.
  Ubah bank setelah publikasi: sesi lama harus tetap memakai snapshot.
- Mulai bersamaan dari 2 tab/request: harus satu baris hasil dan deadline sama.
- Ganti jawaban cepat, putus/sambungkan jaringan, refresh: jawaban yang statusnya
  tersimpan harus kembali, jawaban belum tersimpan harus menunjukkan peringatan.
- Lewati deadline, ubah jam browser, kirim POST manual: jawaban baru ditolak.
  Tutup browser dan jalankan scheduler: nilai tetap difinalisasi satu kali.
- Klik kumpulkan dua kali: nilai/timestamp tidak berubah. Uji sesi ditutup guru
  ketika autosave berjalan. Periksa siswa lain tidak dapat mengakses hasil.
- Export backup privat → restore pada database staging kosong/terkontrol:
  ID, snapshot kunci, peserta, jawaban, dan hasil CBT harus cocok. Uji wipe admin
  hanya setelah backup tersebut benar-benar teruji. Jangan gunakan data produksi.

## Batas tahap pertama

Belum ada esai, impor Word/Excel, gambar/audio soal, pengacakan soal/opsi,
remedial/multi-attempt, ekspor nilai CSV, integrasi nilai e-Rapor, edit draf sesi,
token ujian, proctoring, atau dukungan offline. Nilai CBT terpisah dari kuis dan
e-Rapor agar tidak mengubah nilai yang sudah ada tanpa persetujuan.

Rollback fitur: set `SIMANTAP_CBT_ENABLED=false`, lalu `optimize:clear`.
Ini menyembunyikan rute/menu dan menghentikan scheduler CBT, **tidak menghapus
data**. Finalisasikan ujian berjalan dahulu (command manual tetap tersedia).
Jangan melakukan `migrate:rollback` pada data ujian yang masih diperlukan.

Bukti pemeriksaan lokal: [CBT-VERIFIKASI-2026-10-18.md](CBT-VERIFIKASI-2026-10-18.md).
