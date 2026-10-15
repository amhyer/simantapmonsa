@extends('layouts.app')

@section('title', 'Hasil Ujian - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', 'Hasil Ujian: ' . $result->testSession->nama_sesi)
@section('page_subtitle', 'Selesai ' . $result->waktu_selesai->format('d/m H:i'))

@section('content')
<div class="grid grid-2" style="margin-bottom:20px">
    {{-- Score Summary Card --}}
    <div class="card">
        <div class="card-header">
            <h3>Ringkasan Nilai</h3>
        </div>
        <div class="card-body" style="text-align:center;padding:24px">
            <div style="font-size:48px;font-weight:700;color:var(--violet);margin-bottom:8px">
                {{ number_format($result->nilai_akhir, 0) }}
            </div>
            <div style="font-size:24px;color:#667085;margin-bottom:16px">
                dari {{ number_format($result->nilai_maksimal, 0) }}
            </div>
            <div style="font-size:18px;font-weight:600;margin-bottom:16px">
                <span style="color:#667085">Persentase:</span> <span style="color:var(--violet)">{{ number_format(($result->nilai_akhir / $result->nilai_maksimal) * 100, 1) }}%</span>
            </div>
            
            <div style="display:flex;justify-content:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
                <span class="tag {{ $result->lulus ? 'tag-ok' : 'tag-bad' }}" style="font-size:14px;padding:8px 16px">
                    @if($result->lulus)
                        ✓ LULUS
                    @else
                        ✗ BELUM LULUS
                    @endif
                </span>
                
                @php
                    $predikat = $result->nilai_akhir >= 85 ? 'A' : ($result->nilai_akhir >= 70 ? 'B' : ($result->nilai_akhir >= 55 ? 'C' : ($result->nilai_akhir >= 40 ? 'D' : 'E')));
                    $predikatColor = ['A' => 'tag-violet', 'B' => 'tag-mut', 'C' => 'tag-gold', 'D' => 'tag-bad', 'E' => 'tag-bad'];
                @endphp
                <span class="tag {{ $predikatColor[$predikat] ?? 'tag-mut' }}" style="font-size:14px;padding:8px 16px">
                    Predikat: <span style="font-weight:700">{{ $predikat }}</span>
                </span>
            </div>
            
            <div style="color:#667085;font-size:13px">
                <i class="fas fa-clock" style="margin-right:6px"></i>
                Waktu Mulai: {{ $result->waktu_mulai->format('d/m H:i') }} <br/>
                Durasi: {{ floor($result->durasi_detik / 60) }} menit {{ $result->durasi_detik % 60 }} detik <br/>
                Waktu Selesai: {{ $result->waktu_selesai->format('d/m H:i') }}
            </div>
        </div>
    </div>

    {{-- Answer Summary Card --}}
    <div class="card">
        <div class="card-header">
            <h3>Ringkasan Jawaban</h3>
        </div>
        <div class="card-body" style="padding:24px">
            <div class="grid grid-2" style="gap:16px">
                <div style="text-align:center;padding:16px;background:#E8F5E9;border-radius:8px">
                    <div style="font-size:32px;font-weight:700;color:var(--ok)">{{ $result->jumlah_benar ?? 0 }}</div>
                    <div style="font-size:12px;color:#667085">Jawaban Benar</div>
                </div>
                <div style="text-align:center;padding:16px;background:#FFEBEE;border-radius:8px">
                    <div style="font-size:32px;font-weight:700;color:var(--bad)">{{ $result->jumlah_salah ?? 0 }}</div>
                    <div style="font-size:12px;color:#667085">Jawaban Salah</div>
                </div>
                <div style="text-align:center;padding:16px;background:#FFF3E0;border-radius:8px">
                    <div style="font-size:32px;font-weight:700;color:var(--warn)">{{ $result->jumlah_tidak_dijawab ?? 0 }}</div>
                    <div style="font-size:12px;color:#667085">Tidak Dijawab</div>
                </div>
                <div style="text-align:center;padding:16px;background:#F3E5F5;border-radius:8px">
                    <div style="font-size:32px;font-weight:700;color:var(--violet)">{{ $result->integrity_score ?? 100 }}</div>
                    <div style="font-size:12px;color:#667085">Integritas (%)</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Violations Summary (if any) --}}
