<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SekolahSettings;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Services\Navigation\SchoolDataAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SekolahController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id() ?? auth()->user()?->tenant_id;
        $query = SekolahSettings::query();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $sekolahSettings = $query->first();

        return view('admin.sekolah.index', compact('sekolahSettings'));
    }

    public function search(Request $request)
    {
        $npsn = trim((string) $request->input('npsn', ''));

        if (strlen($npsn) < 3) {
            return response()->json([
                'success' => false,
                'message' => 'NPSN minimal 3 karakter.',
            ]);
        }

        $tenantId = TenantContext::id() ?? auth()->user()?->tenant_id;
        $query = SekolahSettings::where('npsn', $npsn);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $sekolah = $query->first();

        if (! $sekolah) {
            return response()->json([
                'success' => false,
                'message' => 'Data sekolah dengan NPSN '.$npsn.' tidak ditemukan di database sekolah Anda.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'nama_sekolah' => $sekolah->nama_sekolah,
                'npsn' => $sekolah->npsn,
                'status' => $sekolah->status,
                'jenis_sekolah' => $sekolah->jenis_sekolah,
                'jenjang' => $sekolah->jenjang,
                'akreditasi' => $sekolah->akreditasi,
                'kepala_sekolah' => $sekolah->kepala_sekolah,
                'alamat' => $sekolah->alamat,
                'kecamatan' => $sekolah->kecamatan,
                'kota' => $sekolah->kota,
                'provinsi' => $sekolah->provinsi,
                'kode_pos' => $sekolah->kode_pos,
                'telepon' => $sekolah->telepon,
                'email' => $sekolah->email,
                'website' => $sekolah->website,
            ],
            'message' => 'Data ditemukan dari database sekolah.',
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'nama_sekolah' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:500',
            'kecamatan' => 'nullable|string|max:100',
            'kota' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'kode_pos' => 'nullable|string|max:10',
            'telepon' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'kepala_sekolah' => 'nullable|string|max:255',
            'npsn' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'jenis_sekolah' => 'nullable|string|max:50',
            'jenjang' => 'nullable|string|max:50',
            'akreditasi' => 'nullable|string|max:10',
            'gemini_api_key' => 'nullable|string|max:255',
            'gemini_model' => 'nullable|string|max:80',
            'gemini_daily_quota' => 'nullable|integer|min:1|max:5000',
        ]);

        $geminiKey = trim((string) ($validated['gemini_api_key'] ?? ''));
        $geminiModel = trim((string) ($validated['gemini_model'] ?? ''));
        $geminiQuota = isset($validated['gemini_daily_quota']) ? (int) $validated['gemini_daily_quota'] : null;
        unset($validated['gemini_api_key'], $validated['gemini_model'], $validated['gemini_daily_quota']);

        $tenantId = TenantContext::id() ?? auth()->user()?->tenant_id;
        $query = SekolahSettings::query();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $sekolahSettings = $query->first();

        $pengaturan = is_array($sekolahSettings?->pengaturan) ? $sekolahSettings->pengaturan : [];
        if ($geminiKey !== '') {
            $pengaturan['gemini_api_key_encrypted'] = Crypt::encryptString($geminiKey);
            unset($pengaturan['gemini_api_key']);
        }
        if ($geminiModel !== '') {
            $pengaturan['gemini_model'] = $geminiModel;
        }
        if ($geminiQuota !== null) {
            $pengaturan['gemini_daily_quota'] = $geminiQuota;
        }
        $validated['pengaturan'] = $pengaturan;

        if (! empty($validated['jenjang'])) {
            $validated['jenjang'] = \App\Support\SchoolLevel::normalize($validated['jenjang'], $validated['jenjang']);
        }

        if (! $sekolahSettings) {
            $sekolahSettings = SekolahSettings::create([
                'tenant_id' => $tenantId,
                'guru_id' => auth()->id(),
                ...$validated,
            ]);
        } else {
            $sekolahSettings->update($validated);
        }

        if ($tenantId !== null) {
            $tenantModel = \App\Modules\Tenant\Models\Tenant::query()->find($tenantId);
            if ($tenantModel) {
                $meta = is_array($tenantModel->meta) ? $tenantModel->meta : [];
                if (! empty($validated['jenjang'])) {
                    $meta['jenjang'] = $validated['jenjang'];
                }
                $tenantModel->update([
                    'nama_sekolah' => $validated['nama_sekolah'],
                    'meta' => $meta,
                ]);
            }
            \Illuminate\Support\Facades\Cache::forget('tenant-nama-'.$tenantId);
        }
        \Illuminate\Support\Facades\Cache::forget('root-sekolah-nama');

        SchoolDataAvailabilityService::forget($tenantId !== null ? (int) $tenantId : null);

        return redirect()->route('admin.sekolah.index')
            ->with('success', 'Identitas sekolah berhasil diperbarui.');
    }

    public function reset()
    {
        $tenantId = TenantContext::id() ?? auth()->user()?->tenant_id;
        $query = SekolahSettings::query();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        $query->delete();

        if ($tenantId !== null) {
            \Illuminate\Support\Facades\Cache::forget('tenant-nama-'.$tenantId);
        }
        \Illuminate\Support\Facades\Cache::forget('root-sekolah-nama');

        SchoolDataAvailabilityService::forget($tenantId !== null ? (int) $tenantId : null);

        return redirect()->route('admin.sekolah.index')
            ->with('success', 'Identitas sekolah telah dikosongkan.');
    }
}
