@include('errors.layout', [
    'kode' => 503,
    'judul' => 'Sedang pemeliharaan',
    'pesan' => 'SIMANTAP sedang diperbarui dan akan segera kembali. Data Anda aman.',
    'tombol' => ['url' => url('/'), 'label' => 'Coba lagi'],
])
