@extends('layouts.app')

@section('title', 'Modul & Tampilan - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Modul & Tampilan')
@section('page_subtitle', 'Aktifkan atau nonaktifkan modul sistem')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Pengaturan Modul</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.modul.update') }}" method="POST">
                @csrf
                <p style="color:var(--muted);font-size:13px;margin-bottom:20px">
                    Aktifkan atau nonaktifkan modul yang ditampilkan di seluruh sistem. Modul yang dinonaktifkan tidak akan terlihat oleh guru, siswa, dan orang tua.
                </p>

                <div class="grid grid-2" style="gap:14px">
                    @php
                        $modulList = [
                            'materi' => ['icon' => '📚', 'label' => 'Materi', 'desc' => 'Upload dan pengelolaan materi pembelajaran'],
                            'kuis' => ['icon' => '📝', 'label' => 'Kuis', 'desc' => 'Pembuatan dan pengelolaan kuis online'],
                            'nilai' => ['icon' => '📊', 'label' => 'Nilai', 'desc' => 'Pencatatan dan pengelolaan nilai siswa'],
                            'kehadiran' => ['icon' => '✅', 'label' => 'Kehadiran', 'desc' => 'Absensi dan pencatatan kehadiran siswa'],
                            'catatan' => ['icon' => '📋', 'label' => 'Catatan', 'desc' => 'Catatan perkembangan siswa'],
                            'dimensi' => ['icon' => '🌟', 'label' => 'Dimensi', 'desc' => 'Penilaian dimensi Profil Pelajar Pancasila'],
                            'kebiasaan' => ['icon' => '💪', 'label' => 'Kebiasaan', 'desc' => 'Pencatatan kebiasaan dan karakter siswa'],
                            'laporan' => ['icon' => '📄', 'label' => 'Laporan', 'desc' => 'Cetak laporan dan rekapitulasi'],
                        ];
                    @endphp

                    @foreach($modulList as $key => $info)
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:{{ ($modul[$key] ?? false) ? '#F8F9FB' : '#FAFBFD' }};border:1px solid #E4E7EC;border-radius:10px;transition:0.15s">
                            <div style="display:flex;align-items:center;gap:12px">
                                <span style="font-size:22px">{{ $info['icon'] }}</span>
                                <div>
                                    <div style="font-weight:600;font-size:14px">{{ $info['label'] }}</div>
                                    <div style="font-size:12px;color:var(--muted)">{{ $info['desc'] }}</div>
                                </div>
                            </div>
                            <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer">
                                <input type="checkbox" name="modul[{{ $key }}]" value="1" {{ ($modul[$key] ?? false) ? 'checked' : '' }} style="opacity:0;width:0;height:0">
                                <span style="position:absolute;inset:0;background:#D0D5DD;border-radius:99px;transition:0.2s"></span>
                                <span style="position:absolute;top:2px;left:2px;width:20px;height:20px;background:#fff;border-radius:50%;transition:0.2s;box-shadow:0 1px 3px rgba(0,0,0,0.15)"></span>
                            </label>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top:20px">
                    <button type="submit" class="btn">💾 Simpan Pengaturan</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        document.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var parent = this.closest('div[style]');
                var span = this.nextElementSibling;
                var dot = span.nextElementSibling;
                if (this.checked) {
                    span.style.background = 'var(--ok)';
                    dot.style.transform = 'translateX(20px)';
                } else {
                    span.style.background = '#D0D5DD';
                    dot.style.transform = 'translateX(0)';
                }
            });
            cb.dispatchEvent(new Event('change'));
        });
    </script>
    @endpush
@endsection
