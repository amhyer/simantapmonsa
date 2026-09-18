@extends('layouts.app')

@section('title', 'Edit Profil - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Edit Profil')
@section('page_subtitle', 'Perbarui data profil Anda')

@section('content')
    <div class="card" style="max-width:700px">
        <div class="card-header">
            <h3><i class="fas fa-user-edit" style="margin-right:8px;color:var(--navy)"></i>Edit Profil</h3>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="note note-ok" style="margin-bottom:16px">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="note note-warn" style="margin-bottom:16px">
                    <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
                </div>
            @endif

            <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">
                <div style="text-align:center;flex:0 0 140px">
                    @if($siswa->foto)
                        <img id="fotoPreview" src="{{ asset('storage/' . $siswa->foto) }}" alt="Foto" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid var(--navy)">
                    @else
                        <div id="fotoPreview" style="width:120px;height:120px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:42px;margin:0 auto;font-weight:700">
                            {{ substr($siswa->nama_peserta_didik, 0, 1) }}
                        </div>
                    @endif
                    <div style="font-size:11px;color:#888;margin-top:8px">Foto Profil</div>
                </div>

                <div style="flex:1;min-width:0">
                    <form action="{{ route('siswa.profil.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" value="{{ $siswa->nama_peserta_didik }}" disabled style="background:#F2F4F7">
                            <small style="color:#888">Data dari Dapodik, tidak dapat diubah</small>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                            <div class="form-group">
                                <label class="form-label">NIS</label>
                                <input type="text" class="form-control" value="{{ $siswa->nis }}" disabled style="background:#F2F4F7">
                            </div>
                            <div class="form-group">
                                <label class="form-label">NISN</label>
                                <input type="text" class="form-control" value="{{ $siswa->nisn }}" disabled style="background:#F2F4F7">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                            <div class="form-group">
                                <label class="form-label">Kelas</label>
                                <input type="text" class="form-control" value="{{ $siswa->kelas }}" disabled style="background:#F2F4F7">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Jenis Kelamin</label>
                                <input type="text" class="form-control" value="{{ $siswa->jenis_kelamin }}" disabled style="background:#F2F4F7">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">No. HP <span style="color:#888;font-weight:400;font-size:12px">(bisa diubah)</span></label>
                            <input type="text" name="telepon" class="form-control @error('telepon') is-invalid @enderror" value="{{ old('telepon', $siswa->telepon) }}" placeholder="08xxxxxxxxxx">
                            @error('telepon')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Alamat <span style="color:#888;font-weight:400;font-size:12px">(bisa diubah)</span></label>
                            <textarea name="alamat" class="form-control @error('alamat') is-invalid @enderror" rows="3" placeholder="Alamat lengkap Anda">{{ old('alamat', $siswa->alamat) }}</textarea>
                            @error('alamat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Foto Profil <span style="color:#888;font-weight:400;font-size:12px">(opsional, max 2MB)</span></label>
                            <input type="file" name="foto" id="fotoInput" class="form-control @error('foto') is-invalid @enderror" accept="image/jpeg,image/jpg,image/png">
                            @error('foto')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small style="color:#888">Format: JPG, JPEG, PNG. Maksimal 2MB.</small>
                        </div>

                        <div style="display:flex;gap:12px;margin-top:16px">
                            <button type="submit" class="btn btn-sm" style="background:var(--navy);color:#fff">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                            <a href="{{ route('siswa.profil.show') }}" class="btn btn-sm btn-ghost">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('fotoInput')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            const el = document.getElementById('fotoPreview');
            if (el.tagName === 'IMG') {
                el.src = ev.target.result;
            } else {
                const img = document.createElement('img');
                img.id = 'fotoPreview';
                img.src = ev.target.result;
                img.style.cssText = 'width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid var(--navy)';
                el.parentNode.replaceChild(img, el);
            }
        };
        reader.readAsDataURL(file);
    });
    </script>
@endsection
