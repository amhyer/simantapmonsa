# Analisis Butir Soal

Fitur ini menghitung kualitas butir untuk sesi CBT. Analisis bersifat read-only
dan tidak mengubah nilai, kunci, atau jawaban siswa.

## Sumber data

1. **Engine `exam_*`** (utama): sesi dipetakan lewat `LegacyExamMapper`
   (`sumber_tabel = cbt_test_sessions`). Soal, opsi, attempt, dan jawaban dibaca
   dari engine.
2. **CBT lama** (fallback): jika sesi belum punya padanan engine atau belum punya
   attempt engine, dipakai `cbt_test_sessions.question_snapshot` dan `cbt_results`.
   Halaman selalu menampilkan sumber yang dipakai.

Hanya attempt yang sudah selesai (`selesai_at` terisi atau status
`dikumpulkan`/`dinilai`) yang dipakai bila ada. Jika belum ada yang selesai,
data sementara tetap ditampilkan dengan penanda "sebagian / sesi masih berjalan".

## Rumus

| Ukuran | Rumus | Arti |
|---|---|---|
| `P` tingkat kesukaran | benar / jumlah peserta | 0 sulit, 1 mudah |
| `D` daya beda | P kelompok atas 27% - P kelompok bawah 27% | kemampuan membedakan peserta kuat/lemah |
| Distraktor | frekuensi tiap opsi selain kunci | opsi yang tidak pernah dipilih perlu diperiksa |
| Kosong | jawaban tidak ada | termasuk peserta yang tidak menjawab |

Kategori `P`:

- `< 0,30` sulit
- `0,30 - 0,80` sedang
- `> 0,80` mudah

Kategori `D`:

- `< 0,20` lemah
- `0,20 - <0,30` cukup
- `>= 0,30` baik
- `null` jika peserta < 4 (kelompok 27% tidak bermakna)

`D` bisa negatif jika kelompok bawah lebih banyak benar; itu tanda butir atau
kunci perlu ditinjau.

## Akses

- Admin: `GET /admin/cbt/sessions/{session}/analisis-butir`, tombol **Analisis
  Butir** muncul di halaman sesi jika sudah ada hasil.
- CLI:

```bash
php artisan exam:analisis-butir                 # daftar sesi CBT terakhir
php artisan exam:analisis-butir --cbt=12        # sesi CBT → engine/legacy otomatis
php artisan exam:analisis-butir --engine=7      # sesi engine langsung
php artisan exam:analisis-butir --cbt=12 --json
```

## Tafsir praktis

- Butir sulit dengan `D` baik: mungkin materi belum diajarkan atau soal terlalu
  kompleks; cek bukan karena kunci salah.
- Butir mudah dengan `D` rendah: semua peserta benar, kurang membedakan; tetap
  berguna sebagai soal pemantapan, bukan penentu.
- Distraktor kosong: periksa apakah opsi tidak masuk akal, terlalu jelas salah,
  atau tidak terbaca.
- Jangan memakai satu sesi kecil sebagai dasar menghapus soal. Gunakan beberapa
  sesi dan pertimbangkan revisi, bukan penghapusan langsung.

## Batas

- Ini bukan analisis psikometrik lengkap (IRT, reliabilitas, bias butir).
- Data laporan bergantung pada kelengkapan attempt dan kunci jawaban. Kunci yang
  tidak ditemukan ditandai eksplisit.
- Analisis engine memakai soal bank saat ini; jika soal diubah setelah ujian,
  hasil dapat berbeda dari snapshot saat peserta mengerjakan. Kebijakan yang
  disarankan: bekukan bank setelah publish dan gunakan snapshot untuk audit.
- Untuk keputusan e-Rapor/rapor, analisis butir adalah alat bantu review, bukan
  satu-satunya dasar penilaian.
