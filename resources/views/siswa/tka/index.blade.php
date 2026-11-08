@extends('layouts.app')
@section('title', 'Simulasi Tes Kemampuan Akademik (TKA) - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Simulasi TKA Mandiri')
@section('page_subtitle', 'Pusat Asesmen Pendidikan (Pusmendik) • Permendikdasmen No. 9 Tahun 2025')

@section('content')
@include('cbt.feedback')

{{-- Info Header Banner Pusmendik --}}
<div class="card" style="margin-bottom:24px;border-left:4px solid var(--violet);background:linear-gradient(to right, #FFFFFF, #FAF5FF)">
    <div class="card-body" style="padding:22px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:18px">
        <div style="display:flex;align-items:center;gap:16px">
            <div style="width:48px;height:48px;border-radius:12px;background:rgba(124,58,237,0.12);color:var(--violet);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div>
                <h3 style="font-size:16px;font-weight:800;color:var(--text);margin:0 0 4px">
                    Latihan Soal & Simulasi Mandiri TKA
                </h3>
                <p style="margin:0;font-size:13px;color:var(--muted);line-height:1.5">
                    Instrumen standar nasional untuk mengukur capaian akademik berbasis penalaran, wacana literasi, dan logika kuantitatif. Bersifat diagnostik dan mandiri.
                </p>
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span class="tag tag-violet"><i class="fas fa-check-circle"></i> Kisi-kisi Asesmenpedia</span>
            <span class="tag tag-ok"><i class="fas fa-bolt"></i> Evaluasi Instan</span>
            <a href="{{ route('siswa.tka-simulasi.kognitif') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-brain"></i> Profil Kognitif & Rekomendasiku
            </a>
        </div>
    </div>
</div>

