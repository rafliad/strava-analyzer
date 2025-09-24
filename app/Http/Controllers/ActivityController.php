<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        /** @var \App\Models\User $user **/
        $activities = $user->activities()->orderBy('start_date', 'desc')->get();
        return Inertia::render('Strava/Activities', [
            'activities' => $activities,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Activity $activity)
    {
        // Pastikan hanya pemilik yang bisa melihat
        if ($activity->user_id !== Auth::id()) {
            abort(403);
        }

        // NOTE: Panggil method baru untuk menghitung splits
        $splits = $this->calculateSplits($activity);

        return Inertia::render('Strava/Show', [
            'activity' => $activity,
            'splits' => $splits, // NOTE: Kirim data splits sebagai props
        ]);
    }

    /**
     * NOTE: Method baru untuk menghitung split per kilometer.
     */
    private function calculateSplits(Activity $activity): array
    {
        $streams = is_string($activity->streams) ? json_decode($activity->streams, true) : $activity->streams;

        if (empty($streams) || !is_array($streams) || count($streams) < 2) {
            return [];
        }

        $splits = [];
        $splitDistanceKm = 1;
        $nextSplitMark = $splitDistanceKm;
        $splitStartPoint = $streams[0];
        $lastProcessedIndex = 0;

        foreach ($streams as $index => $currentPoint) {
            $currentDistanceKm = $currentPoint['distance'] / 1000;

            if ($currentDistanceKm >= $nextSplitMark) {
                // Kalkulasi untuk split yang baru saja selesai
                $deltaDistance = $currentPoint['distance'] - $splitStartPoint['distance'];
                $deltaTime = $currentPoint['time'] - $splitStartPoint['time'];

                $pace = null;
                if ($deltaDistance > 0 && $deltaTime > 0) {
                    $pace = ($deltaTime / ($deltaDistance / 1000));
                }

                $segmentStreams = array_slice($streams, $lastProcessedIndex, ($index - $lastProcessedIndex) + 1);

                $hrData = array_filter(array_column($segmentStreams, 'heartrate'));
                $wattsData = array_filter(array_column($segmentStreams, 'watts'));

                $avgHr = !empty($hrData) ? round(array_sum($hrData) / count($hrData)) : null;
                $avgWatts = !empty($wattsData) ? round(array_sum($wattsData) / count($wattsData)) : null;

                $splits[] = [
                    'split' => (int) $nextSplitMark,
                    'pace_sec_per_km' => $pace,
                    'avg_hr' => $avgHr,
                    'avg_watts' => $avgWatts,
                ];

                $nextSplitMark += $splitDistanceKm;
                $splitStartPoint = $currentPoint;
                $lastProcessedIndex = $index;
            }
        }

        // NOTE: Logika baru untuk menangani sisa split terakhir
        $lastPoint = end($streams);
        // Periksa apakah ada sisa jarak yang signifikan (lebih dari 10 meter)
        if ($lastPoint['distance'] > $splitStartPoint['distance'] && ($lastPoint['distance'] - $splitStartPoint['distance']) > 10) {
            $deltaDistance = $lastPoint['distance'] - $splitStartPoint['distance'];
            $deltaTime = $lastPoint['time'] - $splitStartPoint['time'];

            $pace = null;
            if ($deltaDistance > 0 && $deltaTime > 0) {
                $pace = ($deltaTime / ($deltaDistance / 1000));
            }

            $segmentStreams = array_slice($streams, $lastProcessedIndex);

            $hrData = array_filter(array_column($segmentStreams, 'heartrate'));
            $wattsData = array_filter(array_column($segmentStreams, 'watts'));

            $avgHr = !empty($hrData) ? round(array_sum($hrData) / count($hrData)) : null;
            $avgWatts = !empty($wattsData) ? round(array_sum($wattsData) / count($wattsData)) : null;

            $splits[] = [
                // Tampilkan jarak total sebagai label split terakhir
                'split' => number_format($activity->distance / 1000, 2),
                'pace_sec_per_km' => $pace,
                'avg_hr' => $avgHr,
                'avg_watts' => $avgWatts,
            ];
        }

        return $splits;
    }
}
