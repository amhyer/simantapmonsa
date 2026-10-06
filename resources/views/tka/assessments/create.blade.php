@extends('layouts.app')

@section('title', 'Penilaian TKA - Tambah')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="mb-0">📝 Penilaian TKA Baru</h2>
        </div>
    </div>

    <form action="{{ route('tka.assessments.store') }}" method="POST" id="assessmentForm">
        @csrf
        
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">📋 Informasi Siswa</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Siswa <span class="text-danger">*</span></label>
                            <select name="siswa_id" class="form-select" required>
                                <option value="">Pilih Siswa</option>
                                @foreach($siswas as $siswa)
                                <option value="{{ $siswa->id }}">
                                    {{ $siswa->nama_peserta_didik }} - {{ $siswa->kelas }} 
                                    {{ $siswa->rombel->nama_rombel ?? '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Semester <span class="text-danger">*</span></label>
                            <select name="semester_id" class="form-select" required>
                                <option value="">Pilih Semester</option>
                                @foreach($semesters as $semester)
                                <option value="{{ $semester->id }}">
                                    {{ $semester->tahun_ajaran }} - {{ $semester->nama_semester }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Penilaian <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_penilaian" class="form-control" 
                                   value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📊 Rubrik Penilaian</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Skor</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>4</strong></td>
                                    <td>Sangat Baik</td>
                                </tr>
                                <tr>
                                    <td><strong>3</strong></td>
                                    <td>Baik</td>
                                </tr>
                                <tr>
                                    <td><strong>2</strong></td>
                                    <td>Cukup</td>
                                </tr>
                                <tr>
                                    <td><strong>1</strong></td>
                                    <td>Perlu Bimbingan</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">🎯 Penilaian per Dimensi</h5>
                    </div>
                    <div class="card-body">
                        @foreach($dimensions as $dimension)
                        <div class="dimension-section mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">
                                    <span class="badge bg-secondary">{{ $dimension->kategori }}</span>
                                    {{ $dimension->nama_dimensi }}
                                </h6>
                                <small class="text-muted">
                                    Threshold: {{ $dimension->threshold_siap }}
                                </small>
                            </div>
                            
                            <div class="table-wrapper">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th width="5%">No</th>
                                            <th width="45%">Indikator</th>
                                            <th width="30%">Penilaian</th>
                                            <th width="20%">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($dimension->assessmentItems as $index => $item)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <small>{{ $item->indikator }}</small>
                                            </td>
                                            <td>
                                                <input type="hidden" 
                                                       name="responses[{{ $item->id }}][assessment_item_id]" 
                                                       value="{{ $item->id }}">
                                                
                                                @if($item->tipe_jawaban === 'skala_1_4')
                                                <select name="responses[{{ $item->id }}][skor]" 
                                                        class="form-select form-select-sm" required>
                                                    <option value="">Pilih Skor</option>
                                                    <option value="4">4 - Sangat Baik</option>
                                                    <option value="3">3 - Baik</option>
                                                    <option value="2">2 - Cukup</option>
                                                    <option value="1">1 - Perlu Bimbingan</option>
                                                </select>
                                                @elseif($item->tipe_jawaban === 'ya_tidak')
                                                <select name="responses[{{ $item->id }}][skor]" 
                                                        class="form-select form-select-sm" required>
                                                    <option value="">Pilih</option>
                                                    <option value="4">Ya</option>
                                                    <option value="1">Tidak</option>
                                                </select>
                                                @else
                                                <input type="number" 
                                                       name="responses[{{ $item->id }}][skor]"
                                                       class="form-control form-control-sm"
                                                       min="1" max="4" required>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       name="responses[{{ $item->id }}][catatan_guru]"
                                                       class="form-control form-control-sm"
                                                       placeholder="Catatan">
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-12">
                <a href="{{ route('tka.assessments.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Penilaian
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// Auto-save draft
setInterval(() => {
    const formData = new FormData(document.getElementById('assessmentForm'));
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });
    localStorage.setItem('tka_assessment_draft', JSON.stringify(data));
}, 30000);

// Load draft on page load
window.addEventListener('load', () => {
    const draft = localStorage.getItem('tka_assessment_draft');
    if (draft && confirm('Ada draft penilaian yang tersimpan. Muat draft?')) {
        const data = JSON.parse(draft);
        Object.keys(data).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = data[key];
            }
        });
    }
});

// Clear draft on submit
document.getElementById('assessmentForm').addEventListener('submit', () => {
    localStorage.removeItem('tka_assessment_draft');
});
</script>
@endsection
