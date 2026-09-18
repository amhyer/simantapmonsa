@extends('layouts.app')

@section('title', '7 Kebiasaan - Orang Tua')

@section('sidebar')
    @include('ortu.partials.sidebar')
@endsection

@section('page_title', '7 Kebiasaan Anak Indonesia Hebat')
@section('page_subtitle', 'Laporan harian dari rumah')

@section('content')
    @if(!$siswa)
        <div class="card">
            <div class="card-body">
                <div class="empty">
                    <div class="icon">👪</div>
                    <b>Belum ada anak terhubung</b>
                    <div>Hubungi guru untuk mendapatkan akses ke data anak Anda.</div>
                </div>
            </div>
        </div>
    @else
        <div class="card" style="margin-bottom:16px">
            <div class="card-body" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
                <span class="avatar avatar-lg">{{ substr($siswa->nama_peserta_didik, 0, 2) }}</span>
                <div style="flex:1;min-width:190px">
                    <div style="font-size:11.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#667085">Laporan pembiasaan</div>
                    <div style="font-size:19px;font-weight:800;letter-spacing:-.4px">{{ $siswa->nama_peserta_didik }}</div>
                    <div style="font-size:13px;color:#667085">{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:11.5px;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#667085">Terisi</div>
                    @php
                        $fields = ['bangun_pagi', 'beribadah', 'berolahraga', 'makan_sehat', 'gemar_belajar', 'bermasyarakat', 'tidur_cepat'];
                        $terisi = 0;
                        if ($kebiasaan) { foreach ($fields as $f) { if ($kebiasaan->$f) $terisi++; } }
                    @endphp
                    <div style="font-size:34px;font-weight:800;letter-spacing:-1.5px;line-height:1.1;color:{{ $terisi == 7 ? '#12805C' : '#1F3864' }}">{{ $terisi }}<span style="font-size:18px;color:#667085">/7</span></div>
                    @if($terisi > 0)
                        <span class="tag {{ $predikat['kelas'] }}">{{ $predikat['label'] }} · {{ number_format($rata, 1) }}</span>
                    @else
                        <span class="tag tag-mut">belum diisi</span>
                    @endif
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
            @if(isset($anakList) && $anakList->count() > 1)
                <div>
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Pilih Anak</label>
                    <select onchange="window.location.href='{{ route('ortu.kebiasaan.index') }}?siswa_id='+this.value+'&tanggal={{ $tanggal }}'" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px;min-width:180px">
                        @foreach($anakList as $a)
                            <option value="{{ $a->id }}" {{ $a->id == $siswa->id ? 'selected' : '' }}>{{ $a->nama_peserta_didik }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px">Tanggal</label>
                <input type="date" value="{{ $tanggal }}" max="{{ date('Y-m-d') }}" onchange="window.location.href='{{ route('ortu.kebiasaan.index') }}?siswa_id={{ $siswa->id }}&tanggal='+this.value" style="padding:8px 12px;border:1px solid #E4E7EC;border-radius:8px">
            </div>
        </div>

        <form action="{{ route('ortu.kebiasaan.store') }}" method="POST">
            @csrf
            <input type="hidden" name="siswa_id" value="{{ $siswa->id }}">
            <input type="hidden" name="tanggal" value="{{ $tanggal }}">

            @php
                $kaihList = [
                    ['bangun_pagi', '🌅', 'Bangun Pagi', 'Bangun pagi sendiri, tanpa dibangunkan berkali-kali.'],
                    ['beribadah', '🕋', 'Beribadah', 'Menjalankan ibadah sesuai agama dan kepercayaannya.'],
                    ['berolahraga', '🏃', 'Berolahraga', 'Bergerak aktif atau berolahraga di rumah.'],
                    ['makan_sehat', '🍎', 'Makan Sehat dan Bergizi', 'Makan teratur dengan menu bergizi seimbang.'],
                    ['gemar_belajar', '📖', 'Gemar Belajar', 'Belajar atau membaca atas kemauan sendiri.'],
                    ['bermasyarakat', '🤝', 'Bermasyarakat', 'Membantu keluarga dan bergaul baik dengan sekitar.'],
                    ['tidur_cepat', '🌙', 'Tidur Cepat', 'Tidur tepat waktu dan tidak begadang.'],
                ];
                $skala = [
                    1 => ['label' => 'Belum', 'color' => '#B42318'],
                    2 => ['label' => 'Kadang', 'color' => '#B54708'],
                    3 => ['label' => 'Sering', 'color' => '#175CD3'],
                    4 => ['label' => 'Selalu', 'color' => '#12805C'],
                ];
            @endphp

            <div style="display:grid;gap:12px;margin-bottom:16px">
                @foreach($kaihList as [$field, $icon, $nama, $deskripsi])
                    @php $nilai = $kebiasaan ? $kebiasaan->$field : null; @endphp
                    <div style="border:1.5px solid #E4E7EC;border-radius:13px;background:#fff;padding:14px 16px;{{ $nilai ? 'border-color:#1F3864;background:#EEF2F9;' : '' }}">
                        <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:11px">
                            <div style="width:38px;height:38px;border-radius:11px;background:#FBF4E4;display:flex;align-items:center;justify-content:center;font-size:19px;flex:0 0 auto">{{ $icon }}</div>
                            <div>
                                <b style="font-size:14.5px;display:block">{{ $nama }}</b>
                                <span style="font-size:12.5px;color:#667085;line-height:1.5">{{ $deskripsi }}</span>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
                            @foreach($skala as $skor => $info)
                                <label style="display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 4px;border-radius:10px;cursor:pointer;font-weight:700;font-size:12px;border:1.8px solid {{ $nilai == $skor ? $info['color'] : '#E4E7EC' }};background:{{ $nilai == $skor ? ($skor == 4 ? '#E6F5F0' : ($skor == 3 ? '#EAF1FE' : ($skor == 2 ? '#FEF3E2' : '#FEE9E7'))) : '#fff' }};color:{{ $nilai == $skor ? $info['color'] : '#667085' }}">
                                    <input type="radio" name="{{ $field }}" value="{{ $skor }}" {{ $nilai == $skor ? 'checked' : '' }} style="display:none">
                                    <span style="width:19px;height:19px;border-radius:50%;border:1.8px solid {{ $nilai == $skor ? $info['color'] : '#D0D5DD' }};display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;background:{{ $nilai == $skor ? $info['color'] : 'transparent' }}">{{ $nilai == $skor ? '✓' : '' }}</span>
                                    {{ $info['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card" style="margin-bottom:16px">
                <div class="card-header">
                    <h3>Catatan untuk guru</h3>
                    <span class="tag tag-mut">opsional</span>
                </div>
                <div class="card-body">
                    <textarea name="catatan" rows="3" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px;font-size:14px" placeholder="Misalnya: hari ini ananda membantu menyapu halaman tanpa diminta.">{{ $kebiasaan ? $kebiasaan->catatan_orang_tua : '' }}</textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-block">💾 Simpan Laporan</button>
        </form>
    @endif
@endsection
