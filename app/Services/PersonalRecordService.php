<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PersonalRecordService
{
    private const DISTANCES = [
        '1k' => 1000,
        '5k' => 5000,
        '10k' => 10000,
        'half-marathon' => 21097,
        'marathon' => 42195,
    ];

    public function calculatePersonalRecords(User $user)
    {
        $cacheKey = "user:{$user->id}:prs";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($user) {
            return $this->performCalculation($user);
        });
    }

    private function performCalculation(User $user)
    {
        $personalRecords = [];

        foreach (self::DISTANCES as $name => $distance) {
            $personalRecords[$name] = $this->calculatePRForDistance($user, $distance);
        }

        return $personalRecords;
    }

    private function calculatePRForDistance(User $user, int $distance)
    {
        $activities = $user->activities()
            ->where('type', 'Run')
            ->where('distance', '>=', $distance)
            ->get();

        $bestTime = null;
        $bestActivity = null;

        foreach ($activities as $activity) {
            $streams = json_decode($activity->streams, true);

            if (empty($streams)) {
                continue;
            }

            $startTime = 0;
            $endTime = 0;

            while ($endTime < count($streams)) {
                $startPoint = $streams[$startTime];
                $endPoint = $streams[$endTime];

                $distanceCovered = $endPoint['distance'] - $startPoint['distance'];

                if ($distanceCovered >= $distance) {
                    $timeTaken = $endPoint['time'] - $startPoint['time'];

                    if (is_null($bestTime) || $timeTaken < $bestTime) {
                        $bestTime = $timeTaken;
                        $bestActivity = $activity;
                    }

                    $startTime++;
                } else {
                    $endTime++;
                }
            }
        }

        if (is_null($bestTime)) {
            return null;
        }

        return [
            'time' => $bestTime,
            'date' => $bestActivity->start_date->format('Y-m-d'),
            'activity_name' => $bestActivity->name,
        ];
    }
}
