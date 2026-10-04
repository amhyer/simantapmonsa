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
            <input type="hidden" name="soal_aktif" id="soalAktif" value="0">

            @php
                $soal = $kuis->soal ?? [];
            @endphp

            @if(count($soal) > 0)
                <!-- Navigasi Soal -->
                <div class="card" style="margin-bottom:16px;background:var(--background);border:none">
                    <div class="card-body p-0">
                        <div class="nav-soal d-flex justify-content-between align-items-center px-3 py-2" style="font-size:12px;color:var(--muted)">
                            <span>Soal <span id="nomorSaatIni">1</span> dari {{ count($soal) }}</span>
                            <div>
                                <button type="button" class="btn btn-sm btn-prev" disabled style="flex:1;padding:8px 12px;border-radius:6px;background:var(--muted);color:var(--text);margin-right:4px">Sebelumnya</button>
                                <button type="button" class="btn btn-sm btn-next" style="flex:1;padding:8px 12px;border-radius:6px;background:var(--violet);color:white;margin-left:4px">Selanjutnya</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar Soal -->
                <div class="progress progress-sm" style="margin-bottom:12px; height:8px">
                    <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="{{ count($soal) }}" style="width:0%"></div>
                </div>

                @foreach($soal as $i => $item)
                    <div class="card" style="margin-bottom:16px;display:none" id="soal-{{ $i }}">
                        <div class="card-header">
                            <h3>Soal {{ $i + 1 }}</h3>
                            @if($item['jenis'] ?? null)
                                <span class="tag tag-gold float-right">{{ $item['jenis'] }}</span>
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
                                        <label style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;border:1px solid var(--line);cursor:pointer;transition:0.15s" class="pilihan-label" onmouseover="this.style.borderColor='var(--violet)'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='var(--line)'">
                                            <input type="radio" name="jawaban[{{ $i }}]" value="{{ $key }}" style="width:20px;height:20px;accent-color:var(--violet)">
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
            @endif

            <!-- Timer Countdown Display -->
            <div class="card" style="margin-bottom:16px;background:var(--background);border:none">
                <div class="card-body p-0">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2">
                        <span>Waktu Tersisa</span>
                        <div style="font-size:18px;font-weight:bold;color:#dc2626" id="timerDisplay">
                            @if($kuis->batas_waktu)
                                {{ \Carbon\Carbon::parse($kuis->batas_waktu)->diffInSeconds(now()) }} detik
                            @else
                                00:00:00
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-gold w-100" onclick="return confirm('Apakah Anda yakin ingin mengirim jawaban?')">
                <i class="fas fa-paper-plane"></i> Kirim Jawaban
            </button>
        </form>
    @endif

    @push('scripts')
        <script>
            const startTime = parseInt(document.getElementById('startTime').value);
            const durasiInput = document.getElementById('durasiInput');
            const timerDisplay = document.getElementById('timerDisplay');
            const nomorSaatIni = document.getElementById('nomorSaatIni');
            const totalSoal = "{{ count($soal) }}";

            // --- TIMER COUNTDOWN ---
            function updateTimer() {
                const now = Math.floor(Date.now() / 1000);
                const elapsed = now - startTime;
                durasiInput.value = elapsed;

                // Hitung mundur dari batas_waktu yang dikirim dari server
                // Jika ada batas_waktu di server, hitung mundur dari itulah
                // Jika tidak ada batas_waktu, tampilkan elapsed time
                @if($kuis->batas_waktu)
                    const batasWaktu = {{ $kuis->batas_waktu->timestamp ?? $kuis->batas_waktu }};
                    const remaining = batasWaktu - now;
                    if (remaining <= 0) {
                        timerDisplay.textContent = "00:00:00";
                        document.getElementById('formKuis').submit();
                    } else {
                        const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
                        const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
                        const s = String(remaining % 60).padStart(2, '0');
                        timerDisplay.textContent = `${h}:${m}:${s}`;
                    }
                @else
                    const h = String(Math.floor(elapsed / 3600)).padStart(2, '0');
                    const m = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
                    const s = String(elapsed % 60).padStart(2, '0');
                    timerDisplay.textContent = `${h}:${m}:${s}`;
                @endif
            }

            // Auto-submit saat waktu habis
            @if($kuis->batas_waktu)
                setInterval(() => {
                    const now = Math.floor(Date.now() / 1000);
                    const remaining = {{ $kuis->batas_waktu->timestamp ?? $kuis->batas_waktu }} - now;
                    if (remaining <= 0) {
                        document.getElementById('formKuis').submit();
                    }
                }, 1000);
            @endif

            // Panggil timer pertama kali
            updateTimer();
            setInterval(updateTimer, 1000);

            // --- NAVIGASI SOAL ---
            let soalAktif = parseInt(document.getElementById('soalAktif').value) || 0;

            function tampilkanSoal(index) {
                // Sembunyikan semua soal
                document.querySelectorAll('.card.soal').forEach(el => el.style.display = 'none');
                // Tampilkan soal yang aktif
                const targetEl = document.getElementById('soal-' + index);
                if (targetEl) {
                    targetEl.style.display = 'block';
                    nomorSaatIni.innerText = index + 1;
                    // Update progress bar
                    const progressPercent = ((index + 1) / totalSoal) * 100;
                    document.querySelector('.progress-bar').style.width = progressPercent + '%';
                    document.querySelector('.progress-bar').setAttribute('aria-valuenow', index + 1);
                }
            }

            // Event listeners navigasi
            document.querySelectorAll('.btn-prev').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (soalAktif > 0) {
                        soalAktif--;
                        document.getElementById('soalAktif').value = soalAktif;
                        tampilkanSoal(soalAktif);
                    }
                });
            });

            document.querySelectorAll('.btn-next').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (soalAktif < totalSoal - 1) {
                        soalAktif++;
                        document.getElementById('soalAktif').value = soalAktif;
                        tampilkanSoal(soalAktif);
                    }
                });
            });

            // Pilih soal pertama saat load
            tampilkanSoal(soalAktif);

            // --- TANDA Bintang (FLAG) SOAL ---
            document.querySelectorAll('.pilihan-label').forEach(label => {
                label.style.cursor = 'pointer';
                label.style.userSelect = 'none';
                
                // Cek apakah soal ini sudah diflag (dimodifikasi via localStorage atau hidden input)
                const flagInput = label.closest('.card').querySelector('input[type="hidden"][name="flag_soal"]');
                if (flagInput && flagInput.value === '1') {
                    label.style.borderColor = 'var(--gold)';
                    label.style.background = '#FFF9C4';
                    label.style.boxShadow = '0 0 0 2px var(--gold)';
                }
            });

            // Fungsi flag soal (simpan ke hidden input)
            window.flagSoal = function(cardIndex) {
                const card = document.getElementById('soal-' + cardIndex);
                const flagInput = card ? card.querySelector('input[type="hidden"][name="flag_soal"]') : null;
                
                if (flagInput) {
                    const currentlyFlagged = flagInput.value === '1';
                    flagInput.value = currentlyFlagged ? '0' : '1';
                    
                    const labels = card.querySelectorAll('.pilihan-label');
                    if (currentlyFlagged) {
                        // Hapus flag
                        flagInput.value = '0';
                        labels.forEach(l => {
                            l.style.borderColor = 'var(--line)';
                            l.style.background = 'transparent';
                        });
                    } else {
                        // Set flag
                        flagInput.value = '1';
                        labels.forEach(l => {
                            l.style.borderColor = 'var(--gold)';
                            l.style.background = '#FFF9C4';
                            l.style.boxShadow = '0 0 0 2px var(--gold)';
                        });
                    }
                }
            }
        </script>
    @endpush
@endsection