@extends('layouts.app')

@section('title', 'Bobot & Ketuntasan - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Bobot & Ketuntasan')
@section('page_subtitle', 'Pengaturan bobot penilaian dan KKM per kelas')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Bobot Penilaian & KKM</h3>
        </div>
        <div class="card-body tight">
            @if($pengaturanGuru->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Guru</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Kelas</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">KKM</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tugas</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">UH</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">UTS</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">UAS</th>
                                <th style="padding:11px 14px;text-align:right;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pengaturanGuru as $pg)
                                @php
                                    $bobot = ($pg->pengaturan ?? [])['bobot'] ?? [];
                                    $bobotErapot = ($pg->pengaturan ?? [])['bobot_erapor'] ?? [];
                                    $fmtFormatif = isset($bobotErapot['formatif']) ? round($bobotErapot['formatif'] * 100) : 30;
                                    $fmtSumatif = isset($bobotErapot['sumatif']) ? round($bobotErapot['sumatif'] * 100) : 40;
                                    $fmtSumatifAkhir = isset($bobotErapot['sumatif_akhir']) ? round($bobotErapot['sumatif_akhir'] * 100) : 30;
                                @endphp
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <span class="avatar" style="width:28px;height:28px;font-size:11px">{{ $pg->guru ? substr($pg->guru->nama_lengkap, 0, 2) : '?' }}</span>
                                            <span style="font-weight:600">{{ $pg->guru?->nama_lengkap ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC">
                                        <span class="tag tag-primary">{{ $pg->kelas }}</span>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">
                                        <span class="tag {{ ($pg->kkm ?? 0) >= 70 ? 'tag-ok' : 'tag-bad' }}">{{ $pg->kkm ?? '-' }}</span>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $bobot['tugas'] ?? '-' }}%</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $bobot['uh'] ?? '-' }}%</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $bobot['uts'] ?? '-' }}%</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $bobot['uas'] ?? '-' }}%</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:right">
                                        <button onclick="editBobot({{ $pg->id }}, {{ $pg->kkm ?? 70 }}, {{ $bobot['tugas'] ?? 30 }}, {{ $bobot['uh'] ?? 20 }}, {{ $bobot['uts'] ?? 20 }}, {{ $bobot['uas'] ?? 30 }}, {{ $fmtFormatif }}, {{ $fmtSumatif }}, {{ $fmtSumatifAkhir }})" class="btn btn-ghost btn-sm">
                                            <i class="fas fa-edit"></i> Ubah
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty"><div class="icon">⚖️</div><b>Belum ada pengaturan</b><p>Buat pengaturan kelas terlebih dahulu di menu Peta Kelas.</p></div>
            @endif
        </div>
    </div>

    <div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:12px;padding:24px;width:90%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">Ubah Bobot & KKM</h3>
            <form id="bobotForm" method="POST">
                @csrf
                <div style="margin-bottom:14px">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">KKM (0-100) *</label>
                    <input type="number" name="kkm" id="inputKkm" min="0" max="100" required style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Bobot Tugas (%)</label>
                        <input type="number" name="bobot_tugas" id="inputTugas" min="0" max="100" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Bobot UH (%)</label>
                        <input type="number" name="bobot_uh" id="inputUh" min="0" max="100" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Bobot UTS (%)</label>
                        <input type="number" name="bobot_uts" id="inputUts" min="0" max="100" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div>
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Bobot UAS (%)</label>
                        <input type="number" name="bobot_uas" id="inputUas" min="0" max="100" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                </div>
                <div style="border-top:1px solid #E4E7EC;padding-top:14px;margin-bottom:16px">
                    <h4 style="font-size:13px;font-weight:700;margin-bottom:10px">Bobot e-Rapor (Formatif/Sumatif/Sumatif Akhir)</h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                        <div>
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Formatif (%)</label>
                            <input type="number" name="bobot_formatif" id="inputFormatif" min="0" max="100" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        </div>
                        <div>
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Sumatif (%)</label>
                            <input type="number" name="bobot_sumatif" id="inputSumatif" min="0" max="100" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        </div>
                        <div>
                            <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Sumatif Akhir (%)</label>
                            <input type="number" name="bobot_sumatif_akhir" id="inputSumatifAkhir" min="0" max="100" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end">
                    <button type="button" onclick="closeModal()" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn">💾 Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editBobot(id, kkm, tugas, uh, uts, uas, formatif, sumatif, sumatifAkhir) {
            document.getElementById('bobotForm').action = '/admin/bobot/' + id;
            document.getElementById('inputKkm').value = kkm;
            document.getElementById('inputTugas').value = tugas;
            document.getElementById('inputUh').value = uh;
            document.getElementById('inputUts').value = uts;
            document.getElementById('inputUas').value = uas;
            document.getElementById('inputFormatif').value = formatif;
            document.getElementById('inputSumatif').value = sumatif;
            document.getElementById('inputSumatifAkhir').value = sumatifAkhir;
            document.getElementById('editModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
@endsection
