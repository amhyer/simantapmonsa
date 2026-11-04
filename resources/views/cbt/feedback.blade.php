@if(session('success'))
    <div class="note note-ok" style="margin-bottom:16px;display:flex;align-items:center;gap:10px">
        <i class="fas fa-check-circle" style="color:var(--ok);font-size:16px"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div class="note note-bad" style="margin-bottom:16px;display:flex;align-items:center;gap:10px">
        <i class="fas fa-exclamation-triangle" style="color:var(--bad);font-size:16px"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if($errors->any())
    <div class="note note-bad" style="margin-bottom:16px">
        <div style="font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <i class="fas fa-exclamation-circle" style="color:var(--bad)"></i> Periksa kembali isian berikut:
        </div>
        <ul style="margin:0;padding-left:20px;font-size:13px">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
