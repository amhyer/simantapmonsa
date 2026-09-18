<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\DapodikImportLog;
use App\Models\Siswa;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index()
    {
        $keys = ApiKey::with('user')->orderBy('created_at', 'desc')->get();
        $logs = DapodikImportLog::with('user')->orderBy('created_at', 'desc')->limit(20)->get();
        $siswaDapodik = Siswa::whereNotNull('rekaman->dapodik_imported_at')->count();

        return view('admin.api-keys.index', compact('keys', 'logs', 'siswaDapodik'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $result = ApiKey::generate(auth()->id(), $request->name);

        return redirect()->route('admin.api-keys.index')
            ->with('success', 'API Key berhasil dibuat.')
            ->with('new_key', $result['plaintext_key']);
    }

    public function destroy(ApiKey $apiKey)
    {
        $apiKey->delete();
        return redirect()->route('admin.api-keys.index')
            ->with('success', 'API Key dihapus.');
    }

    public function toggle(ApiKey $apiKey)
    {
        $apiKey->update(['active' => !$apiKey->active]);
        return redirect()->route('admin.api-keys.index')
            ->with('success', $apiKey->active ? 'API Key diaktifkan.' : 'API Key dinonaktifkan.');
    }
}
