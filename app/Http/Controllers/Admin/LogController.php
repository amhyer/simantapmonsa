<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitas;
use App\Models\User;

class LogController extends Controller
{
    public function index()
    {
        $logs = Aktivitas::with('guru')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $users = User::orderBy('nama_lengkap')->get();

        return view('admin.log.index', compact('logs', 'users'));
    }
}
