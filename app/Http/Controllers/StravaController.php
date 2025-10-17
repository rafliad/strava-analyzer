<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use CodeToad\Strava\StravaFacade as Strava;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class StravaController extends Controller
{
    public function redirect()
    {
        return Strava::authenticate('read_all,profile:read_all,activity:read_all');
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect('/dashboard')->with('error', 'Failed to connect to Strava. Authorization was denied.');
        }

        try {
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
        } catch (Exception $e) {
            Log::error('Strava callback error: ' . $e->getMessage());
            return redirect('/dashboard')->with('error', 'An error occurred while connecting to Strava. Please try again.');
        }
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

    public function sync()
    {
        $user = Auth::user();
        /** @var \App\Models\User $user **/
        $cacheKey = "strava.activities.user.{$user->id}";

        $stravaActivities = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($user) {
            $this->checkAndRefreshToken($user);
            return Strava::activities($user->strava_token);
        });

        if (!empty($stravaActivities)) {
            foreach ($stravaActivities as $stravaActivity) {
                $streams = $this->fetchActivityStreams($user, $stravaActivity->id);
                Activity::updateOrCreate(
                    ['strava_id' => $stravaActivity->id, 'user_id' => $user->id],
                    [
                        'name' => $stravaActivity->name,
                        'distance' => $stravaActivity->distance,
                        'moving_time' => $stravaActivity->moving_time,
                        'elapsed_time' => $stravaActivity->elapsed_time,
                        'total_elevation_gain' => $stravaActivity->total_elevation_gain,
                        'type' => $stravaActivity->type,
                        'start_date' => Carbon::parse($stravaActivity->start_date_local),
                        'streams' => json_encode($streams),
                    ]
                );
            }
        }

        $cacheKey = "user:{$user->id}:prs";
        Cache::forget($cacheKey);

        return redirect()->route('activities.index')->with('success', 'Activities have been synced!');
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

        try {
            Strava::unauthenticate($user->strava_token);
        } catch (Exception $e) {
            Log::info('Strava deauthorization failed, likely already revoked by user. Error: ' . $e->getMessage());
        }
        /** @var \App\Models\User $user **/
        $user->update([
            'strava_id'            => null,
            'strava_token'         => null,
            'strava_refresh_token' => null,
            'strava_expires_at'    => null,
        ]);

        return redirect()->route('dashboard')->with('success', 'Strava disconnected successfully.');
    }

    private function checkAndRefreshToken(User $user)
    {
        if ($user->strava_expires_at && now()->timestamp >= $user->strava_expires_at->timestamp) {
            $refresh = Strava::refreshToken($user->strava_refresh_token);

            $user->update([
                'strava_token' => $refresh->access_token,
                'strava_refresh_token' => $refresh->refresh_token,
                'strava_expires_at' => Carbon::createFromTimestamp($refresh->expires_at),
            ]);

            $user->refresh();
        }
    }

    private function fetchActivityStreams(User $user, int $activityId): array
    {
        try {
            $this->checkAndRefreshToken($user);
            $streams = Strava::activityStream($user->strava_token, $activityId, ['distance', 'time', 'heartrate', 'watts', 'altitude']);

            $processedStreams = [];
            $distanceStream = null;
            $timeStream = null;
            $heartrateStream = null;
            $wattsStream = null;
            $altitudeStream = null;

            foreach ($streams as $stream) {
                if ($stream->type === 'distance') $distanceStream = $stream->data;
                if ($stream->type === 'time') $timeStream = $stream->data;
                if ($stream->type === 'heartrate') $heartrateStream = $stream->data;
                if ($stream->type === 'watts') $wattsStream = $stream->data;
                if ($stream->type === 'altitude') $altitudeStream = $stream->data;
            }

            if ($distanceStream && $timeStream) {
                for ($i = 0; $i < count($distanceStream); $i++) {
                    $processedStreams[] = [
                        'distance' => $distanceStream[$i],
                        'time' => $timeStream[$i],
                        'heartrate' => $heartrateStream[$i] ?? null,
                        'watts' => $wattsStream[$i] ?? null,
                        'altitude' => $altitudeStream[$i] ?? null,
                    ];
                }
            }
            return $processedStreams;
        } catch (\Exception $e) {
            Log::error("Failed to fetch streams for activity {$activityId}: " . $e->getMessage());
            return [];
        }
    }
}
