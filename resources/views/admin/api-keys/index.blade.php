@extends('layouts.app')

@section('title', 'API Keys & Dapodik - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'API Keys & Dapodik Bridge')
@section('page_subtitle', 'Kelola akses API untuk aplikasi eksternal')

@section('content')
    @if(session('new_key'))
        <div style="background:#FFFBEB;border:1px solid #F59E0B;border-radius:10px;padding:16px;margin-bottom:16px">
            <div style="font-weight:700;color:#92400E;margin-bottom:8px">⚠️ Simpan API Key ini! Anda tidak akan melihatnya lagi.</div>
            <div style="padding:10px 14px;background:#fff;border-radius:8px;font-family:monospace;font-size:14px;word-break:break-all;border:1px solid #E5E7EB">{{ session('new_key') }}</div>
            <button class="btn btn-sm" style="margin-top:8px;background:#F59E0B;color:#fff" onclick="salin('{{ session('new_key') }}')">📋 Salin</button>
        </div>
    @endif

    @if(session('success'))
        <div class="toast toast-success" style="position:static;margin-bottom:16px">✅ {{ session('success') }}</div>
    @endif

    <div class="grid grid-4" style="margin-bottom:20px">
        <div class="stat-card primary"><div class="stat-value">{{ $keys->count() }}</div><div class="stat-label">Total API Keys</div></div>
        <div class="stat-card gold"><div class="stat-value">{{ $keys->where('active', true)->count() }}</div><div class="stat-label">Aktif</div></div>
        <div class="stat-card ok"><div class="stat-value">{{ $siswaDapodik }}</div><div class="stat-label">Siswa Dapodik</div></div>
        <div class="stat-card"><div class="stat-value">{{ $logs->count() }}</div><div class="stat-label">Riwayat Import</div></div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><h3>📖 Cara Import dari Dapodik</h3></div>
        <div class="card-body">
            <div style="display:grid;gap:14px">
                @foreach(['Unduh Aplikasi SIMANTAP Dapodik Bridge dari tombol di bawah.' => '📥', 'Jalankan EXE di laptop Dapodik, isi pengaturan database.' => '💻', 'Buat API Key baru di bawah, masukkan ke aplikasi.' => '🔑', 'Klik "Muat Data" lalu "Kirim ke SIMANTAP".' => '🚀'] as $text => $icon)
                    <div style="display:flex;gap:12px;align-items:flex-start">
                        <div style="width:32px;height:32px;background:var(--gold);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">{{ $icon }}</div>
                        <div style="font-size:13.5px;line-height:1.6">{{ $text }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-bottom:16px">
        <div class="card">
            <div class="card-header">
                <h3>🔑 API Keys</h3>
                <button class="btn btn-sm" onclick="document.getElementById('modalForm').style.display='flex'">➕ Buat Key</button>
            </div>
            <div class="card-body tight">
                @if($keys->count())
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse">
                            <thead>
                                <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                    <th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#667085">Nama</th>
                                    <th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#667085">Key</th>
                                    <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#667085">Status</th>
                                    <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#667085">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($keys as $key)
                                    <tr style="border-bottom:1px solid #F2F4F7">
                                        <td style="padding:10px 14px"><b>{{ $key->name }}</b><br><small style="color:#888">{{ $key->user->nama_lengkap ?? '-' }}</small></td>
                                        <td style="padding:10px 14px"><code style="background:#F2F4F7;padding:2px 8px;border-radius:6px;font-size:11px">{{ substr($key->key, 0, 16) }}...</code></td>
                                        <td style="padding:10px 14px;text-align:center">
                                            <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;{{ $key->active ? 'background:#D1FAE5;color:#065F46' : 'background:#F3F4F6;color:#6B7280' }}">{{ $key->active ? 'Aktif' : 'Nonaktif' }}</span>
                                        </td>
                                        <td style="padding:10px 14px;text-align:center">
                                            <form action="{{ route('admin.api-keys.toggle', $key) }}" method="POST" style="display:inline">@csrf<button class="btn btn-ghost btn-sm" title="Toggle">{{ $key->active ? '⏸' : '▶️' }}</button></form>
                                            <form action="{{ route('admin.api-keys.destroy', $key) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" style="color:#B42318">🗑</button></form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="text-align:center;padding:30px;color:#888">
                        <div style="font-size:32px;margin-bottom:8px">🔑</div>
                        <b>Belum ada API Key</b>
                        <p style="font-size:13px;margin-top:4px">Buat API Key untuk menghubungkan Dapodik Bridge.</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3>📋 Riwayat Import</h3></div>
            <div class="card-body tight">
                @if($logs->count())
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse">
                            <thead>
                                <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                    <th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#667085">Waktu</th>
                                    <th style="padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;color:#667085">Sekolah</th>
                                    <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#667085">Total</th>
                                    <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#667085">OK</th>
                                    <th style="padding:10px 14px;text-align:center;font-size:11px;text-transform:uppercase;color:#667085">Gagal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    <tr style="border-bottom:1px solid #F2F4F7">
                                        <td style="padding:10px 14px;font-size:12px">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                        <td style="padding:10px 14px">{{ $log->nama_sekolah ?? '-' }}</td>
                                        <td style="padding:10px 14px;text-align:center">{{ $log->total_data }}</td>
                                        <td style="padding:10px 14px;text-align:center"><span style="color:#059669;font-weight:600">{{ $log->berhasil }}</span></td>
                                        <td style="padding:10px 14px;text-align:center"><span style="color:{{ $log->gagal > 0 ? '#B42318' : '#888' }};font-weight:600">{{ $log->gagal }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="text-align:center;padding:30px;color:#888">
                        <div style="font-size:32px;margin-bottom:8px">📭</div>
                        <b>Belum ada riwayat import</b>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalForm" style="display:none" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal">
            <div class="modal-header"><h3>🔑 Buat API Key</h3><button class="modal-close" onclick="document.getElementById('modalForm').style.display='none'">&times;</button></div>
            <form action="{{ route('admin.api-keys.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Aplikasi</label>
                        <input type="text" name="name" required placeholder="Contoh: Dapodik Bridge" value="Dapodik Bridge" style="width:100%;padding:10px;border:1px solid #E4E7EC;border-radius:8px">
                    </div>
                    <div style="background:#FFFBEB;border:1px solid #F59E0B;border-radius:8px;padding:10px;font-size:13px;color:#92400E">⚠️ API Key hanya ditampilkan sekali setelah dibuat.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalForm').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-primary">🔑 Buat API Key</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function salin(text) {
        navigator.clipboard.writeText(text).then(() => {
            const t = document.createElement('div');
            t.className = 'toast toast-success';
            t.textContent = '✅ API Key tersalin!';
            t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999';
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 2500);
        });
    }
    </script>
@endsection
