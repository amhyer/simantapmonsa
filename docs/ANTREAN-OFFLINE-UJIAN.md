# Antrean Jawaban Offline (CBT dan Tryout)

Fitur ini membuat jawaban yang dipilih siswa **tidak hilang saat koneksi putus
atau halaman perlu dimuat ulang**. Ini bukan mode ujian sepenuhnya offline.

## Yang dijamin

- Setiap perubahan jawaban disimpan ke `localStorage` per perangkat/per sesi
  (`simantap:exam-outbox:cbt:<id>` atau `simantap:exam-outbox:tryout:<id>`).
- Jawaban yang belum tersimpan dikirim ulang otomatis:
  - setiap 8 detik,
  - saat browser kembali online,
  - saat siswa mengubah jawaban berikutnya.
- Setelah reload, jawaban dari antrean dipulihkan ke kontrol radio/teks di
  halaman sebelum siswa melanjutkan.
- Timeout pengiriman 10 detik; kegagalan jaringan tidak menghapus antrean.
- Respons `409` (waktu habis/sesi selesai) menghentikan antrean dan membuka
  halaman hasil.
- Tryout mencegah tombol **Selesaikan Tryout** jika masih ada jawaban yang belum
  tersimpan.

Pengiriman ulang aman karena endpoint jawaban melakukan update-or-create per
`attempt + soal`; jawaban terakhir yang menang (last write wins).

## Yang belum dijamin

- Halaman ujian dan soal **tidak** tersedia tanpa jaringan. Belum ada Service
  Worker, cache HTML, IndexedDB, token HMAC per soal, atau enkripsi lokal.
- Siswa tidak dapat memulai ujian baru sepenuhnya offline.
- Antrean terikat pada browser/perangkat. Membuka akun yang sama di perangkat
  lain tidak memindahkan antrean.
- Sesi login yang kedaluwarsa tetap harus login ulang; jawaban di antrean tidak
  dikirim tanpa sesi yang sah.
- Timer berjalan di klien untuk tampilan, tetapi server tetap penentu batas
  waktu. Jangan mengandalkan jam perangkat.
- `localStorage` dapat dibersihkan pengguna, mode privat, atau kebijakan browser.
  Ini bukan pengganti pengawasan ujian.

## Uji manual

1. Buka sesi CBT/Tryout sebagai siswa.
2. Buka DevTools → Network → **Offline**.
3. Pilih beberapa jawaban. Status harus menunjukkan jawaban belum tersimpan.
4. Muat ulang halaman **saat masih online** setelah antrean terbentuk, lalu
   pastikan pilihan pulih dari antrean.
5. Aktifkan kembali jaringan. Tunggu maksimal 8 detik; status berubah menjadi
   tersimpan dan antrean kosong.
6. Untuk Tryout, coba klik **Selesaikan Tryout** saat antrean belum kosong:
   tombol harus menolak dan meminta menunggu.

## Operasional

- Tidak ada perubahan konfigurasi server.
- Pastikan `APP_URL`, HTTPS, dan sesi/cookie stabil di staging. Antrean tidak
  menyelesaikan masalah login yang terputus.
- Jika siswa melaporkan jawaban hilang, periksa `exam_answers`/`student_answers`
  di server dan minta siswa tidak menghapus data situs sebelum antrean terkirim.
- Uji beban tidak boleh menjalankan skenario offline ini terhadap produksi.