{{-- Simulasi Adaptif TKA (CAT - Tingkat Kesulitan Menyesuaikan Siswa) --}}
@if(!empty($paketAdaptifList))
<div class="card" style="margin-bottom:24px;border:1.5px solid #DDD6FE">
    <div class="card-header" style="background:#FAF5FF">
        <h3><i class="fas fa-sliders-h" style="color:var(--violet);margin-right:8px"></i>Simulasi Adaptif TKA (Kesulitan Otomatis & Terlapor ke Orang Tua)</h3>
        <span class="tag tag-violet">4 Subtes CAT Adaptif</span>
    </div>
    <div class="card-body">
        <p style="font-size:13px;color:var(--muted);margin:0 0 16px;line-height:1.5">
            Tingkat kesulitan butir soal otomatis menyesuaikan ketepatan jawabanmu (<strong>Mudah ↔ Sedang ↔ Sulit</strong>), mencatat durasi pengerjaan aktual, menyusun paket latihan personalisasi, dan melaporkan hasilnya ke akun Orang Tua/Wali.
        </p>
        <div class="grid grid-2" style="gap:16px">
            @foreach($paketAdaptifList as $kodeAdp => $adp)
                <div style="background:#fff;border:1.5px solid var(--line);border-radius:12px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;gap:12px">
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                            <span class="tag tag-violet" style="font-weight:700">{{ $adp['label_kode'] }}</span>
                            <span class="tag tag-ok">{{ $adp['target_soal'] }} Soal Bertingkat</span>
                        </div>
                        <h4 style="font-size:14.5px;font-weight:800;color:var(--navy);margin:0 0 6px">{{ $adp['judul'] }}</h4>
                        <p style="font-size:12.5px;color:var(--muted);margin:0;line-height:1.5">{{ $adp['deskripsi'] }}</p>
                    </div>
                    <form method="POST" action="{{ route('siswa.tka-simulasi.adaptif.start', $kodeAdp) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-sm" style="width:100%;font-weight:700;color:var(--violet);border-color:var(--violet)">
                            <i class="fas fa-play"></i> Mulai Simulasi Adaptif
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Subtest Packages Grid --}}
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h3><i class="fas fa-layer-group" style="color:var(--violet);margin-right:8px"></i>Pilih Subtes Simulasi TKA</h3>
        <span class="tag tag-secondary">3 Subtes Asesmen Resmi</span>
    </div>

    <div class="card-body">
        <div class="grid grid-3">
            @foreach($paketList as $key => $paket)
                @php
                    $last = $riwayat[$key] ?? null;
                @endphp
                <div style="background:#FAFBFD;border:1.5px solid var(--line);border-radius:12px;padding:22px;display:flex;flex-direction:column;justify-content:space-between;transition:all .15s ease">
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                            <span class="tag tag-violet" style="font-weight:700">{{ $paket['kode'] }}</span>
                            <span class="tag tag-primary"><i class="fas fa-clock"></i> {{ $paket['durasi'] }} Menit</span>
                        </div>

                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                            <i class="fas {{ $paket['icon'] }}" style="color:var(--violet);font-size:18px"></i>
                            <h4 style="font-size:15px;font-weight:800;color:var(--text);margin:0;line-height:1.4">
                                {{ $paket['kategori'] }}
                            </h4>
                        </div>

                        <p style="font-size:12.5px;color:var(--muted);line-height:1.5;margin:0 0 16px">
                            {{ $paket['deskripsi'] }}
                        </p>

                        {{-- Parameter Ringkas --}}
                        <div style="background:#fff;border:1px solid var(--line);border-radius:8px;padding:12px;margin-bottom:18px;display:grid;grid-template-columns:1fr 1fr;gap:8px;text-align:center">
                            <div>
                                <div style="font-size:11px;color:var(--muted)">Jumlah Soal</div>
                                <div style="font-size:15px;font-weight:800;color:var(--navy);margin-top:2px">{{ count($paket['soal']) }} Butir</div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:var(--muted)">Model Soal</div>
                                <div style="font-size:13px;font-weight:700;color:var(--text);margin-top:2px">Dual-Pane Wacana</div>
                            </div>
                        </div>

                        @if($last)
                            <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:10px 12px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
                                <div>
                                    <div style="font-size:11px;color:#166534;font-weight:700">Skor Terakhir: {{ $last['skor'] }}</div>
                                    <div style="font-size:10.5px;color:#15803D">{{ $last['level'] }} • {{ $last['submitted_at'] }}</div>
                                </div>
                                <a href="{{ route('siswa.tka-simulasi.result', $key) }}" style="font-size:11.5px;font-weight:700;color:#166534;text-decoration:none">
                                    Hasil →
                                </a>
                            </div>
                        @endif
                    </div>

                    <div>
                        <a href="{{ route('siswa.tka-simulasi.confirm', $key) }}" class="btn btn-primary btn-block" style="display:flex;align-items:center;justify-content:center;gap:8px">
                            <i class="fas fa-arrow-right"></i> Masuk Simulasi
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Official Kemendikdasmen References & Direct Links --}}
<div class="card" style="margin-bottom:24px;border:1.5px solid #E2E8F0;background:#FAFBFD">
    <div class="card-header" style="background:#fff">
        <h3 style="font-size:14.5px;color:var(--text);display:flex;align-items:center;gap:8px">
            <i class="fas fa-external-link-alt" style="color:var(--navy)"></i> Portal Resmi Ujian TKA Nasional (Kemendikdasmen)
        </h3>
        <span class="tag tag-ok">Referensi Standar Nasional</span>
    </div>
    <div class="card-body" style="padding:18px 20px">
        <p style="font-size:13px;color:var(--muted);margin:0 0 16px;line-height:1.5">
            Sebelum mengikuti ujian TKA serentak nasional, peserta didik dapat mengakses portal rujukan resmi pemerintah berikut untuk familiarisasi sistem dan regulasi:
        </p>
        <div class="grid grid-3" style="gap:14px">
            <a href="https://pusmendik.kemendikdasmen.go.id/tka/simulasi_tka/?ses_id=3cb5320944a943d63b4835d4c727ffc1744dd4f6" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="btn btn-secondary" 
               style="display:flex;flex-direction:column;align-items:flex-start;padding:14px 16px;height:auto;text-align:left;border-radius:10px;text-decoration:none;background:#fff">
                <div style="display:flex;align-items:center;justify-content:space-between;width:100%;margin-bottom:6px">
                    <span style="font-weight:700;font-size:13.5px;color:var(--navy)">Simulasi TKA Pusmendik</span>
                    <i class="fas fa-arrow-up-right-from-square" style="font-size:12px;color:var(--muted)"></i>
                </div>
                <div style="font-size:11.5px;color:var(--muted);line-height:1.4">
                    Uji coba login & mesin CBT resmi Pusmendik Kemendikdasmen.
                </div>
            </a>

            <a href="https://asesmenpedia.pusmendik.kemendikdasmen.go.id/tka" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="btn btn-secondary" 
               style="display:flex;flex-direction:column;align-items:flex-start;padding:14px 16px;height:auto;text-align:left;border-radius:10px;text-decoration:none;background:#fff">
                <div style="display:flex;align-items:center;justify-content:space-between;width:100%;margin-bottom:6px">
                    <span style="font-weight:700;font-size:13.5px;color:var(--navy)">Asesmenpedia TKA</span>
                    <i class="fas fa-arrow-up-right-from-square" style="font-size:12px;color:var(--muted)"></i>
                </div>
                <div style="font-size:11.5px;color:var(--muted);line-height:1.4">
                    Kisi-kisi soal, kerangka asesmen, dan level kognitif resmi.
                </div>
            </a>

            <a href="https://pusatinformasi.ult.kemendikdasmen.go.id/hc/id/categories/51884694242585-Tes-Kemampuan-Akademik-TKA" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="btn btn-secondary" 
               style="display:flex;flex-direction:column;align-items:flex-start;padding:14px 16px;height:auto;text-align:left;border-radius:10px;text-decoration:none;background:#fff">
                <div style="display:flex;align-items:center;justify-content:space-between;width:100%;margin-bottom:6px">
                    <span style="font-weight:700;font-size:13.5px;color:var(--navy)">Pusat Informasi ULT</span>
                    <i class="fas fa-arrow-up-right-from-square" style="font-size:12px;color:var(--muted)"></i>
                </div>
                <div style="font-size:11.5px;color:var(--muted);line-height:1.4">
                    Regulasi Permendikdasmen No. 9 Tahun 2025, SOP & FAQ resmi.
                </div>
            </a>
        </div>
    </div>
