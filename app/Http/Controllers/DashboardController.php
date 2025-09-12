<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        /** @var \App\Models\User $user **/

        $stats = [
            'total_distance_this_month' => 0,
            'total_activities_this_month' => 0,
            'is_connected' => false,
        ];
        $recentActivities = [];

        // Periksa apakah pengguna terhubung ke Strava
        if ($user->strava_id) {
            $stats['is_connected'] = true;

            // Ambil tanggal awal dan akhir bulan ini
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();

            // Hitung statistik dari database lokal
            $stats['total_distance_this_month'] = $user->activities()
                ->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                ->sum('distance');

            $stats['total_activities_this_month'] = $user->activities()
                ->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                ->count();

            // Ambil 5 aktivitas terbaru dari database lokal
            $recentActivities = $user->activities()
                ->orderBy('start_date', 'desc')
                ->take(5)
                ->get();
        }

        // Kirimkan semua data sebagai props ke halaman Inertia
        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentActivities' => $recentActivities,
        ]);
    }
}
