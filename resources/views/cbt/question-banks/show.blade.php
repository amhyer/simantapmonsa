@extends('layouts.app')

@section('title', 'Bank Soal: ' . $bank->nama_bank . ' - SIMANTAP')

@section('sidebar')
    @include('guru.partials.sidebar')
@endsection

@section('page_title', 'Bank Soal: ' . $bank->nama_bank)
@section('page_subtitle', $bank->deskripsi)

@section('content')
<div x-data="bankState()">
    {{-- Bank Info Header --}}
    <div class="grid grid-2" style="margin-bottom:20px">
        <div class="card">
            <div class="card-body" style="display:flex;gap:16px;align-items:center">
                <div style="flex:1">
                    <p style="font-size:12px;color:#667085;margin-bottom:4px">Kode Bank</p>
                    <p style="font-weight:600;font-size:16px">{{ $bank->kode_bank }}</p>
                </div>
                <div style="flex:1">
                    <p style="font-size:12px;color:#667085;margin-bottom:4px">Mata Pelajaran</p>
                    <p style="font-weight:600;font-size:14px">{{ $bank->mataPelajaran->nama ?? '-' }}</p>
                </div>
                <div style="flex:1">
                    <p style="font-size:12px;color:#667085;margin-bottom:4px">Semester</p>
                    <p style="font-weight:600;font-size:14px">{{ $bank->semester->nama ?? '-' }}</p>
                </div>
                <div style="flex:1">
                    <p style="font-size:12px;color:#667085;margin-bottom:4px">Status</p>
                    <span class="tag {{ $bank->aktif ? 'tag-ok' : 'tag-mut' }}">
                        {{ $bank->aktif ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body" style="text-align:center;padding:24px">
                <div style="font-size:36px;font-weight:700;color:var(--violet);margin-bottom:8px">
                    {{ $bank->questions_count ?? 0 }}
                </div>
                <div style="font-size:13px;color:#667085;margin-bottom:16px">Total Soal</div>
                
                @php
                    $difficulties = $bank->questions()->groupBy('tingkat_kesulitan')->pluck(\DB::raw('count(*) as count'), 'tingkat_kesulitan');
                @endphp
                <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;font-size:12px">
                    @foreach(['mudah' => 'Mudah', 'sedang' => 'Sedang', 'sulit' => 'Sulit'] as $key => $label)
                        <span class="tag tag-mut">
                            {{ $label }}: {{ $difficulties->get($key, 0) }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Actions Bar --}}
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
        <a href="{{ route('cbt.question-banks.edit', $bank) }}" class="btn btn-sm" style="background:#7C3AED;color:#fff">
            <i class="fas fa-edit" style="margin-right:4px"></i>Edit Bank
        </a>
        
        <button @click="showAddForm()" class="btn btn-sm" style="background:#12805C;color:#fff">
            <i class="fas fa-plus" style="margin-right:4px"></i>Tambah Soal
        </button>
        
        <a href="{{ route('cbt.sessions.create', ['bank_id' => $bank->id]) }}" class="btn btn-sm" style="background:var(--gold);color:#fff">
            <i class="fas fa-play" style="margin-right:4px"></i>Buat Sesi Ujian
        </a>
        
        <a href="{{ route('cbt.question-banks.index') }}" class="btn btn-sm" style="background:#E4E7EC;color:#101828">
            <i class="fas fa-arrow-left" style="margin-right:4px"></i>Kembali
        </a>
    </div>

    {{-- Inline Add Form --}}
    <template x-if="showForm">
        <div class="card" style="margin-bottom:20px">
            <div class="card-header" style="background:#F9FAFB">
                <h3>Tambah Soal Baru</h3>
            </div>
            <div class="card-body">
                <form @submit.prevent="submitQuestion()">
                    <div class="grid grid-2" style="gap:16px;margin-bottom:16px">
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                                Pertanyaan <span style="color:var(--bad)">*</span>
                            </label>
                            <textarea 
                                x-model="formData.pertanyaan"
                                style="width:100%;min-height:100px;padding:10px;border:1px solid #E4E7EC;border-radius:6px;font-family:inherit;font-size:13px"
                                required
                                placeholder="Ketik pertanyaan di sini..."
                            ></textarea>
                        </div>
                        
                        <div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                <div>
                                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                                        Tipe Soal <span style="color:var(--bad)">*</span>
                                    </label>
                                    <select x-model="formData.tipe_soal" style="width:100%;padding:8px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px" required>
                                        <option value="">-- Pilih --</option>
                                        <option value="pilihan_ganda">Pilihan Ganda</option>
                                        <option value="benar_salah">Benar/Salah</option>
                                        <option value="essay">Essay</option>
                                        <option value="menjodohkan">Menjodohkan</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                                        Poin <span style="color:var(--bad)">*</span>
                                    </label>
                                    <input 
                                        type="number" 
                                        x-model.number="formData.poin"
                                        min="1"
                                        style="width:100%;padding:8px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px"
                                        required
                                        value="1"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                                Tingkat Kesulitan
                            </label>
                            <select x-model="formData.tingkat_kesulitan" style="width:100%;padding:8px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px">
                                <option value="mudah">Mudah</option>
                                <option value="sedang" selected>Sedang</option>
                                <option value="sulit">Sulit</option>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#667085">
                                Media (URL) [Opsional]
                            </label>
                            <input 
                                type="url" 
                                x-model="formData.media_url"
                                style="width:100%;padding:8px;border:1px solid #E4E7EC;border-radius:6px;font-size:13px"
                                placeholder="https://..."
                            />
                        </div>
                    </div>

                    <div style="display:flex;gap:8px">
                        <button type="submit" class="btn btn-sm" style="background:#12805C;color:#fff">
                            <i class="fas fa-check" style="margin-right:4px"></i>Simpan Soal
                        </button>
                        <button @click="hideForm()" type="button" class="btn btn-sm" style="background:#E4E7EC;color:#101828">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    {{-- Questions Table --}}
    <div class="card">
        <div class="card-header">
            <h3>Daftar Soal</h3>
            <span class="tag tag-mut">{{ $bank->questions_count ?? 0 }} soal</span>
        </div>
        <div class="card-body tight">
            @if($bank->questions->isEmpty())
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h4>Belum ada soal</h4>
                    <p>Tambahkan soal ke bank ini untuk membuat ujian.</p>
                </div>
            @else
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tipe</th>
                                <th>Soal</th>
                                <th>Poin</th>
                                <th>Kesulitan</th>
                                <th>Aktif</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bank->questions as $idx => $question)
                                <tr>
                                    <td style="font-weight:600;width:40px">{{ $idx + 1 }}</td>
                                    <td>
                                        <span class="tag tag-mut" style="font-size:11px">
                                            {{ ucfirst(str_replace('_', ' ', $question->tipe_soal)) }}
                                        </span>
                                    </td>
                                    <td style="max-width:300px;font-size:13px">
                                        <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block">
                                            {{ Str::limit($question->pertanyaan, 80) }}
                                        </span>
                                    </td>
                                    <td style="text-align:center;font-weight:600">{{ $question->poin ?? 1 }}</td>
                                    <td>
                                        <span class="tag {{ 
                                            $question->tingkat_kesulitan === 'mudah' ? 'tag-ok' : 
                                            ($question->tingkat_kesulitan === 'sulit' ? 'tag-bad' : 'tag-gold')
                                        }}" style="font-size:11px">
                                            {{ ucfirst($question->tingkat_kesulitan ?? 'sedang') }}
                                        </span>
                                    </td>
                                    <td style="text-align:center">
                                        <input 
                                            type="checkbox" 
                                            @change="toggleQuestionActive($event, {{ $question->id }})"
                                            {{ $question->aktif ? 'checked' : '' }}
                                            style="cursor:pointer"
                                        />
                                    </td>
                                    <td style="text-align:right">
                                        <div style="display:flex;gap:6px;justify-content:flex-end">
                                            {{-- Edit soal belum tersedia: route cbt.questions.edit tidak terdaftar.
                                                 Komentar HTML tetap mengeksekusi route(), jadi gunakan komentar Blade. --}}
                                            <form method="POST" action="{{ route('cbt.questions.destroy', $question) }}" style="display:inline" onsubmit="return confirm('Hapus soal ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm" style="background:#B42318;color:#fff;padding:6px 10px" title="Hapus">
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
            @endif
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function bankState() {
        return {
            showForm: false,
            formData: {
                pertanyaan: '',
                tipe_soal: 'pilihan_ganda',
                poin: 1,
                tingkat_kesulitan: 'sedang',
                media_url: ''
            },
            
            showAddForm() {
                this.showForm = true;
                this.$nextTick(() => {
                    document.querySelector('textarea[placeholder="Ketik pertanyaan di sini..."]')?.focus();
                });
            },
            
            hideForm() {
                this.showForm = false;
                this.formData = {
                    pertanyaan: '',
                    tipe_soal: 'pilihan_ganda',
                    poin: 1,
                    tingkat_kesulitan: 'sedang',
                    media_url: ''
                };
            },
            
            submitQuestion() {
                // In production, send AJAX POST to route('cbt.questions.store', $bank)
                // For now, show a simple alert
                alert('Fitur tambah soal inline akan diimplementasi via AJAX');
                this.hideForm();
            },
            
            toggleQuestionActive(event, questionId) {
                const isActive = event.target.checked;
                // Send PATCH request to update question active status
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("cbt.questions.update", ["question" => ":id"]) }}'.replace(':id', questionId);
                
                const token = document.querySelector('meta[name="csrf-token"]').content;
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'PATCH';
                
                const activeInput = document.createElement('input');
                activeInput.type = 'hidden';
                activeInput.name = 'aktif';
                activeInput.value = isActive ? '1' : '0';
                
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = token;
                
                form.appendChild(methodInput);
                form.appendChild(activeInput);
                form.appendChild(tokenInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    }
</script>
@endpush
