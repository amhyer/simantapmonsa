@extends('layouts.admin-with-tabs')

@section('title', 'Manajemen Bank Soal CBT - Admin')

@section('page_title', 'Bank Soal CBT')
@section('page_subtitle', 'Monitoring dan pengelolaan seluruh bank soal dari semua guru')

@section('header_actions')
    <a href="{{ route('admin.cbt.banks.create') }}" class="btn btn-gold btn-sm">
        <i class="fas fa-plus"></i> Tambah Bank Soal
    </a>
@endsection

@section('content')
{{-- Filter Bar --}}
<div class="filter-bar">
    <form method="GET" action="{{ route('admin.cbt.banks.index') }}" style="display:contents">
        <div class="filter-group">
            <label class="form-label">Filter Guru</label>
            <select name="guru_id" class="form-control">
                <option value="">— Semua Guru —</option>
                @foreach($gurus as $guru)
                    <option value="{{ $guru->id }}" @selected(request('guru_id') == $guru->id)>
                        {{ $guru->nama_lengkap }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-group" style="flex:2">
            <label class="form-label">Pencarian Judul / Mata Pelajaran</label>
            <input type="text" name="q" class="form-control" placeholder="Ketik kata kunci..." value="{{ request('q') }}">
        </div>

        <div style="display:flex;align-items:center;gap:8px">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="{{ route('admin.cbt.banks.index') }}" class="btn btn-secondary">
                <i class="fas fa-times"></i> Reset
            </a>
        </div>
    </form>
</div>

{{-- Bank Soal Table Card --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-database" style="color:var(--navy);margin-right:8px"></i>Daftar Bank Soal Guru ({{ $banks->total() }})</h3>
        <a href="{{ route('admin.cbt.banks.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Buat Bank Soal
        </a>
    </div>

    <div class="card-body tight">
        @if($banks->isEmpty())
            <div class="empty-state">
                <div class="icon">📚</div>
                <h4>Tidak Ada Bank Soal Ditemukan</h4>
                <p>Belum ada bank soal yang dibuat atau kriteria pencarian tidak cocok.</p>
            </div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%">No</th>
                            <th style="width:30%">Judul Bank Soal</th>
                            <th style="width:20%">Mata Pelajaran</th>
                            <th style="width:20%">Guru Pengampu</th>
                            <th style="width:10%">Butir</th>
                            <th style="width:15%;text-align:right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($banks as $index => $bank)
                            <tr>
                                <td>{{ $banks->firstItem() + $index }}</td>
                                <td>
                                    <div style="font-weight:700;font-size:14px;color:var(--text)">
                                        <a href="{{ route('admin.cbt.banks.show', $bank) }}" style="color:var(--navy);text-decoration:none">
                                            {{ $bank->title }}
                                        </a>
                                    </div>
                                    <div style="font-size:11.5px;color:var(--muted);margin-top:2px">
                                        ID: #{{ $bank->id }} • Dibuat {{ $bank->created_at?->format('d/m/Y') ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="tag tag-primary">
                                        <i class="fas fa-book" style="margin-right:4px"></i> {{ $bank->subject }}
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:13px;font-weight:600;color:var(--text)">
                                        {{ $bank->guru->nama_lengkap ?? 'Guru Terhapus' }}
                                    </div>
                                    <div style="font-size:11px;color:var(--muted)">NIP: {{ $bank->guru->nip ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="tag {{ $bank->questions_count > 0 ? 'tag-ok' : 'tag-warn' }}">
                                        {{ $bank->questions_count }} Soal
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;align-items:center;gap:6px">
                                        <a href="{{ route('admin.cbt.banks.show', $bank) }}" class="btn btn-primary btn-sm" title="Lihat Butir Soal">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.cbt.banks.edit', $bank) }}" class="btn btn-secondary btn-sm" title="Ubah Bank">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.cbt.banks.destroy', $bank) }}" onsubmit="return confirm('Hapus bank soal ini?')" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--bad)" title="Hapus Bank">
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

            @if($banks->hasPages())
                <div style="padding:16px 20px;border-top:1px solid var(--line)">
                    {{ $banks->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
