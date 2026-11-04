@extends('layouts.app')

@section('title', 'Penilaian TKA Siswa - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Input Penilaian Kesiapan (TKA)')
@section('page_subtitle', 'Observasi dan asesmen capaian Profil Pelajar Pancasila siswa')

@section('header_actions')
    <a href="{{ route('tka.assessments.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
    </a>
@endsection

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('content')
<form action="{{ route('tka.assessments.store') }}" method="POST" id="assessmentForm">
    @csrf

    <div class="grid grid-3" style="align-items:start;margin-bottom:24px">
        {{-- Left: Student Selection & Info --}}
        <div style="display:flex;flex-direction:column;gap:20px">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-graduate" style="color:var(--navy);margin-right:8px"></i>Identitas Siswa</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom:16px">
                        <label class="form-label">Pilih Siswa <span style="color:var(--bad)">*</span></label>
                        <select name="siswa_id" class="form-control" required style="width:100%">
                            <option value="">— Pilih Siswa —</option>
                            @foreach($siswas as $siswa)
                                <option value="{{ $siswa->id }}" @selected(old('siswa_id') == $siswa->id)>
                                    {{ $siswa->nama_peserta_didik }} (Kelas {{ $siswa->kelas }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div style="margin-bottom:16px">
                        <label class="form-label">Semester Penilaian <span style="color:var(--bad)">*</span></label>
                        <select name="semester_id" class="form-control" required style="width:100%">
                            <option value="">— Pilih Semester —</option>
                            @foreach($semesters as $semester)
                                <option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>
                                    {{ $semester->tahun_ajaran }} — {{ $semester->nama_semester ?? $semester->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Tanggal Pengamatan <span style="color:var(--bad)">*</span></label>
                        <input type="date" name="tanggal_penilaian" class="form-control" value="{{ old('tanggal_penilaian', date('Y-m-d')) }}" required>
                    </div>
                </div>
            </div>

            {{-- Rubrik Panduan --}}
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-book-open" style="color:var(--gold);margin-right:8px"></i>Rubrik Penilaian 1–4</h3>
                </div>
                <div class="card-body tight">
                    <div class="table-wrapper">
                        <table>
                            <tbody>
                                <tr>
                                    <td style="width:25%"><span class="tag tag-ok">Skor 4</span></td>
                                    <td style="font-size:12.5px;font-weight:600">Sangat Berkembang (SB)</td>
                                </tr>
                                <tr>
                                    <td><span class="tag tag-primary">Skor 3</span></td>
                                    <td style="font-size:12.5px;font-weight:600">Berkembang Sesuai Harapan (BSH)</td>
                                </tr>
                                <tr>
                                    <td><span class="tag tag-gold">Skor 2</span></td>
                                    <td style="font-size:12.5px;font-weight:600">Mulai Berkembang (MB)</td>
                                </tr>
                                <tr>
                                    <td><span class="tag tag-bad">Skor 1</span></td>
                                    <td style="font-size:12.5px;font-weight:600">Belum Berkembang (BB / Bimbingan)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Dimension Rubric Assessment (Takes 2 Columns) --}}
        <div class="card" style="grid-column: span 2">
            <div class="card-header">
                <h3><i class="fas fa-list-check" style="color:var(--navy);margin-right:8px"></i>Butir Indikator Dimensi Profil Pelajar Pancasila</h3>
                <span class="tag tag-secondary">{{ $dimensions->count() }} Dimensi</span>
            </div>

            <div class="card-body" style="padding:20px 24px">
                @foreach($dimensions as $dimension)
                    <div style="margin-bottom:28px;padding-bottom:20px;border-bottom:1px solid var(--line)">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
                            <div style="display:flex;align-items:center;gap:8px">
                                <span class="tag tag-primary" style="font-weight:700">
                                    {{ $dimension->kategori ? ucfirst(str_replace('_', ' ', $dimension->kategori)) : 'Dimensi' }}
                                </span>
                                <h4 style="font-size:15px;font-weight:800;margin:0;color:var(--text)">
                                    {{ $dimension->nama_dimensi }}
                                </h4>
                            </div>
                            <span style="font-size:12px;color:var(--muted)">
                                Standar Siap: <strong>{{ $dimension->threshold_siap ?? '3.0' }}</strong>
                            </span>
                        </div>

                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width:5%">No</th>
                                        <th style="width:45%">Indikator Perilaku / Sikap</th>
                                        <th style="width:25%">Skor Nilai</th>
                                        <th style="width:25%">Catatan Pengamatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dimension->assessmentItems as $index => $item)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <div style="font-size:13px;font-weight:600;color:var(--text)">
                                                    {{ $item->indikator }}
                                                </div>
                                                @if($item->pertanyaan_penilaian)
                                                    <div style="font-size:11.5px;color:var(--muted);margin-top:2px">
                                                        {{ $item->pertanyaan_penilaian }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="hidden" name="responses[{{ $item->id }}][assessment_item_id]" value="{{ $item->id }}">
                                                
                                                @if($item->tipe_jawaban === 'ya_tidak')
                                                    <select name="responses[{{ $item->id }}][skor]" class="form-control" required style="width:100%">
                                                        <option value="">— Pilih —</option>
                                                        <option value="4">Ya (Terpenuhi)</option>
                                                        <option value="1">Tidak (Belum)</option>
                                                    </select>
                                                @else
                                                    <select name="responses[{{ $item->id }}][skor]" class="form-control" required style="width:100%">
                                                        <option value="">— Pilih Skor —</option>
                                                        <option value="4">4 - Sangat Berkembang</option>
                                                        <option value="3">3 - Berkembang Harapan</option>
                                                        <option value="2">2 - Mulai Berkembang</option>
                                                        <option value="1">1 - Perlu Bimbingan</option>
                                                    </select>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       name="responses[{{ $item->id }}][catatan_guru]" 
                                                       class="form-control" 
                                                       placeholder="Catatan guru (opsional)">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach

                {{-- Action Bar --}}
                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:16px;gap:12px">
                    <a href="{{ route('tka.assessments.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary" style="padding:10px 28px">
                        <i class="fas fa-save"></i> Simpan Penilaian Siswa
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Auto-save draft in localStorage
setInterval(() => {
    const form = document.getElementById('assessmentForm');
    if (!form) return;
    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => { data[key] = value; });
    try { localStorage.setItem('tka_assessment_draft', JSON.stringify(data)); } catch (e) {}
}, 30000);

// Load draft on page load
window.addEventListener('load', () => {
    try {
        const draft = localStorage.getItem('tka_assessment_draft');
        if (draft && confirm('Terdapat draf penilaian yang belum tersimpan sebelumnya. Ingin memulihkan data tersebut?')) {
            const data = JSON.parse(draft);
            Object.keys(data).forEach(key => {
                const input = document.querySelector(`[name="${key}"]`);
                if (input) input.value = data[key];
            });
        }
    } catch (e) {}
});

// Clear draft on submit
document.getElementById('assessmentForm')?.addEventListener('submit', () => {
    try { localStorage.removeItem('tka_assessment_draft'); } catch (e) {}
});
</script>
@endsection
