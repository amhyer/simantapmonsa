@include('errors.layout', [
    'kode' => 503,
    'judul' => $judul ?? 'Sedang pemeliharaan',
    'pesan' => $pesan ?? 'SIMANTAP sedang diperbarui dan akan segera kembali. Data Anda aman.',
    'tombol' => $tombol ?? ['url' => url('/'), 'label' => 'Coba lagi'],
])
