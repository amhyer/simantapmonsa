@php
    $ttd = app(\App\Services\PembelajaranMendalam\TandaTanganPdfService::class)->resolveForPdf($sekolah ?? null, $guru ?? null);
    $namaKepsek = $ttd['nama_kepsek'] ?: ($kop['kepala_sekolah'] ?? '........................................');
    $namaGuru = $ttd['nama_guru'] ?: '........................................';
@endphp

<table style="width:100%;border:none;margin-top:22px;page-break-inside:avoid">
    <tr>
        <td style="width:50%;border:none;text-align:center;vertical-align:top;padding:0">
            <div>Mengetahui,</div>
            <div>Kepala Sekolah</div>
            <div style="position:relative;height:66px;margin:4px auto;width:210px">
                @if($ttd['stempel_uri'])
                    <img src="{{ $ttd['stempel_uri'] }}" alt="Stempel Sekolah"
                         style="position:absolute;left:18px;top:0;height:62px;max-width:90px;opacity:0.85">
                @endif
                @if($ttd['ttd_kepsek_uri'])
                    <img src="{{ $ttd['ttd_kepsek_uri'] }}" alt="TTD Kepala Sekolah"
                         style="position:relative;height:56px;max-width:150px;margin-top:5px">
                @endif
            </div>
            <div style="font-weight:bold;text-decoration:underline">{{ $namaKepsek }}</div>
            @if($ttd['nip_kepsek'])
                <div style="font-size:9px;margin-top:2px">NIP. {{ $ttd['nip_kepsek'] }}</div>
            @endif
        </td>
        <td style="width:50%;border:none;text-align:center;vertical-align:top;padding:0">
            <div>{{ $ttd['kota'] ? $ttd['kota'].', ' : '' }}{{ now()->format('d/m/Y') }}</div>
            <div>{{ $ttd['jabatan_guru'] }}</div>
            <div style="position:relative;height:66px;margin:4px auto;width:210px">
                @if($ttd['ttd_guru_uri'])
                    <img src="{{ $ttd['ttd_guru_uri'] }}" alt="TTD Guru"
                         style="height:56px;max-width:160px;margin-top:5px">
                @endif
            </div>
            <div style="font-weight:bold;text-decoration:underline">{{ $namaGuru }}</div>
            @if($ttd['nip_guru'])
                <div style="font-size:9px;margin-top:2px">NIP. {{ $ttd['nip_guru'] }}</div>
            @endif
        </td>
    </tr>
</table>
