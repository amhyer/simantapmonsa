@extends('layouts.app')

@section('title', 'Mata Pelajaran - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Mata Pelajaran')
@section('page_subtitle', 'Kelola daftar mata pelajaran ' . $jenjangSekolah)

@section('content')
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h3><i class="fas fa-book" style="margin-right:8px;color:var(--navy)"></i>Mata Pelajaran {{ $jenjangSekolah }}</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('admin.mapel.create') }}" class="btn btn-sm" style="background:var(--navy);color:#fff">
                    <i class="fas fa-plus"></i> Tambah Manual
                </a>
                <form action="{{ route('admin.mapel.seed') }}" method="POST" style="display:inline" onsubmit="return confirm('Tambahkan mata pelajaran default {{ $jenjangSekolah }}?')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-gold">
                        <i class="fas fa-list"></i> Default {{ $jenjangSekolah }}
                    </button>
                </form>
                <form action="{{ route('admin.mapel.import-dapodik') }}" method="POST" style="display:inline" onsubmit="return confirm('Import mata pelajaran {{ $jenjangSekolah }} dari Dapodik?')">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="background:#0f766e;color:#fff">
                        <i class="fas fa-cloud-download-alt"></i> Import Dapodik
                    </button>
                </form>
                <form action="{{ route('admin.mapel.import-lokal') }}" method="POST" style="display:inline" onsubmit="return confirm('Import dari data lokal?')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-ghost">
                        <i class="fas fa-database"></i> Import Lokal
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
                <div style="flex:1;min-width:200px">
                    <form method="GET" style="display:flex;gap:8px">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau kode..." class="form-control" style="flex:1">
                        <button type="submit" class="btn btn-sm btn-ghost"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <select onchange="window.location.href='{{ route('admin.mapel.index') }}?status='+this.value+'&search={{ request('search') }}'" class="form-control" style="width:auto;min-width:120px">
                    <option value="">Semua Status</option>
                    <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>

            <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
                <div style="padding:10px 16px;background:var(--navy);color:#fff;border-radius:8px;font-size:13px">
                    <i class="fas fa-book"></i> Total: <b>{{ $total }}</b>
                </div>
                <div style="padding:10px 16px;background:var(--ok);color:#fff;border-radius:8px;font-size:13px">
                    <i class="fas fa-check-circle"></i> Aktif: <b>{{ $aktifCount }}</b>
                </div>
            </div>

            @if($mapel->count())
                <form id="bulkForm">
                    <div id="bulkActions" style="display:none;margin-bottom:12px;padding:10px 16px;background:#FEF3E2;border:1px solid #F59E0B;border-radius:8px;display:none;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                        <span style="font-size:13px;font-weight:600;color:#B54708">
                            <i class="fas fa-check-double"></i> <span id="selectedCount">0</span> item dipilih
                        </span>
                        <div style="display:flex;gap:8px">
                            <button type="button" onclick="deleteSelected()" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i> Hapus Terpilih
                            </button>
                            <button type="button" onclick="clearSelection()" class="btn btn-sm btn-ghost">Batal</button>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:40px">
                                        <input type="checkbox" id="checkAll" onchange="toggleAll(this)" title="Centang semua">
                                    </th>
                                    <th>No</th>
                                    <th>Nama Mata Pelajaran</th>
                                    <th>Kode</th>
                                    <th style="width:80px">Status</th>
                                    <th style="width:100px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mapel as $i => $m)
                                    <tr>
                                        <td><input type="checkbox" name="ids[]" value="{{ $m->id }}" class="row-check" onchange="updateBulk()"></td>
                                        <td>{{ ($mapel->currentPage() - 1) * $mapel->perPage() + $i + 1 }}</td>
                                        <td style="font-weight:600">{{ $m->nama }}</td>
                                        <td><span class="tag tag-primary">{{ $m->kode ?: '-' }}</span></td>
                                        <td>
                                            @if($m->aktif)
                                                <span class="tag tag-ok">Aktif</span>
                                            @else
                                                <span class="tag tag-bad">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:6px">
                                                <a href="{{ route('admin.mapel.edit', $m) }}" class="btn btn-sm btn-ghost" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('admin.mapel.destroy', $m) }}" method="POST" onsubmit="return confirm('Hapus mata pelajaran ini?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>

                <div style="margin-top:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <div style="font-size:13px;color:var(--muted)">
                            Menampilkan {{ $mapel->firstItem() }}-{{ $mapel->lastItem() }} dari {{ $mapel->total() }} data
                        </div>
                        <form action="{{ route('admin.mapel.destroy-all') }}" method="POST" onsubmit="return confirm('YAKIN HAPUS SEMUA mata pelajaran {{ $jenjangSekolah }}? Tindakan ini tidak dapat dibatalkan.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash-alt"></i> Hapus Semua
                            </button>
                        </form>
                    </div>
                    <div>{{ $mapel->links() }}</div>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon"><i class="fas fa-book"></i></div>
                    <h4>Belum ada mata pelajaran {{ $jenjangSekolah }}</h4>
                    <p>Klik "Default {{ $jenjangSekolah }}" untuk menambahkan mata pelajaran bawaan, atau "Import Dapodik" untuk ambil dari Dapodik.</p>
                </div>
            @endif
        </div>
    </div>

    <form id="deleteSelectedForm" method="POST" action="{{ route('admin.mapel.destroy-selected') }}" style="display:none">
        @csrf @method('DELETE')
        <input type="hidden" name="ids" id="deleteIds">
    </form>

    @push('scripts')
    <script>
        function toggleAll(el) {
            document.querySelectorAll('.row-check').forEach(function(cb) {
                cb.checked = el.checked;
            });
            updateBulk();
        }

        function updateBulk() {
            var checked = document.querySelectorAll('.row-check:checked');
            var count = checked.length;
            document.getElementById('selectedCount').textContent = count;
            var bar = document.getElementById('bulkActions');
            bar.style.display = count > 0 ? 'flex' : 'none';
            document.getElementById('checkAll').checked = count > 0 && count === document.querySelectorAll('.row-check').length;
        }

        function clearSelection() {
            document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = false; });
            document.getElementById('checkAll').checked = false;
            updateBulk();
        }

        function deleteSelected() {
            var checked = document.querySelectorAll('.row-check:checked');
            if (checked.length === 0) return;
            if (!confirm('Hapus ' + checked.length + ' mata pelajaran yang dipilih?')) return;

            var ids = [];
            checked.forEach(function(cb) { ids.push(cb.value); });
            document.getElementById('deleteIds').value = ids.join(',');
            document.getElementById('deleteSelectedForm').submit();
        }
    </script>
    @endpush
@endsection
