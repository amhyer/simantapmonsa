{{-- Forum Diskusi & Pengumuman Sekolah --}}
@php
    $forumSekolah = \App\Models\SekolahSettings::first();
    $forumPosts = is_array(($forumSekolah?->pengaturan ?? [])['forum_pengumuman'] ?? null)
        ? $forumSekolah->pengaturan['forum_pengumuman']
        : [];
@endphp
<div class="card" style="margin-top:20px">
    <div class="card-header">
        <h3><i class="fas fa-comments" style="margin-right:8px;color:var(--navy)"></i>Forum Diskusi &amp; Pengumuman Sekolah</h3>
        <span class="tag tag-mut">{{ count($forumPosts) }} catatan</span>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.forum.simpan') }}" style="display:grid;gap:10px;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid #E4E7EC">
            @csrf
            <input type="text" name="judul" class="form-control" maxlength="120" required placeholder="Judul pengumuman atau topik diskusi (mis. Batas akhir input nilai PAS)...">
            <textarea name="pesan" rows="2" class="form-control" maxlength="600" required placeholder="Tulis pesan untuk operator dan tim kurikulum sekolah..."></textarea>
            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-paper-plane"></i> Publikasikan Pengumuman
                </button>
            </div>
        </form>

        @forelse($forumPosts as $post)
            <div style="padding:12px 14px;border:1px solid #E4E7EC;border-radius:10px;margin-bottom:10px;background:#F8FAFC;display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <div style="font-weight:700;font-size:13.5px;color:var(--text)">{{ $post['judul'] ?? '-' }}</div>
                    <div style="font-size:13px;color:#334155;margin:4px 0;line-height:1.5">{{ $post['pesan'] ?? '' }}</div>
                    <div style="font-size:11.5px;color:var(--muted)">Oleh <strong>{{ $post['penulis'] ?? 'Operator' }}</strong> &bull; {{ $post['created_at'] ?? '' }}</div>
                </div>
                <form method="POST" action="{{ route('admin.forum.hapus', $post['id'] ?? '0') }}" onsubmit="return confirm('Hapus pengumuman ini?')" style="margin:0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--bad)" title="Hapus"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        @empty
            <div style="text-align:center;padding:18px;color:var(--muted);font-size:13px">
                Belum ada pengumuman atau catatan diskusi internal sekolah.
            </div>
        @endforelse
    </div>
</div>
