{{-- Disclaimer & acuan regulasi wajib tampil di semua halaman kesiapan TKA --}}
<div class="note note-warn" style="margin-bottom:16px">
    <strong>Pemantauan internal sekolah — bukan nilai atau hasil TKA resmi.</strong> {{ $disclaimer }}
    <details style="margin-top:6px">
        <summary>Sumber acuan &amp; status verifikasi dokumen primer</summary>
        <div style="font-size:.85rem;margin-top:6px;line-height:1.5">
            <strong>Dokumen utama:</strong> {{ $acuan['dokumen'] }} (ditetapkan {{ $acuan['tanggal_dokumen'] ?? '—' }})<br>
            <strong>Regulasi pendukung:</strong> {{ $acuan['regulasi_pendukung'] ?? '—' }}<br>
            <strong>Penerbit:</strong> {{ $acuan['penyelenggara'] }}<br>
            <strong>Portal resmi:</strong> {{ $acuan['url_portal'] ?? '—' }}<br>
            <strong>Lokasi &amp; status salinan:</strong> {{ $acuan['lokasi_dokumen'] ?? '—' }} (tanggal verifikasi: {{ $acuan['tanggal_verifikasi'] ?? '—' }})<br>
            {{ $acuan['verifikasi'] }}
        </div>
    </details>
</div>
