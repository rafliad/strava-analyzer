<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Activity;

class AnalysisController extends Controller
{
    public function index()
    {
        return inertia('Analysis/Index');
    }

    public function performAnalysis(Request $request)
    {
        $validated = $request->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
        ]);

        $user = Auth::user();
        /** @var \App\Models\User $user **/
        $activities = $user->activities()
            ->whereBetween('start_date', [$validated['startDate'], $validated['endDate']])
            ->orderBy('start_date', 'asc')
            ->get();

        if ($activities->isEmpty()) {
            return response()->json(['analysis' => 'Tidak ada aktivitas yang ditemukan pada rentang tanggal yang dipilih.']);
        }

        $prompt = $this->buildAnalysisPrompt($activities, $validated['startDate'], $validated['endDate']);
        $analysisRaw = $this->askGemini($prompt);
        $analysis = $this->postProcessMarkdown($analysisRaw);

        return response()->json(['analysis' => $analysis]);
    }

    public function performSingleActivityAnalysis(Activity $activity)
    {
        if ($activity->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $preparedData = $this->prepareSingleActivityDataForAI($activity);
        $prompt = $this->buildSingleActivityPrompt($activity, $preparedData);
        $analysisRaw = $this->askGemini($prompt);
        $analysis = $this->postProcessMarkdown($analysisRaw);

        return response()->json(['analysis' => $analysis]);
    }


    private function askGemini(string $prompt): string
    {
        $apiKey = env('GEMINI_API_KEY');
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key={$apiKey}";

        try {
            $response = Http::timeout(60)->post($apiUrl, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text', 'Maaf, analisis tidak dapat dibuat saat ini.');
            }

            Log::error('Gemini API Error: ' . $response->body());
            return 'Terjadi kesalahan saat berkomunikasi dengan layanan AI.';
        } catch (\Exception $e) {
            Log::error('Gemini API Exception: ' . $e->getMessage());
            return 'Gagal terhubung ke layanan AI.';
        }
    }

    private function buildAnalysisPrompt($activities, $startDate, $endDate): string
    {
        $activityData = $activities->map(function ($activity) {
            return sprintf(
                "- Date: %s, Type: %s, Distance: %.2f km, Moving Time: %d minutes, Elevation: %d m",
                $activity->start_date->format('Y-m-d'),
                $activity->type,
                $activity->distance / 1000,
                $activity->moving_time / 60,
                $activity->total_elevation_gain
            );
        })->implode("\n");

        $systemPrompt = file_get_contents(resource_path('prompts/analysis_prompt.txt'));
        return $systemPrompt . "\n\nHere is the activity data from {$startDate} to {$endDate}:\n" . $activityData;
    }


    private function buildSingleActivityPrompt(Activity $activity, array $preparedData): string
    {
        $dataString = json_encode($preparedData, JSON_PRETTY_PRINT);

        $systemPrompt = file_get_contents(resource_path('prompts/single_activity_analysis_prompt.txt'));
        $systemPrompt = str_replace('{activity_name}', $activity->name, $systemPrompt);

        return $systemPrompt . "\n\nAnalyze the following activity data:\n" . $dataString;
    }

    // NOTE: Method-method kompleks sebelumnya digantikan oleh satu method ini.
    /**
     * Pre-processes activity streams into a simple, structured array for the AI.
     * Calculates first-half vs. second-half metrics for cardiac drift analysis.
     */
    private function prepareSingleActivityDataForAI(Activity $activity): array
    {
        $streams = is_string($activity->streams) ? json_decode($activity->streams, true) : $activity->streams;

        if (empty($streams) || !is_array($streams)) {
            return ['error' => 'No stream data available for detailed analysis.'];
        }

        // --- Calculate Overall Averages ---
        $validHr = array_filter(array_column($streams, 'heartrate'));
        $validWatts = array_filter(array_column($streams, 'watts'));
        $avgHr = !empty($validHr) ? round(array_sum($validHr) / count($validHr)) : null;
        $avgWatts = !empty($validWatts) ? round(array_sum($validWatts) / count($validWatts)) : null;

        // --- Calculate Half-Splits ---
        $totalPoints = count($streams);
        $midPointIndex = intval($totalPoints / 2);

        $firstHalfStreams = array_slice($streams, 0, $midPointIndex);
        $secondHalfStreams = array_slice($streams, $midPointIndex);

        $calculateHalfMetrics = function ($halfStreams) {
            if (count($halfStreams) < 2) {
                return ['avg_hr' => null, 'avg_pace_min_km' => null];
            }

            $startPoint = $halfStreams[0];
            $endPoint = end($halfStreams);

            $deltaDistance = $endPoint['distance'] - $startPoint['distance'];
            $deltaTime = $endPoint['time'] - $startPoint['time'];

            $halfAvgHr = null;
            $hrData = array_filter(array_column($halfStreams, 'heartrate'));
            if (!empty($hrData)) {
                $halfAvgHr = round(array_sum($hrData) / count($hrData));
            }

            $halfAvgPace = null;
            if ($deltaDistance > 0 && $deltaTime > 0) {
                // Pace in seconds per km -> minutes per km
                $paceInSecPerKm = ($deltaTime / $deltaDistance) * 1000;
                $halfAvgPace = round($paceInSecPerKm / 60, 2);
            }

            return ['avg_hr' => $halfAvgHr, 'avg_pace_min_km' => $halfAvgPace];
        };

        $firstHalfMetrics = $calculateHalfMetrics($firstHalfStreams);
        $secondHalfMetrics = $calculateHalfMetrics($secondHalfStreams);

        // --- Assemble Final Data Structure ---
        return [
            'overall_summary' => [
                'distance_km' => round($activity->distance / 1000, 2),
                'moving_time_minutes' => round($activity->moving_time / 60, 2),
                'avg_hr' => $avgHr,
                'avg_watts' => $avgWatts,
                'elevation_gain_m' => $activity->total_elevation_gain,
            ],
            'first_half_metrics' => $firstHalfMetrics,
            'second_half_metrics' => $secondHalfMetrics,
        ];
    }

    private function postProcessMarkdown(string $text): string
    {
        if ($text === '') return $text;
        $text = preg_replace('/\*\*\s*(#{1,6}\s*)(.*?)\s*\*\*/m', '$1$2', $text);
        $text = preg_replace('/[_\*]+\s*(#{1,6}\s*)(.*?)\s*[_\*]+/m', '$1$2', $text);
        $text = preg_replace('/([^\\n])\\s*(\\n?)(#{1,6}\\s)/', "$1\n\n$3", $text);
        $text = preg_replace('/^[ \\t]+(#{1,6}\\s+)/m', '$1', $text);
        $text = preg_replace("/\\n{3,}/", "\n\n", $text);
        return trim($text) . "\n";
    }
}
