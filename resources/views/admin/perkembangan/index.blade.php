@extends('layouts.app')

@section('title', 'Perkembangan Nilai - SIMANTAP')

@section('sidebar')
    @include('admin.partials.sidebar')
@endsection

@section('page_title', 'Perkembangan Nilai')
@section('page_subtitle', 'Tren rata-rata nilai rapor antar semester')

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Tren per Semester</h3>
            <span class="tag tag-mut">{{ $tren->count() }} semester</span>
        </div>
        <div class="card-body tight">
            @if($tren->count())
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="background:#FAFBFD;border-bottom:1px solid #E4E7EC">
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Semester</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Jumlah Nilai</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Rata-rata</th>
                                <th style="padding:11px 14px;text-align:left;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tren</th>
                                <th style="padding:11px 14px;text-align:center;font-size:11.5px;text-transform:uppercase;letter-spacing:.6px;color:#667085">Tuntas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $maks = max(1, $tren->max('rata')); @endphp
                            @foreach($tren as $t)
                                <tr>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC"><b>{{ $t['label'] }}</b></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $t['jumlah'] }}</td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center"><b>{{ $t['rata'] }}</b></td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;min-width:180px">
                                        <div style="background:#EEF2F6;border-radius:6px;height:16px;overflow:hidden">
                                            <div style="width:{{ round($t['rata'] / $maks * 100) }}%;background:var(--navy);height:16px;border-radius:6px"></div>
                                        </div>
                                    </td>
                                    <td style="padding:11px 14px;border-bottom:1px solid #E4E7EC;text-align:center">{{ $t['tuntas'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="icon">📈</div>
                    <h4>Belum ada data</h4>
                    <p>Tren muncul setelah ada nilai e-rapor di lebih dari satu periode.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