</div>

{{-- Panduan Peserta TKA --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-info-circle" style="color:var(--navy);margin-right:8px"></i>Petunjuk Asesmen Mandiri</h3>
    </div>
    <div class="card-body" style="font-size:13.5px;line-height:1.6;color:var(--text)">
        <ol style="margin:0;padding-left:20px;display:flex;flex-direction:column;gap:8px">
            <li>Simulasi mandiri ini dirancang sesuai format resmi <strong>Pusat Asesmen Pendidikan (Pusmendik) Kemendikdasmen</strong>.</li>
            <li>Soal disajikan dengan tata letak <strong>Split-Pane</strong>: Stimulus wacana informasi di sebelah kiri dan butir pertanyaan di sebelah kanan.</li>
            <li>Jawaban otomatis tersimpan setiap kali Anda memilih opsi. Anda dapat menandai soal <em>Ragu-ragu</em> atau berpindah butir melalui <em>Daftar Soal</em>.</li>
            <li>Hasil simulasi memberikan pemetaan level kompetensi akademik (<strong>Perlu Intervensi Khusus</strong>, <strong>Dasar</strong>, <strong>Cakap</strong>, <strong>Mahir</strong>) lengkap dengan pembahasan kunci jawaban.</li>
        </ol>
    </div>
</div>
@endsection
