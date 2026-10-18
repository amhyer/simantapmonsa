<style>
.cbt-form label{display:block;margin:12px 0 5px;font-weight:600}.cbt-form input:not([type=radio]),.cbt-form select,.cbt-form textarea{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}.cbt-form{max-width:900px}.cbt-actions{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0}.cbt-note{padding:12px;border:1px solid #cbd5e1;border-radius:8px;margin:12px 0}.cbt-list{width:100%;border-collapse:collapse}.cbt-list td,.cbt-list th{text-align:left;padding:12px;border-bottom:1px solid #e2e8f0}.cbt-option{display:flex;gap:10px;padding:12px;border:1px solid #e2e8f0;border-radius:8px;margin:6px 0}.cbt-option input{width:auto}.cbt-timer{position:sticky;top:0;z-index:10;background:#172554;color:#fff;padding:16px;border-radius:8px}.cbt-form button{cursor:pointer}
</style>
@if(session('success'))<p class="cbt-note" role="status">{{ session('success') }}</p>@endif
@if($errors->any())
<div class="cbt-note" role="alert"><strong>Periksa isian berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
