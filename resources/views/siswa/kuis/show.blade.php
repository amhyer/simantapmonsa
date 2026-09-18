@extends('layouts.app')

@section('title', 'Kerjakan Kuis - SIMANTAP')

@section('sidebar')
    @include('siswa.partials.sidebar')
@endsection

@section('page_title', $kuis->judul)
@section('page_subtitle', $kuis->mata_pelajaran . ' · ' . $kuis->jumlah_soal . ' soal')

@section('header_actions')
    <a href="{{ route('siswa.kuis.index') }}" class="btn btn-ghost btn-sm">← Kembali</a>
@endsection

@section('content')
    @if($sudahMengerjakan)
        <div class="card" style="margin-bottom:16px">
            <div class="card-body">
                <div style="text-align:center;padding:20px 0">
                    <div style="font-size:48px;margin-bottom:12px">✅</div>
                    <h3 style="margin-bottom:8px">Anda Sudah Mengerjakan Kuis Ini</h3>
                    <div style="color:var(--muted);margin-bottom:16px">
                        Kuis ini sudah dikerjakan pada {{ \Carbon\Carbon::parse($sudahMengerjakan->waktu)->translatedFormat('d M Y H:i') }}
                    </div>
                    <div class="grid grid-3" style="max-width:400px;margin:0 auto">
                        <div class="stat-card primary">
                            <div class="stat-label">Skor</div>
                            <div class="stat-value" style="font-size:24px">{{ $sudahMengerjakan->skor }}</div>
                        </div>
                        <div class="stat-card ok">
                            <div class="stat-label">Benar</div>
                            <div class="stat-value" style="font-size:24px">{{ $sudahMengerjakan->benar }}/{{ $sudahMengerjakan->total }}</div>
                        </div>
                        <div class="stat-card {{ $sudahMengerjakan->tuntas ? 'ok' : 'bad' }}">
                            <div class="stat-label">Status</div>
                            <div class="stat-value" style="font-size:16px">{{ $sudahMengerjakan->tuntas ? 'Tuntas' : 'Belum Tuntas' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        @if($kuis->petunjuk)
            <div class="note note-gold" style="margin-bottom:16px">
                <b><i class="fas fa-info-circle"></i> Petunjuk</b><br>
                {{ $kuis->petunjuk }}
            </div>
        @endif

        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <h3><i class="fas fa-clock" style="margin-right:8px;color:var(--gold)"></i>Informasi Kuis</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-3">
                    <div>
                        <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;font-weight:700;margin-bottom:4px">Jenis</div>
                        <span class="tag tag-gold">{{ $kuis->jenis }}</span>
                    </div>
                    <div>
                        <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;font-weight:700;margin-bottom:4px">KKM</div>
                        <span class="tag tag-primary">{{ $kuis->kkm }}</span>
                    </div>
                    <div>
                        <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;font-weight:700;margin-bottom:4px">Jumlah Soal</div>
                        <span class="tag tag-mut">{{ $kuis->jumlah_soal }} soal</span>
                    </div>
                </div>
                @if($kuis->batas_waktu)
                    <div style="margin-top:12px">
                        <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085;font-weight:700;margin-bottom:4px">Batas Waktu</div>
                        <span class="tag tag-bad">
                            <i class="fas fa-clock"></i>
                            {{ \Carbon\Carbon::parse($kuis->batas_waktu)->translatedFormat('d M Y H:i') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        @if($kuis->stimulus)
            <div class="note" style="margin-bottom:16px">
                <b><i class="fas fa-lightbulb"></i> Stimulus</b><br>
                {{ $kuis->stimulus }}
            </div>
        @endif

        <form action="{{ route('siswa.kuis.submit', $kuis->id) }}" method="POST" id="formKuis">
            @csrf
            <input type="hidden" name="durasi" id="durasiInput" value="0">
            <input type="hidden" name="start_time" id="startTime" value="{{ now()->timestamp }}">

            @php
                $soal = $kuis->soal ?? [];
            @endphp

            @foreach($soal as $i => $item)
                <div class="card" style="margin-bottom:16px">
                    <div class="card-header">
                        <h3>Soal {{ $i + 1 }}</h3>
                        @if($item['jenis'] ?? null)
                            <span class="tag tag-gold">{{ $item['jenis'] }}</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div style="font-size:15px;line-height:1.7;margin-bottom:14px">
                            @if(is_array($item['pertanyaan']))
                                {!! nl2br(e(implode("\n", $item['pertanyaan']))) !!}
                            @else
                                {!! nl2br(e($item['pertanyaan'])) !!}
                            @endif
                        </div>

                        @if(!empty($item['gambar']))
                            <div style="margin-bottom:14px">
                                <img src="{{ $item['gambar'] }}" alt="Gambar soal {{ $i + 1 }}" style="max-width:100%;border-radius:8px;border:1px solid var(--line)">
                            </div>
                        @endif

                        @if(!empty($item['pilihan']))
                            <div style="display:flex;flex-direction:column;gap:8px">
                                @foreach($item['pilihan'] as $key => $pilihan)
                                    <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;border:1px solid var(--line);cursor:pointer;transition:0.15s" class="pilihan-label" onmouseover="this.style.borderColor='var(--navy)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--line)'">
                                        <input type="radio" name="jawaban[{{ $i }}]" value="{{ $key }}" style="width:16px;height:16px;accent-color:var(--navy)">
                                        <span style="font-size:14px">{{ $pilihan }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <input type="text" name="jawaban[{{ $i }}]" placeholder="Masukkan jawaban..." style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid var(--line);font-size:14px" required>
                        @endif
                    </div>
                </div>
            @endforeach

            @if(count($soal) > 0)
                <div class="card">
                    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                        <div style="font-size:13px;color:var(--muted)">
                            <i class="fas fa-clock" style="margin-right:4px"></i>
                            Waktu: <span id="timerDisplay">00:00:00</span>
                        </div>
                        <button type="submit" class="btn btn-gold" onclick="return confirm('Apakah Anda yakin ingin mengirim jawaban?')">
                            <i class="fas fa-paper-plane"></i> Kirim Jawaban
                        </button>
                    </div>
                </div>
            @endif
        </form>
    @endif

    @push('scripts')
        <script>
            const startTime = parseInt(document.getElementById('startTime').value);
            const durasiInput = document.getElementById('durasiInput');
            const timerDisplay = document.getElementById('timerDisplay');

            function updateTimer() {
                const now = Math.floor(Date.now() / 1000);
                const elapsed = now - startTime;
                durasiInput.value = elapsed;

                const h = String(Math.floor(elapsed / 3600)).padStart(2, '0');
                const m = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
                const s = String(elapsed % 60).padStart(2, '0');
                timerDisplay.textContent = `${h}:${m}:${s}`;
            }

            if (timerDisplay) {
                setInterval(updateTimer, 1000);
                updateTimer();
            }

            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const group = this.closest('.card-body');
                    group.querySelectorAll('.pilihan-label').forEach(label => {
                        label.style.borderColor = 'var(--line)';
                        label.style.background = 'transparent';
                    });
                    this.closest('.pilihan-label').style.borderColor = 'var(--navy)';
                    this.closest('.pilihan-label').style.background = '#EEF2F9';
                });
            });
        </script>
    @endpush
@endsection
