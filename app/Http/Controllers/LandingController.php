<?php

namespace App\Http\Controllers;

class LandingController extends Controller
{
    public function index()
    {
        return view('landing');
    }

    public function demo()
    {
        if (!app()->environment('local')) {
            return redirect()->route('login');
        }
        return redirect()->route('login')->with('info', 'Demo: admin/admin123, guru/guru123');
    }
}
