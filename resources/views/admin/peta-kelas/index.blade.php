@extends('layouts.app')

@section('title', 'Peta Kelas - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Peta Kelas')
@section('page_subtitle', 'Pemetaan guru dan siswa per kelas — seret siswa untuk pindah kelas')

@section('content')
    @if(session('success'))
        <div class="toast toast-success" style="position:static;margin-bottom:16px">✅ {{ session('success') }}</div>
    @endif

    @php
        $syncedSiswa = \App\Models\Siswa::whereNotNull('dapodik_id')->count();
        $totalSiswa = \App\Models\Siswa::count();
    @endphp

    @if($syncedSiswa > 0)
        <div style="display:flex;align-items:center;gap:8px;padding:10px 16px;background:#EEF2F9;border-radius:8px;border-left:3px solid var(--navy);margin-bottom:16px;font-size:13px">
            <i class="fas fa-link" style="color:var(--navy)"></i>
            <span><b>{{ $syncedSiswa }}</b> dari {{ $totalSiswa }} siswa bersumber dari <b>Dapodik Bridge</b></span>
        </div>
    @endif

    <div class="grid grid-3" style="margin-bottom:20px">
        <div class="stat-card primary"><div class="stat-value">{{ $guru->count() }}</div><div class="stat-label">Total Guru</div></div>
        <div class="stat-card gold"><div class="stat-value">{{ $siswa->count() }}</div><div class="stat-label">Total Siswa</div></div>
        <div class="stat-card ok"><div class="stat-value">{{ $pengaturanGuru->pluck('kelas')->unique()->count() }}</div><div class="stat-label">Total Kelas</div></div>
    </div>

    <div class="drag-drop-layout">
        <div class="siswa-pool" id="siswaPool">
            <div class="pool-header">
                <h3>📋 Belum Ditugaskan</h3>
                <span class="badge" id="countPool">{{ $tidakDitugaskan->count() }}</span>
            </div>
            <input type="text" id="searchPool" placeholder="Cari siswa..." oninput="filterPool(this.value)">
            <div class="siswa-list" id="poolList">
                @forelse($tidakDitugaskan as $s)
                    <div class="siswa-card" draggable="true" data-id="{{ $s->id }}" data-nama="{{ $s->nama_peserta_didik }}" data-nis="{{ $s->nis }}">
                        <div class="siswa-info">
                            <span class="siswa-nama">{{ $s->nama_peserta_didik }}</span>
                            <span class="siswa-nis">NIS: {{ $s->nis }}</span>
                        </div>
                        <button class="btn-icon" onclick="openModal({{ $s->id }}, '{{ addslashes($s->nama_peserta_didik) }}')">📦</button>
                    </div>
                @empty
                    <div class="empty-msg">✅ Semua siswa sudah ditugaskan</div>
                @endforelse
            </div>
        </div>

        <div class="guru-area">
            @foreach($guruStats as $gs)
                <div class="guru-card">
                    <div class="guru-header">
                        <div>
                            <h4>{{ $gs['guru']->nama_lengkap }}</h4>
                            <small>{{ $gs['kelas_list']->implode(', ') ?: 'Belum ada kelas' }}</small>
                        </div>
                        <span class="badge">{{ $gs['jumlah_siswa'] }} siswa</span>
                    </div>
                    <div class="siswa-list drop-zone" data-guru="{{ $gs['guru']->id }}" data-guru-nama="{{ $gs['guru']->nama_lengkap }}">
                        @foreach($siswaPerGuru->get($gs['guru']->id, collect()) as $s)
                            <div class="siswa-card" draggable="true" data-id="{{ $s->id }}" data-nama="{{ $s->nama_peserta_didik }}" data-nis="{{ $s->nis }}" data-kelas="{{ $s->kelas }}">
                                <div class="siswa-info">
                                    <span class="siswa-nama">{{ $s->nama_peserta_didik }}</span>
                                    <span class="siswa-nis">NIS: {{ $s->nis }}</span>
                                    <span class="siswa-kelas">{{ $s->kelas }}</span>
                                </div>
                                <button class="btn-icon btn-danger" onclick="keluarkan({{ $s->id }}, '{{ addslashes($s->nama_peserta_didik) }}')">✕</button>
                            </div>
                        @endforeach
                        <div class="drop-hint">Seret siswa ke sini</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="modal-overlay" id="modal" style="display:none">
        <div class="modal">
            <div class="modal-header">
                <h3>Pindahkan Siswa</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="modalNama" class="modal-nama"></p>
                <input type="hidden" id="modalSiswaId">
                <div class="form-group">
                    <label>Guru</label>
                    <select id="modalGuru" onchange="loadKelas()">
                        <option value="">Pilih Guru</option>
                        @foreach($guru as $g)
                            <option value="{{ $g->id }}">{{ $g->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Kelas</label>
                    <select id="modalKelas"><option value="">Pilih Kelas</option></select>
                </div>
                <div class="form-group" id="newKelasGroup" style="display:none">
                    <label>Kelas Baru</label>
                    <input type="text" id="modalKelasBaru" placeholder="Contoh: 7A">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button class="btn btn-primary" onclick="prosesPindah()">Pindahkan</button>
            </div>
        </div>
    </div>

    <style>
    .drag-drop-layout {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 16px;
    }
    .siswa-pool {
        background: var(--card);
        border-radius: 10px;
        border: 1px solid var(--line);
        padding: 12px;
        max-height: calc(100vh - 220px);
        overflow-y: auto;
        position: sticky;
        top: 80px;
    }
    .pool-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    .pool-header h3 { margin:0; font-size:13px; }
    #searchPool { width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:8px; font-size:13px; margin-bottom:10px; }
    .guru-area { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:14px; }
    .guru-card { background:var(--card); border-radius:10px; border:1px solid var(--line); overflow:hidden; }
    .guru-header {
        padding:10px 14px; background:linear-gradient(135deg,#1F3864,#2d5a9e); color:#fff;
        display:flex; justify-content:space-between; align-items:center;
    }
    .guru-header h4 { margin:0; font-size:13px; }
    .guru-header small { font-size:11px; opacity:.8; }
    .guru-header .badge { background:rgba(255,255,255,.2); padding:2px 8px; border-radius:10px; font-size:11px; }
    .siswa-list { padding:6px; min-height:60px; max-height:260px; overflow-y:auto; }
    .siswa-card {
        display:flex; justify-content:space-between; align-items:center;
        padding:7px 10px; margin:3px 0; background:var(--bg-secondary,#f8f9fa);
        border-radius:7px; cursor:grab; border:1px solid transparent; transition:all .15s;
    }
    .siswa-card:active { cursor:grabbing; transform:scale(1.02); }
    .siswa-card.dragging { opacity:.4; border-color:var(--gold); }
    .siswa-card:hover { border-color:var(--gold); }
    .siswa-info { display:flex; flex-direction:column; gap:1px; min-width:0; }
    .siswa-nama { font-weight:600; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .siswa-nis { font-size:10px; color:var(--muted); }
    .siswa-kelas {
        font-size:9px; color:var(--gold); background:rgba(184,134,11,.1);
        padding:1px 5px; border-radius:3px; width:fit-content;
    }
    .btn-icon { background:none; border:none; cursor:pointer; font-size:13px; padding:2px 4px; opacity:.5; transition:all .15s; }
    .btn-icon:hover { opacity:1; }
    .btn-danger:hover { color:#B42318; }
    .drop-zone { border:2px dashed transparent; transition:all .15s; }
    .drop-zone.drag-over { border-color:var(--gold); background:rgba(184,134,11,.03); }
    .drop-hint { text-align:center; color:#bbb; font-size:11px; padding:12px; }
    .empty-msg { text-align:center; padding:20px; color:var(--muted); font-size:12px; }
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:1000; }
    .modal { background:#fff; border-radius:12px; width:100%; max-width:380px; box-shadow:0 20px 60px rgba(0,0,0,.3); }
    .modal-header { padding:14px 18px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
    .modal-header h3 { margin:0; font-size:15px; }
    .modal-close { background:none; border:none; font-size:22px; cursor:pointer; color:#888; }
    .modal-body { padding:18px; }
    .modal-nama { font-weight:600; color:#1F3864; margin-bottom:14px; font-size:14px; }
    .modal-footer { padding:14px 18px; border-top:1px solid #eee; display:flex; justify-content:flex-end; gap:8px; }
    .form-group { margin-bottom:12px; }
    .form-group label { display:block; margin-bottom:5px; font-weight:500; font-size:12px; color:#555; }
    .form-group input, .form-group select { width:100%; padding:9px 10px; border:1px solid #ddd; border-radius:7px; font-size:13px; }
    .btn { padding:9px 18px; border-radius:7px; font-weight:500; cursor:pointer; border:none; font-size:13px; }
    .btn-primary { background:#B8860B; color:#fff; }
    .btn-primary:hover { background:#9a7209; }
    .btn-secondary { background:#e2e8f0; color:#555; }
    @media(max-width:768px) {
        .drag-drop-layout { grid-template-columns:1fr; }
        .siswa-pool { position:static; max-height:none; }
        .guru-area { grid-template-columns:1fr; }
    }
    </style>

    <script>
    const CSRF = '{{ csrf_token() }}';
    let dragged = null;

    document.querySelectorAll('.siswa-card[draggable="true"]').forEach(c => {
        c.addEventListener('dragstart', function(e) { e.stopPropagation(); dragged = this; this.classList.add('dragging'); e.dataTransfer.effectAllowed='move'; e.dataTransfer.setData('text/plain', this.dataset.id); });
        c.addEventListener('dragend', function(e) { e.stopPropagation(); this.classList.remove('dragging'); document.querySelectorAll('.drop-zone').forEach(z=>z.classList.remove('drag-over')); });
        c.addEventListener('mousedown', e => e.stopPropagation());
    });

    document.querySelectorAll('.drop-zone').forEach(z => {
        z.addEventListener('dragover', e => { e.preventDefault(); e.stopPropagation(); e.dataTransfer.dropEffect='move'; z.classList.add('drag-over'); });
        z.addEventListener('dragleave', e => { e.stopPropagation(); z.classList.remove('drag-over'); });
        z.addEventListener('drop', e => {
            e.preventDefault(); e.stopPropagation(); z.classList.remove('drag-over');
            const id = e.dataTransfer.getData('text/plain');
            const nama = dragged?.dataset?.nama||'';
            const guruId = z.dataset.guru;
            const guruNama = z.dataset.guruNama;
            if(id && guruId) showModal(id, nama, parseInt(guruId), guruNama);
        });
        z.addEventListener('mousedown', e => e.stopPropagation());
    });

    const pool = document.getElementById('poolList');
    pool.addEventListener('dragover', e => { e.preventDefault(); e.stopPropagation(); });
    pool.addEventListener('drop', e => {
        e.preventDefault(); e.stopPropagation();
        const id = e.dataTransfer.getData('text/plain');
        const nama = dragged?.dataset?.nama||'';
        if(id) keluarkan(parseInt(id), nama);
    });
    pool.addEventListener('mousedown', e => e.stopPropagation());

    function showModal(id, nama, guruId, guruNama) {
        document.getElementById('modalSiswaId').value = id;
        document.getElementById('modalNama').textContent = 'Pindahkan ' + nama + ' ke ' + guruNama;
        document.getElementById('modalGuru').value = guruId;
        loadKelas(() => { document.getElementById('modal').style.display='flex'; });
    }

    function openModal(id, nama) {
        document.getElementById('modalSiswaId').value = id;
        document.getElementById('modalNama').textContent = 'Pindahkan ' + nama;
        document.getElementById('modalGuru').value = '';
        document.getElementById('modalKelas').innerHTML = '<option value="">Pilih Kelas</option>';
        document.getElementById('modal').style.display = 'flex';
    }

    function closeModal() { document.getElementById('modal').style.display='none'; }

    function loadKelas(cb) {
        const gid = document.getElementById('modalGuru').value;
        const sel = document.getElementById('modalKelas');
        const ng = document.getElementById('newKelasGroup');
        if(!gid) { sel.innerHTML='<option value="">Pilih Kelas</option>'; ng.style.display='none'; return; }
        fetch('{{ route("admin.peta-kelas.kelas-options") }}?guru_id='+gid, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(d=>{
            let h='<option value="">Pilih Kelas</option>';
            d.kelas.forEach(k => h+='<option value="'+k+'">'+k+'</option>');
            h+='<option value="__new__">+ Kelas Baru</option>';
            sel.innerHTML=h;
            sel.onchange=function(){ ng.style.display=this.value==='__new__'?'block':'none'; };
            ng.style.display='none';
            if(cb)cb();
        });
    }

    function prosesPindah() {
        const sid = document.getElementById('modalSiswaId').value;
        const gid = document.getElementById('modalGuru').value;
        let kelas = document.getElementById('modalKelas').value;
        if(kelas==='__new__') kelas = document.getElementById('modalKelasBaru').value.trim();
        if(!gid||!kelas) return alert('Lengkapi semua field');
        fetch('{{ route("admin.peta-kelas.pindah") }}', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},
            body:JSON.stringify({siswa_id:sid,guru_id:gid,kelas:kelas})
        }).then(r=>r.json()).then(d=>{
            if(d.success){ toast(d.message,'success'); setTimeout(()=>location.reload(),600); }
            else toast(d.message||'Gagal','error');
        }).catch(()=>toast('Error jaringan','error'));
    }

    function keluarkan(id, nama) {
        if(!confirm('Keluarkan '+nama+' dari kelas?')) return;
        fetch('{{ route("admin.peta-kelas.keluarkan") }}', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},
            body:JSON.stringify({siswa_id:id})
        }).then(r=>r.json()).then(d=>{
            if(d.success){ toast(d.message,'success'); setTimeout(()=>location.reload(),600); }
            else toast(d.message||'Gagal','error');
        }).catch(()=>toast('Error jaringan','error'));
    }

    function filterPool(v) {
        const cards = document.querySelectorAll('#poolList .siswa-card');
        const lv = v.toLowerCase(); let c=0;
        cards.forEach(card => {
            const ok = card.dataset.nama.toLowerCase().includes(lv)||card.dataset.nis.includes(lv);
            card.style.display=ok?'':'none'; if(ok)c++;
        });
        document.getElementById('countPool').textContent=c;
    }

    function toast(msg, type) {
        const t=document.createElement('div');
        t.className='toast toast-'+type;
        t.textContent=msg;
        t.style.cssText='position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;z-index:9999;font-size:13px;animation:slideUp .3s';
        if(type==='success')t.style.background='#22c55e';
        if(type==='error')t.style.background='#ef4444';
        document.body.appendChild(t);
        setTimeout(()=>t.remove(),3000);
    }
    </script>
@endsection
