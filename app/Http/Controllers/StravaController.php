<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use CodeToad\Strava\StravaFacade as Strava;
use Illuminate\Http\Request;
use Exception;

class StravaController extends Controller
{
    public function redirect()
    {
        return redirect(Strava::getAuthorizationUrl());
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect('/dashboard')->with('error', 'Failed to connect to Strava. Authorization was denied.');
        }

        try {
            $code = $request->query('code');

            $tokenData = Strava::getAccessToken($code);

            $user = Auth::user();

            /** @var \App\Models\User $user **/
            $user->update([
                'strava_id' => $tokenData->athlete->id,
                'strava_token' => $tokenData->access_token,
                'strava_refresh_token' => $tokenData->refresh_token,
                'strava_expires_at' => now()->addSeconds($tokenData->expires_in),
            ]);

            return redirect('/dashboard')->with('status', 'Successfully connected to Strava!');
        } catch (Exception $e) {
            return redirect('/dashboard')->with('error', 'An error occurred while connecting to Strava. Please try again.');
        }
    }

    public function athlete()
    {
        $token = env('STRAVA_ACCESS_TOKEN');

        $athlete = Strava::athlete($token);

        return response()->json($athlete);
    }

    public function activities()
    {
        $token = env('STRAVA_ACCESS_TOKEN');
        $activities = Strava::activities($token, 1, 5);

        return response()->json($activities);
    }

    public function singleActivity($id)
    {
        $token = env('STRAVA_ACCESS_TOKEN');

        $activity = Strava::activity($token, $id);

        return response()->json($activity);
    }
}
