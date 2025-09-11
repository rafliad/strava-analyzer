<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        /** @var \App\Models\User $user **/
        $activities = $user->activities()->orderBy('start_date', 'desc')->get();
        return Inertia::render('Strava/Activities', [
            'activities' => $activities,
        ]);
    }
}