@if(($result->integrity_score ?? 100) < 100 && $result->integrityViolations && $result->integrityViolations->count() > 0)
    <div class="card" style="margin-bottom:20px;border-left:3px solid var(--warn)">
        <div class="card-header" style="background:#FFF3E0">
            <h3>⚠️ Catatan Integritas</h3>
        </div>
        <div class="card-body tight">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Jenis Pelanggaran</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result->integrityViolations as $violation)
                            <tr>
                                <td style="font-size:12px">{{ $violation->created_at->format('H:i:s') }}</td>
                                <td style="font-size:12px">
                                    <span class="tag tag-warn">{{ str_replace('_', ' ', ucfirst($violation->jenis_pelanggaran)) }}</span>
                                </td>
                                <td style="font-size:12px">{{ $violation->deskripsi }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

{{-- Question Review (if teacher enabled feedback) --}}
@if($result->testSession->tampilkan_nilai || auth()->user()->peran === 'guru')
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3>Ulasan Soal</h3>
            <span class="tag tag-mut">{{ $result->studentAnswers->count() }} soal</span>
        </div>
        <div class="card-body tight">
            @if($result->studentAnswers->isEmpty())
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h4>Belum ada ulasan</h4>
                    <p>Ulasan soal akan ditampilkan setelah guru membuka hasil penilaian.</p>
                </div>
            @else
                <div style="display:flex;flex-direction:column;gap:12px">
                    @foreach($result->studentAnswers as $idx => $answer)
                        @php
                            $question = $answer->question;
                            $isCorrect = $answer->is_correct ?? false;
                            $statusIcon = $isCorrect ? '✓' : ($answer->jawaban ? '✗' : '—');
                            $statusColor = $isCorrect ? 'var(--ok)' : ($answer->jawaban ? 'var(--bad)' : '#667085');
                        @endphp
                        <div style="border:1px solid #E4E7EC;border-radius:8px;overflow:hidden">
                            <div 
                                @click="$el.nextElementSibling.style.display = $el.nextElementSibling.style.display === 'none' ? 'block' : 'none'"
                                style="padding:12px 16px;background:#F9FAFB;cursor:pointer;display:flex;justify-content:space-between;align-items:center"
                            >
                                <div style="display:flex;gap:12px;align-items:center;flex:1">
                                    <span style="font-weight:600;color:#667085">{{ $idx + 1 }}.</span>
                                    <span style="flex:1;font-size:13px;color:#101828;max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                        {{ Str::limit($question->pertanyaan, 80) }}
                                    </span>
                                    <span style="font-weight:700;color:{{ $statusColor }};font-size:16px">{{ $statusIcon }}</span>
                                </div>
                                <i class="fas fa-chevron-down" style="color:#667085"></i>
                            </div>
                            
                            <div style="padding:16px;background:#fff;display:none">
                                <div style="margin-bottom:12px">
                                    <p style="font-size:12px;color:#667085;font-weight:600;margin-bottom:4px">PERTANYAAN:</p>
                                    <p style="font-size:13px;line-height:1.6">{{ $question->pertanyaan }}</p>
                                </div>
                                
                                @if($question->media_url)
                                    <div style="margin-bottom:12px;border-radius:6px;overflow:hidden;background:#F5F5F5;max-height:200px">
                                        @if($question->media_type === 'image')
                                            <img src="{{ $question->media_url }}" style="width:100%;height:auto;object-fit:contain;max-height:200px" alt="Media"/>
                                        @elseif($question->media_type === 'video')
                                            <video controls style="width:100%;max-height:200px">
                                                <source src="{{ $question->media_url }}" />
                                            </video>
                                        @endif
                                    </div>
                                @endif
                                
                                <div style="margin-bottom:12px;padding:10px;background:#FFF3CD;border-radius:6px;border-left:3px solid var(--warn)">
                                    <p style="font-size:12px;color:#667085;font-weight:600;margin-bottom:4px">JAWABAN ANDA:</p>
                                    <p style="font-size:13px;color:#101828">
                                        {{ $answer->jawaban ?? '(Tidak dijawab)' }}
                                        <span style="color:{{ $statusColor }};font-weight:700;margin-left:8px">{{ $statusIcon }}</span>
                                    </p>
                                </div>
                                
                                @if($isCorrect || !$answer->jawaban)
                                    <div style="padding:10px;background:#E8F5E9;border-radius:6px;border-left:3px solid var(--ok)">
                                        <p style="font-size:12px;color:#667085;font-weight:600;margin-bottom:4px">JAWABAN BENAR:</p>
                                        <p style="font-size:13px;color:#101828">
                                            {{ $answer->correct_answer ?? '-' }}
                                        </p>
                                    </div>
                                @endif
                                
                                @if($question->pembahasan)
                                    <div style="margin-top:12px;padding:10px;background:#E3F2FD;border-radius:6px;border-left:3px solid #1976D2">
                                        <p style="font-size:12px;color:#667085;font-weight:600;margin-bottom:4px">PEMBAHASAN:</p>
                                        <p style="font-size:13px;color:#101828;line-height:1.6">{{ $question->pembahasan }}</p>
                                    </div>
                                @endif
                                
                                <div style="margin-top:12px;font-size:12px;color:#667085">
                                    <span class="tag tag-mut">{{ $question->poin ?? 1 }} poin</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif

{{-- Actions --}}
<div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap">
    <a href="{{ route('cbt.student.index') }}" class="btn" style="background:#7C3AED;color:#fff">
        <i class="fas fa-arrow-left" style="margin-right:6px"></i>Kembali ke Daftar Ujian
    </a>
    <button onclick="window.print()" class="btn" style="background:#E4E7EC;color:#101828">
        <i class="fas fa-print" style="margin-right:6px"></i>Cetak Hasil
    </button>
</div>

<style>
    @media print {
        .btn, #sidebar, .topbar, a[href] {
            display: none;
        }
        body {
            background: white;
        }
        .card {
            page-break-inside: avoid;
            border: 1px solid #ddd;
        }
    }
</style>

@endsection
