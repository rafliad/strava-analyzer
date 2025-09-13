<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Activity;


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

    public function show(Activity $activity)
    {
        if ($activity->user_id !== Auth::id()) {
            abort(403);
        }

        return Inertia::render('Strava/Show', [
            'activity' => $activity
        ]);
    }
}
