@extends('layouts.app')
@section('title', 'Petunjuk Ujian CBT - ' . $session->title)

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Petunjuk Ujian CBT')
@section('page_subtitle', $session->title)

@section('content')
@include('cbt.feedback')

<div style="max-width:850px;margin:0 auto">
    {{-- Card Petunjuk --}}
    <div class="card" style="box-shadow:0 4px 20px rgba(0,0,0,0.06);border-radius:16px;overflow:hidden">
        {{-- Header Card --}}
        <div style="background:linear-gradient(135deg, var(--navy-dark) 0%, var(--navy) 100%);color:#fff;padding:28px 32px">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <span class="tag tag-gold" style="font-weight:700">SESI CBT</span>
                <span style="font-size:13px;opacity:0.85">Kelas {{ $session->kelas }}</span>
            </div>
            <h2 style="font-size:22px;font-weight:800;letter-spacing:-0.4px;margin:0 0 8px">{{ $session->title }}</h2>
            <p style="font-size:13.5px;opacity:0.85;margin:0;line-height:1.5">
                Bacalah seluruh tata tertib dan petunjuk pelaksanaan ujian di bawah ini sebelum Anda memulai pengerjaan.
            </p>
        </div>

        {{-- Parameter Ujian --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:1px;background:var(--line);border-bottom:1px solid var(--line)">
            <div style="background:#fff;padding:18px 24px;text-align:center">
                <div style="font-size:11px;text-transform:uppercase;color:var(--muted);font-weight:700;letter-spacing:0.5px">Total Soal</div>
                <div style="font-size:22px;font-weight:800;color:var(--navy);margin-top:4px">{{ count($session->question_snapshot ?? []) }} Butir</div>
                <div style="font-size:11.5px;color:var(--muted);margin-top:2px">{{ count($session->question_snapshot ?? []) }} soal pilihan ganda, satu percobaan.</div>
            </div>
            <div style="background:#fff;padding:18px 24px;text-align:center">
                <div style="font-size:11px;text-transform:uppercase;color:var(--muted);font-weight:700;letter-spacing:0.5px">Durasi Pengerjaan</div>
                <div style="font-size:22px;font-weight:800;color:var(--gold);margin-top:4px">{{ $session->duration_minutes }} Menit</div>
                <div style="font-size:11.5px;color:var(--muted);margin-top:2px">Hitung mundur otomatis</div>
            </div>
            <div style="background:#fff;padding:18px 24px;text-align:center">
                <div style="font-size:11px;text-transform:uppercase;color:var(--muted);font-weight:700;letter-spacing:0.5px">Batas Akhir Sesi</div>
                <div style="font-size:16px;font-weight:800;color:var(--bad);margin-top:6px">{{ $session->closes_at->format('d/m/Y H:i') }}</div>
                <div style="font-size:11.5px;color:var(--muted);margin-top:2px">{{ config('app.timezone') }}</div>
            </div>
            <div style="background:#fff;padding:18px 24px;text-align:center">
                <div style="font-size:11px;text-transform:uppercase;color:var(--muted);font-weight:700;letter-spacing:0.5px">Percobaan</div>
                <div style="font-size:20px;font-weight:800;color:var(--ok);margin-top:4px">1 Kali</div>
                <div style="font-size:11.5px;color:var(--muted);margin-top:2px">Jawaban tersimpan otomatis</div>
            </div>
        </div>

        {{-- Petunjuk & Tata Tertib --}}
        <div class="card-body" style="padding:28px 32px">
            <h4 style="font-size:15px;font-weight:700;margin:0 0 16px;color:var(--text);display:flex;align-items:center;gap:8px">
                <i class="fas fa-clipboard-list" style="color:var(--navy)"></i> Tata Tertib & Ketentuan Ujian
            </h4>
            
            <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:28px">
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <span style="width:24px;height:24px;border-radius:50%;background:#EEF2F9;color:var(--navy);font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">1</span>
                    <div style="font-size:13.5px;color:var(--text);line-height:1.5">
                        <strong>Penyimpanan Otomatis:</strong> Setiap Anda memilih atau mengubah jawaban, sistem akan langsung menyimpannya ke server secara otomatis.
                    </div>
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <span style="width:24px;height:24px;border-radius:50%;background:#EEF2F9;color:var(--navy);font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">2</span>
                    <div style="font-size:13.5px;color:var(--text);line-height:1.5">
                        <strong>Ketahanan Koneksi (Offline Resilience):</strong> Jika internet terputus di tengah ujian, <em>jangan menutup halaman ujian</em>. Jawaban tetap aman di memori perangkat dan otomatis terkirim saat internet tersambung kembali.
                    </div>
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <span style="width:24px;height:24px;border-radius:50%;background:#EEF2F9;color:var(--navy);font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">3</span>
                    <div style="font-size:13.5px;color:var(--text);line-height:1.5">
                        <strong>Integritas Ujian:</strong> Aktivitas perpindahan tab, jendela peramban, atau aplikasi lain terdeteksi oleh sensor pengawas ujian.
                    </div>
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start">
                    <span style="width:24px;height:24px;border-radius:50%;background:#EEF2F9;color:var(--navy);font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0">4</span>
                    <div style="font-size:13.5px;color:var(--text);line-height:1.5">
                        <strong>Batas Waktu:</strong> Bila waktu habis, sistem secara otomatis mengumpulkan jawaban yang telah tersimpan.
                    </div>
                </div>
            </div>

            {{-- Kunci Fullscreen Opsional --}}
            <div class="fullscreen-prep-card" style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
                <div style="display:flex;align-items:center;gap:14px">
                    <div id="fs-prep-icon-box" style="width:42px;height:42px;border-radius:10px;background:#DCFCE7;color:#16A34A;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
                        <i class="fas fa-expand-arrows-alt"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:14px;color:#166534;display:flex;align-items:center;gap:8px">
                            <span>Kunci Mode Layar Penuh (Fullscreen)</span>
                            <span id="fs-prep-badge-on" class="tag tag-ok" style="font-size:10.5px;padding:2px 8px;display:none">Aktif</span>
                            <span id="fs-prep-badge-off" class="tag" style="background:#E2E8F0;color:#475569;font-size:10.5px;padding:2px 8px">Opsional / Disarankan</span>
                        </div>
                        <div style="font-size:12.5px;color:#15803D;margin-top:2px;line-height:1.4">
                            Kunci tampilan layar penuh untuk memaksimalkan fokus pengerjaan dan meminimalkan godaan membuka tab lain.
                        </div>
                    </div>
                </div>
                <div>
                    <button type="button" id="btn-prep-fullscreen" onclick="toggleFullscreenPrep()" class="btn btn-secondary" style="border-color:#86EFAC;color:#166534;background:#fff;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:6px">
                        <i class="fas fa-expand" id="prep-fs-icon"></i>
                        <span id="prep-fs-label">Masuk Mode Layar Penuh</span>
                    </button>
                </div>
            </div>

            {{-- Tombol Aksi --}}
            <div style="border-top:1px solid var(--line);padding-top:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
                <a href="{{ route('cbt.student.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                </a>

                @if($session->status === 'running' && now()->greaterThanOrEqualTo($session->opens_at) && now()->lessThan($session->closes_at))
                    <form method="POST" action="{{ route('cbt.student.begin', $session) }}" onsubmit="return confirm('Apakah Anda sudah siap memulai ujian? Waktu akan langsung berjalan!')">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="padding:10px 28px;font-size:14px;box-shadow:0 4px 12px rgba(31,56,100,0.25)">
                            <i class="fas fa-play"></i> Mulai ujian sekarang
                        </button>
                    </form>
                @else
                    <div class="note note-warn" style="margin:0;font-size:13px;display:flex;align-items:center;gap:8px">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Sesi belum dibuka oleh pengawas atau sudah melewati batas waktu. Silakan hubungi guru pengajar.</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function updateFullscreenPrepUI() {
    const isFs = !!document.fullscreenElement;
    const badgeOn = document.getElementById('fs-prep-badge-on');
    const badgeOff = document.getElementById('fs-prep-badge-off');
    const label = document.getElementById('prep-fs-label');
    const icon = document.getElementById('prep-fs-icon');
    const btn = document.getElementById('btn-prep-fullscreen');
    const iconBox = document.getElementById('fs-prep-icon-box');

    if (isFs) {
        if (badgeOn) badgeOn.style.display = 'inline-block';
        if (badgeOff) badgeOff.style.display = 'none';
        if (label) label.textContent = 'Keluar Layar Penuh';
        if (icon) icon.className = 'fas fa-compress';
        if (btn) {
            btn.style.background = '#DCFCE7';
            btn.style.borderColor = '#86EFAC';
        }
        if (iconBox) iconBox.innerHTML = '<i class="fas fa-check"></i>';
    } else {
        if (badgeOn) badgeOn.style.display = 'none';
        if (badgeOff) badgeOff.style.display = 'inline-block';
        if (label) label.textContent = 'Masuk Mode Layar Penuh';
        if (icon) icon.className = 'fas fa-expand';
        if (btn) {
            btn.style.background = '#fff';
            btn.style.borderColor = '#86EFAC';
        }
        if (iconBox) iconBox.innerHTML = '<i class="fas fa-expand-arrows-alt"></i>';
    }
}

function toggleFullscreenPrep() {
    if (!document.fullscreenElement) {
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen().then(updateFullscreenPrepUI).catch(err => {
                alert('Peramban Anda membatasi peralihan otomatis layar penuh. Tekan F11 pada keyboard untuk mengaktifkan layar penuh.');
            });
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen().then(updateFullscreenPrepUI).catch(() => {});
        }
    }
}

document.addEventListener('fullscreenchange', updateFullscreenPrepUI);
document.addEventListener('DOMContentLoaded', updateFullscreenPrepUI);
</script>
@endpush
