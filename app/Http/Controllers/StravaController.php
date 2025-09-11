<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use CodeToad\Strava\StravaFacade as Strava;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class StravaController extends Controller
{
    // Step 1: redirect user ke Strava
    public function redirect()
    {
        return Strava::authenticate('read_all,profile:read_all,activity:read_all');
    }

    // Step 2: handle callback dari Strava
    public function callback(Request $request)
    {
        $token = Strava::token($request->code);

        $user = Auth::user();
        /** @var \App\Models\User $user **/
        $user->update([
            'strava_id'            => $token->athlete->id,
            'strava_token'         => $token->access_token,
            'strava_refresh_token' => $token->refresh_token,
            'strava_expires_at'    => Carbon::createFromTimestamp($token->expires_at),
        ]);

        return redirect()->route('dashboard')->with('success', 'Strava connected!');
    }

    public function athlete()
    {
        $user = Auth::user();
        /** @var \App\Models\User $user **/
        if (now()->timestamp >= $user->strava_expires_at->timestamp) {
            $refresh = Strava::refreshToken($user->strava_refresh_token);
            $user->update([
                'strava_token'         => $refresh->access_token,
                'strava_refresh_token' => $refresh->refresh_token,
                'strava_expires_at'    => Carbon::createFromTimestamp($refresh->expires_at),
            ]);
        }

        $athlete = Strava::athlete($user->strava_token);

        return response()->json($athlete);
    }

    public function activities()
    {
        $user = Auth::user();

        if (now()->timestamp >= $user->strava_expires_at->timestamp) {
            $refresh = Strava::refreshToken($user->strava_refresh_token);
            /** @var \App\Models\User $user **/
            $user->update([
                'strava_token'         => $refresh->access_token,
                'strava_refresh_token' => $refresh->refresh_token,
                'strava_expires_at'    => Carbon::createFromTimestamp($refresh->expires_at),
            ]);
        }

        $activities = Strava::activities($user->strava_token);

        return response()->json($activities);
    }

    public function singleActivity($id)
    {
        $user = Auth::user();

        if (now()->timestamp >= $user->strava_expires_at->timestamp) {
            $refresh = Strava::refreshToken($user->strava_refresh_token);
            /** @var \App\Models\User $user **/
            $user->update([
                'strava_token'         => $refresh->access_token,
                'strava_refresh_token' => $refresh->refresh_token,
                'strava_expires_at'    => Carbon::createFromTimestamp($refresh->expires_at),
            ]);
        }

        $activity = Strava::activity($user->strava_token, $id);

        return response()->json($activity);
    }

    public function disconnect()
    {
        $user = Auth::user();

        Strava::unauthenticate($user->strava_token);
        /** @var \App\Models\User $user **/
        $user->update([
            'strava_id'            => null,
            'strava_token'         => null,
            'strava_refresh_token' => null,
            'strava_expires_at'    => null,
        ]);

        return back()->with('success', 'Strava disconnected');
    }
}
