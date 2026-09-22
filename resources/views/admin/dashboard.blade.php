@extends('layouts.app')

@section('title', 'Admin Dashboard - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Dasbor Admin')
@section('page_subtitle', $sekolahSettings ? $sekolahSettings->nama_sekolah : 'Kelola seluruh sistem')

@section('content')
    {{-- Top Stats --}}
    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-label">Total Siswa</div>
            <div class="stat-value">{{ number_format($siswa) }}</div>
            <div class="stat-change">{{ $siswaAktif }} aktif</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-chalkboard-teacher stat-icon"></i>
            <div class="stat-label">Guru & PTK</div>
            <div class="stat-value">{{ number_format($ptk) }}</div>
            <div class="stat-change">{{ $guru }} akun guru aktif</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-school stat-icon"></i>
            <div class="stat-label">Kelas / Rombel</div>
            <div class="stat-value">{{ number_format($rombel) }}</div>
            <div class="stat-change">dari Dapodik</div>
        </div>
        <div class="stat-card" style="border-left:4px solid #667085">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-label">Akun Pengguna</div>
            <div class="stat-value" style="color:#667085">{{ $totalUsers }}</div>
            <div class="stat-change">Termasuk admin</div>
        </div>
        <div class="stat-card ok">
            <i class="fas fa-signal stat-icon"></i>
            <div class="stat-label">Online (±15 mnt)</div>
            <div class="stat-value">{{ number_format($onlineCount) }}</div>
            <div class="stat-change">berdasarkan login terakhir</div>
        </div>
        <div class="stat-card gold">
            <i class="fas fa-calendar-days stat-icon"></i>
            <div class="stat-label">Pembelajaran</div>
            <div class="stat-value">{{ number_format($totalJadwal) }}</div>
            <div class="stat-change">jadwal dari Dapodik</div>
        </div>
    </div>

    {{-- Status Kerja Administrator (ala e-Rapor) --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-list-check" style="color:var(--navy)"></i> Status Kerja Administrator</h3>
            <span class="tag {{ $progresKerja >= 80 ? 'tag-ok' : ($progresKerja >= 50 ? 'tag-gold' : 'tag-mut') }}">{{ $progresKerja }}%</span>
        </div>
        <div class="card-body tight">
            <div style="font-size:12px;color:var(--muted);margin:0 16px 10px">Rincian Kerja Utama Administrator :</div>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                            <th style="padding:10px 14px;text-align:center;font-size:11.5px;color:#667085;width:50px">No</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11.5px;color:#667085">Jenis Kegiatan Administrator</th>
                            <th style="padding:10px 14px;text-align:center;font-size:11.5px;color:#667085">Status Pekerjaan</th>
                            <th style="padding:10px 14px;text-align:left;font-size:11.5px;color:#667085;width:180px">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($statusKerja as $i => $kerja)
                            <tr>
                                <td style="padding:10px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $i + 1 }}</td>
                                <td style="padding:10px 14px;border-bottom:1px solid #E4E7EC">{{ $kerja['label'] }}</td>
                                <td style="padding:10px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                    @if($kerja['done'])
                                        <span class="tag tag-ok">Sudah dikerjakan</span>
                                    @else
                                        <span class="tag tag-mut">Belum dikerjakan</span>
                                    @endif
                                </td>
                                <td style="padding:10px 14px;border-bottom:1px solid #E4E7EC">
                                    <div style="background:#EEF2F6;border-radius:6px;height:16px;overflow:hidden">
                                        <div style="width:{{ $kerja['done'] ? 100 : 0 }}%;background:{{ $kerja['done'] ? 'var(--ok)' : 'transparent' }};height:16px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff">{{ $kerja['done'] ? '100%' : '' }}</div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin:14px 16px 4px;font-size:12px;color:var(--muted)">Progres Kerja Utama Administrator :</div>
            <div style="margin:0 16px 16px;background:#EEF2F6;border-radius:8px;height:22px;overflow:hidden">
                <div style="width:{{ $progresKerja }}%;background:var(--gold);height:22px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff">{{ $progresKerja }}%</div>
            </div>
        </div>
    </div>

    {{-- Info Sekolah --}}
    @if($sekolahSettings)
        <div style="display:flex;align-items:center;gap:16px;padding:16px 20px;background:linear-gradient(135deg,#1F3864,#2d5a9e);border-radius:12px;margin-bottom:20px;color:#fff;flex-wrap:wrap">
            <div style="width:48px;height:48px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">
                <i class="fas fa-building-columns"></i>
            </div>
            <div style="flex:1;min-width:200px">
                <div style="font-size:18px;font-weight:800;letter-spacing:-.3px">{{ $sekolahSettings->nama_sekolah }}</div>
                <div style="font-size:13px;opacity:.85">{{ $sekolahSettings->npsn ? 'NPSN: '.$sekolahSettings->npsn : '' }} {{ $sekolahSettings->alamat ? '· '.$sekolahSettings->alamat : '' }}</div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                @if($sekolahSettings->jenjang)
                    <span style="background:rgba(255,255,255,.15);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600">{{ $sekolahSettings->jenjang }}</span>
                @endif
                @if($sekolahSettings->akreditasi)
                    <span style="background:rgba(255,255,255,.15);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600">Akreditasi {{ $sekolahSettings->akreditasi }}</span>
                @endif
                @if($semesterAktif)
                    <span style="background:#B8860B;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600">Semester {{ $semesterAktif->tahun_ajaran ?? $semesterAktif->semester_id }}</span>
                @endif
            </div>
        </div>
    @endif

    {{-- Dapodik Sync Status --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-arrows-rotate" style="color:var(--gold)"></i> Status Sinkronisasi Dapodik</h3>
            <a href="{{ route('admin.dapodik.index') }}" class="btn btn-sm btn-gold"><i class="fas fa-link"></i> Buka Import Dapodik</a>
        </div>
        <div class="card-body">
            @if($lastSync)
                <div class="grid grid-4" style="margin-bottom:16px">
                    <div class="stat-card ok">
                        <div class="stat-label">Siswa dari Dapodik</div>
                        <div class="stat-value" style="font-size:22px">{{ number_format($syncedSiswa) }}</div>
                        <div class="stat-change" style="font-size:11px;color:var(--muted)">dari {{ number_format($siswa) }} total</div>
                    </div>
                    <div class="stat-card gold">
                        <div class="stat-label">Guru dari Dapodik</div>
                        <div class="stat-value" style="font-size:22px">{{ number_format($ptk) }}</div>
                        <div class="stat-change" style="font-size:11px;color:var(--muted)">{{ $syncedGuru }} akun aktif</div>
                    </div>
                    <div class="stat-card primary">
                        <div class="stat-label">Total PTK</div>
                        <div class="stat-value" style="font-size:22px">{{ number_format($ptk) }}</div>
                    </div>
                    <div class="stat-card" style="border-left:4px solid var(--navy)">
                        <div class="stat-label">Total Rombel</div>
                        <div class="stat-value" style="font-size:22px">{{ number_format($rombel) }}</div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:#E6F5F0;border-radius:8px;border-left:3px solid var(--ok);margin-bottom:12px">
                    <span style="width:8px;height:8px;border-radius:50%;background:var(--ok);flex-shrink:0"></span>
                    <span style="font-size:13px;color:#065F46">Terakhir sinkron: <b>{{ $lastSync->created_at->format('d M Y H:i') }}</b> — {{ $lastSync->total_data }} data | {{ $lastSync->berhasil }} berhasil | {{ $lastSync->diperbarui }} diperbarui | {{ $lastSync->gagal }} gagal</span>
                </div>

                @if($syncLogs->count() > 1)
                    <div style="font-size:12px;color:var(--muted);margin-top:8px">
                        <b>Riwayat 5 sync terakhir:</b>
                        @foreach($syncLogs as $log)
                            <div style="display:flex;align-items:center;gap:8px;padding:4px 0;border-bottom:1px solid #F2F4F7">
                                <span class="tag tag-primary" style="font-size:10px">{{ $log->tipe ?? 'semua' }}</span>
                                <span style="flex:1">{{ $log->created_at->format('d/m H:i') }}</span>
                                <span style="font-weight:600">{{ $log->berhasil }}</span>
                                <span style="color:var(--ok)">OK</span>
                                <span style="font-weight:600;color:var(--bad)">{{ $log->gagal }}</span>
                                <span style="color:var(--bad)">Gagal</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div style="display:flex;align-items:center;gap:12px;padding:16px;background:#FEF3E2;border-radius:10px;border:1px solid #F59E0B">
                    <i class="fas fa-exclamation-triangle" style="font-size:24px;color:#B54708"></i>
                    <div>
                        <div style="font-weight:700;color:#92400E">Belum ada sinkronisasi</div>
                        <div style="font-size:13px;color:#92400E">Gunakan menu Import Dapodik atau SIMANTAP Bridge untuk mengambil data dari Dapodik.</div>
                    </div>
                    <a href="{{ route('admin.dapodik.index') }}" class="btn btn-sm btn-gold" style="margin-left:auto"><i class="fas fa-link"></i> Atur Sekarang</a>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px">
        {{-- Konten Akademik --}}
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-book" style="color:var(--navy)"></i> Ringkasan Konten</h3>
            </div>
            <div class="card-body tight">
                <table style="width:100%;border-collapse:collapse">
                    <tbody>
                        <tr style="border-bottom:1px solid #F2F4F7">
                            <td style="padding:12px 16px;display:flex;align-items:center;gap:10px">
                                <span style="width:32px;height:32px;background:#EEF2F9;color:var(--navy);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-book-open" style="font-size:14px"></i></span>
                                <div>
                                    <div style="font-weight:600;font-size:13px">Materi Pembelajaran</div>
                                    <div style="font-size:11px;color:var(--muted)">Upload oleh guru</div>
                                </div>
                            </td>
                            <td style="padding:12px 16px;text-align:right;font-weight:700;font-size:18px;color:var(--navy)">{{ number_format($totalMateri) }}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #F2F4F7">
                            <td style="padding:12px 16px;display:flex;align-items:center;gap:10px">
                                <span style="width:32px;height:32px;background:#FBF4E4;color:var(--gold);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-clipboard-question" style="font-size:14px"></i></span>
                                <div>
                                    <div style="font-weight:600;font-size:13px">Kuis Online</div>
                                    <div style="font-size:11px;color:var(--muted)">Dibuat oleh guru</div>
                                </div>
                            </td>
                            <td style="padding:12px 16px;text-align:right;font-weight:700;font-size:18px;color:var(--gold)">{{ number_format($totalKuis) }}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #F2F4F7">
                            <td style="padding:12px 16px;display:flex;align-items:center;gap:10px">
                                <span style="width:32px;height:32px;background:#E6F5F0;color:var(--ok);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-clipboard-check" style="font-size:14px"></i></span>
                                <div>
                                    <div style="font-weight:600;font-size:13px">Kehadiran</div>
                                    <div style="font-size:11px;color:var(--muted)">{{ $totalKehadiran > 0 ? round($totalHadir / $totalKehadiran * 100, 1).'%' : '0%' }} hadir</div>
                                </div>
                            </td>
                            <td style="padding:12px 16px;text-align:right;font-weight:700;font-size:18px;color:var(--ok)">{{ number_format($totalKehadiran) }}</td>
                        </tr>
                        <tr>
                            <td style="padding:12px 16px;display:flex;align-items:center;gap:10px">
                                <span style="width:32px;height:32px;background:#FEE9E7;color:var(--bad);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-users-gear" style="font-size:14px"></i></span>
                                <div>
                                    <div style="font-weight:600;font-size:13px">Total Pengguna</div>
                                    <div style="font-size:11px;color:var(--muted)">{{ $guru }} guru · {{ $siswa }} siswa</div>
                                </div>
                            </td>
                            <td style="padding:12px 16px;text-align:right;font-weight:700;font-size:18px;color:#667085">{{ number_format($totalUsers) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Aktivitas Terbaru --}}
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock" style="color:var(--gold)"></i> Aktivitas Terbaru</h3>
            </div>
            <div class="card-body tight">
                @if($recentUsers->count())
                    @foreach($recentUsers as $u)
                        <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid #F2F4F7">
                            <span style="width:32px;height:32px;border-radius:50%;background:{{ $u->peran === 'guru' ? '#EEF2F9' : ($u->peran === 'siswa' ? '#D1FAE5' : '#FBF4E4') }};color:{{ $u->peran === 'guru' ? '#4338CA' : ($u->peran === 'siswa' ? '#065F46' : '#92400E') }};display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">{{ substr($u->nama_lengkap, 0, 2) }}</span>
                            <div style="flex:1;min-width:0">
                                <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $u->nama_lengkap }}</div>
                                <div style="font-size:11px;color:var(--muted)">{{ ucfirst($u->peran) }}</div>
                            </div>
                            <div style="text-align:right;flex-shrink:0">
                                <div style="font-size:12px;color:var(--muted)">{{ $u->terakhir_masuk ? $u->terakhir_masuk->diffForHumans() : '-' }}</div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div style="text-align:center;padding:30px;color:var(--muted)">
                        <div style="font-size:32px;margin-bottom:8px"><i class="fas fa-user-clock"></i></div>
                        <div style="font-size:13px">Belum ada aktivitas pengguna</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt" style="color:var(--gold)"></i> Aksi Cepat</h3>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
                <a href="{{ route('admin.users.index') }}" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#EEF2F9;border-radius:10px;border:1px solid transparent;text-decoration:none;color:var(--navy);transition:all .15s" onmouseover="this.style.borderColor='var(--navy)';this.style.transform='translateY(-1px)'" onmouseout="this.style.borderColor='transparent';this.style.transform='none'">
                    <span style="width:36px;height:36px;background:var(--navy);color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-users"></i></span>
                    <div>
                        <div style="font-weight:600;font-size:13px">Kelola Pengguna</div>
                        <div style="font-size:11px;color:var(--muted)">{{ $totalUsers }} akun</div>
                    </div>
                </a>
                <a href="{{ route('admin.peta-kelas.index') }}" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#FBF4E4;border-radius:10px;border:1px solid transparent;text-decoration:none;color:#92400E;transition:all .15s" onmouseover="this.style.borderColor='var(--gold)';this.style.transform='translateY(-1px)'" onmouseout="this.style.borderColor='transparent';this.style.transform='none'">
                    <span style="width:36px;height:36px;background:var(--gold);color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-school"></i></span>
                    <div>
                        <div style="font-weight:600;font-size:13px">Peta Kelas</div>
                        <div style="font-size:11px;color:var(--muted)">{{ $kelas }} kelas</div>
                    </div>
                </a>
                <a href="{{ route('admin.dapodik.index') }}" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#E6F5F0;border-radius:10px;border:1px solid transparent;text-decoration:none;color:#065F46;transition:all .15s" onmouseover="this.style.borderColor='var(--ok)';this.style.transform='translateY(-1px)'" onmouseout="this.style.borderColor='transparent';this.style.transform='none'">
                    <span style="width:36px;height:36px;background:var(--ok);color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-arrows-rotate"></i></span>
                    <div>
                        <div style="font-weight:600;font-size:13px">Import Dapodik</div>
                        <div style="font-size:11px;color:var(--muted)">{{ $lastSync ? 'Terakhir: '.$lastSync->created_at->diffForHumans() : 'Belum sync' }}</div>
                    </div>
                </a>
                <a href="{{ route('admin.sekolah.index') }}" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#FEE9E7;border-radius:10px;border:1px solid transparent;text-decoration:none;color:#991B1B;transition:all .15s" onmouseover="this.style.borderColor='#DC2626';this.style.transform='translateY(-1px)'" onmouseout="this.style.borderColor='transparent';this.style.transform='none'">
                    <span style="width:36px;height:36px;background:#DC2626;color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-building-columns"></i></span>
                    <div>
                        <div style="font-weight:600;font-size:13px">Identitas Sekolah</div>
                        <div style="font-size:11px;color:var(--muted)">{{ $sekolahSettings->npsn ?? 'Belum diatur' }}</div>
                    </div>
                </a>
                <a href="{{ route('admin.semester.index') }}" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#F2F4F7;border-radius:10px;border:1px solid transparent;text-decoration:none;color:#667085;transition:all .15s" onmouseover="this.style.borderColor='#667085';this.style.transform='translateY(-1px)'" onmouseout="this.style.borderColor='transparent';this.style.transform='none'">
                    <span style="width:36px;height:36px;background:#667085;color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-calendar-alt"></i></span>
                    <div>
                        <div style="font-weight:600;font-size:13px">Semester</div>
                        <div style="font-size:11px;color:var(--muted)">{{ $semesterAktif ? 'Aktif: '.$semesterAktif->tahun_ajaran : 'Belum diatur' }}</div>
                    </div>
                </a>
                <a href="{{ route('admin.backup.index') }}" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#FAFBFD;border-radius:10px;border:1px solid transparent;text-decoration:none;color:#667085;transition:all .15s" onmouseover="this.style.borderColor='#667085';this.style.transform='translateY(-1px)'" onmouseout="this.style.borderColor='transparent';this.style.transform='none'">
                    <span style="width:36px;height:36px;background:#667085;color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fas fa-database"></i></span>
                    <div>
                        <div style="font-weight:600;font-size:13px">Backup & Restore</div>
                        <div style="font-size:11px;color:var(--muted)">Data system</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
@endsection
