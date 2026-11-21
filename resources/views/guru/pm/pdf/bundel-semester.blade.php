<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bundel Administrasi Pembelajaran Mendalam</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111827; }
        .cover { text-align: center; padding: 60px 24px 30px; border: 2px solid #1E3A8A; border-radius: 6px; page-break-after: always; }
        .cover h1 { font-size: 18px; margin: 12px 0 6px; color: #1E3A8A; }
        .cover h2 { font-size: 13px; margin: 0 0 24px; color: #374151; font-weight: normal; }
        .cover-meta { width: 80%; margin: 24px auto; border-collapse: collapse; text-align: left; }
        .cover-meta td { padding: 6px 8px; border-bottom: 1px solid #E5E7EB; font-size: 10.5px; }
        h3.sec { font-size: 12px; color: #1E3A8A; border-bottom: 1.5px solid #1E3A8A; padding-bottom: 3px; margin: 16px 0 8px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.data th, table.data td { border: 1px solid #374151; padding: 4px 5px; vertical-align: top; }
        table.data th { background: #F3F4F6; text-align: center; }
        .center { text-align: center; }
        .pb { page-break-before: always; }
    </style>
</head>
<body>
    {{-- HALAMAN COVER --}}
    <div class="cover">
        <div style="font-size:11px;font-weight:bold;letter-spacing:1px;color:#4B5563">PERANGKAT ADMINISTRASI GURU — KURIKULUM MERDEKA</div>
        <h1>BUNDEL PEMBELAJARAN MENDALAM</h1>
        <h2>{{ $sekolah?->nama_sekolah ?? 'Sekolah Dasar' }}</h2>

        <table class="cover-meta">
            <tr><td style="width:38%"><strong>Tahun Ajaran</strong></td><td>: {{ $tahunAjaran }}</td></tr>
            <tr><td><strong>Semester</strong></td><td>: Semester {{ $semester }} ({{ $semester === 1 ? 'Ganjil' : 'Genap' }})</td></tr>
            <tr><td><strong>Kelas / Fase</strong></td><td>: Kelas {{ $tingkat }} (Fase {{ $fase }})</td></tr>
            <tr><td><strong>Mata Pelajaran</strong></td><td>: {{ $mapel }}</td></tr>
            <tr><td><strong>Nama Guru</strong></td><td>: {{ $guru->nama_lengkap }}</td></tr>
            <tr><td><strong>NPSN Sekolah</strong></td><td>: {{ $sekolah?->npsn ?: '-' }}</td></tr>
        </table>

        <div style="margin-top:28px;font-size:10px;color:#4B5563">
            <strong>Isi Bundel Dokumen:</strong><br>
            1. Alokasi Waktu · 2. Capaian Pembelajaran (CP) · 3. Tujuan &amp; Alur Tujuan Pembelajaran (TP/ATP) · 4. KKTP · 5. Program Tahunan (Prota) · 6. Program Semester (Prosem) · 7. Rekap Modul Ajar (RPM) &amp; Jurnal Mengajar
        </div>
    </div>

    {{-- 1. ALOKASI WAKTU --}}
    <h3 class="sec">1. Alokasi Waktu (Kelas {{ $tingkat }})</h3>
    <table class="data">
        <thead><tr><th style="width:28px">No</th><th>Mata Pelajaran</th><th style="width:65px">Intra/Mgg</th><th style="width:65px">Ko/Mgg</th></tr></thead>
        <tbody>
            @forelse($alokasi as $i => $a)
                <tr><td class="center">{{ $i + 1 }}</td><td>{{ $a->nama_mapel }}</td><td class="center">{{ $a->jp_intra }} JP</td><td class="center">{{ $a->jp_kokurikuler }} JP</td></tr>
            @empty
                <tr><td colspan="4" class="center">Belum ada data alokasi waktu.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- 2. CP --}}
    <h3 class="sec">2. Capaian Pembelajaran (CP — Fase {{ $fase }})</h3>
    <table class="data">
        <thead><tr><th style="width:28px">No</th><th style="width:110px">Mapel</th><th style="width:95px">Elemen</th><th>Capaian Pembelajaran</th></tr></thead>
        <tbody>
            @forelse($cpList as $i => $cp)
                <tr><td class="center">{{ $i + 1 }}</td><td>{{ $cp->nama_mapel }}</td><td>{{ $cp->elemen }}</td><td>{{ $cp->deskripsi }}</td></tr>
            @empty
                <tr><td colspan="4" class="center">Belum ada data CP.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- 3. TP & ATP --}}
    <h3 class="sec">3. Tujuan &amp; Alur Tujuan Pembelajaran (TP / ATP)</h3>
    <table class="data">
        <thead><tr><th style="width:28px">No</th><th style="width:100px">Mapel</th><th style="width:55px">Kode</th><th>Tujuan Pembelajaran</th><th style="width:110px">Materi Pokok</th><th style="width:45px">JP</th></tr></thead>
        <tbody>
            @forelse($tpList as $i => $tp)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $tp->nama_mapel }}</td>
                    <td class="center">{{ $tp->kode_tp ?: '-' }}</td>
                    <td>{{ $tp->deskripsi }}</td>
                    <td>{{ $tp->materi_pokok ?: '-' }}</td>
                    <td class="center">{{ $tp->alokasi_jp }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="center">Belum ada data TP/ATP.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- 4. KKTP --}}
    <h3 class="sec">4. Kriteria Ketercapaian Tujuan Pembelajaran (KKTP)</h3>
    <table class="data">
        <thead><tr><th style="width:85px">Interval</th><th style="width:120px">Predikat</th><th>Keterangan / Tindak Lanjut</th></tr></thead>
        <tbody>
            @foreach($rentangPredikat as $r)
                <tr><td class="center"><strong>{{ $r['interval'] }}</strong></td><td>{{ $r['predikat'] }}</td><td>{{ $r['keterangan'] }}</td></tr>
            @endforeach
        </tbody>
    </table>

    {{-- 5. PROTA --}}
    <h3 class="sec pb">5. Program Tahunan (Prota)</h3>
    <table class="data">
        <thead><tr><th style="width:28px">No</th><th style="width:110px">Mapel</th><th style="width:42px">Sem</th><th>Materi / Bab</th><th style="width:45px">JP</th></tr></thead>
        <tbody>
            @forelse($protaList as $i => $pr)
                <tr><td class="center">{{ $i + 1 }}</td><td>{{ $pr->nama_mapel }}</td><td class="center">{{ $pr->semester }}</td><td>{{ $pr->materi }}</td><td class="center">{{ $pr->alokasi_jp }}</td></tr>
            @empty
                <tr><td colspan="5" class="center">Belum ada data Program Tahunan.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- 6. PROSEM --}}
    <h3 class="sec">6. Program Semester {{ $semester }} (Prosem)</h3>
    <table class="data">
        <thead><tr><th style="width:28px">No</th><th style="width:110px">Mapel</th><th style="width:65px">Bulan/Mgg</th><th>Materi / Topik</th><th style="width:45px">JP</th></tr></thead>
        <tbody>
            @forelse($prosemList as $i => $ps)
                <tr><td class="center">{{ $i + 1 }}</td><td>{{ $ps->nama_mapel }}</td><td class="center">Bln {{ $ps->bulan }} · M{{ $ps->minggu_ke }}</td><td>{{ $ps->materi }}</td><td class="center">{{ $ps->alokasi_jp }}</td></tr>
            @empty
                <tr><td colspan="5" class="center">Belum ada data Program Semester.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- 7. REKAP RPM & JURNAL --}}
    <h3 class="sec">7. Daftar Modul Ajar / RPM ({{ $rpmList->count() }}) &amp; Jurnal Mengajar ({{ $jurnalList->count() }})</h3>
    <table class="data">
        <thead><tr><th style="width:28px">No</th><th style="width:80px">Jenis</th><th style="width:110px">Mapel</th><th>Judul RPM / Topik Jurnal</th><th style="width:75px">Tanggal/JP</th></tr></thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($rpmList as $rpm)
                <tr><td class="center">{{ $no++ }}</td><td class="center">RPM</td><td>{{ $rpm->mata_pelajaran }}</td><td>{{ $rpm->judul }}</td><td class="center">{{ $rpm->alokasi_waktu ?: '-' }}</td></tr>
            @endforeach
            @foreach($jurnalList as $j)
                <tr><td class="center">{{ $no++ }}</td><td class="center">Jurnal</td><td>{{ $j->mata_pelajaran }}</td><td>{{ $j->topik }}</td><td class="center">{{ $j->tanggal->format('d/m/Y') }}</td></tr>
            @endforeach
            @if($rpmList->isEmpty() && $jurnalList->isEmpty())
                <tr><td colspan="5" class="center">Belum ada RPM atau Jurnal Mengajar.</td></tr>
            @endif
        </tbody>
    </table>

    @include('guru.pm.pdf._ttd', ['sekolah' => $sekolah, 'guru' => $guru])
</body>
</html>
