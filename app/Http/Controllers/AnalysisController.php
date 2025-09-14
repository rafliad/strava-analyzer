<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Activity; // NOTE: Tambahkan import untuk Activity

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
        $analysis = $this->askGemini($prompt);

        return response()->json(['analysis' => $analysis]);
    }

    // NOTE: Method baru untuk analisis aktivitas tunggal
    public function performSingleActivityAnalysis(Activity $activity)
    {
        // Pastikan pengguna yang meminta adalah pemilik aktivitas
        if ($activity->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $prompt = $this->buildSingleActivityPrompt($activity);
        $analysis = $this->askGemini($prompt);

        return response()->json(['analysis' => $analysis]);
    }


    private function askGemini(string $prompt): string
    {
        $apiKey = env('GEMINI_API_KEY');
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key={$apiKey}";

        try {
            $response = Http::post($apiUrl, [
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

        $systemPrompt = <<<PROMPT
You are an experienced running & cycling coach.  
Your task: analyze **training performance trends across a given time period**.  

🎯 Rules:  
- Use Markdown formatting.  
- Use clear headings (#) and bold subheadings (##).  
- Keep the analysis positive and constructive.  
- Do NOT give medical advice.  
- The response must be in English.  

Required structure:  
# Training Performance Analysis

**## General Summary**  
- High-level overview of training within the selected date range.

**## Positive Trends**  
- Key improvements or consistent efforts.

**## Areas for Improvement**  
- One or two constructive suggestions.

**## Training Suggestions**  
- One or two simple workout recommendations.

PROMPT;

        return $systemPrompt . "\n\nHere is the activity data from {$startDate} to {$endDate}:\n" . $activityData;
    }


    // NOTE: Method prompt baru yang dirancang khusus untuk satu aktivitas
    private function buildSingleActivityPrompt(Activity $activity): string
    {
        $activityDetails = sprintf(
            "Activity Details:\n- Name: %s\n- Date: %s\n- Type: %s\n- Distance: %.2f km\n- Moving Time: %d minutes %d seconds\n- Total Elevation Gain: %d m",
            $activity->name,
            $activity->start_date->format('F d, Y'),
            $activity->type,
            $activity->distance / 1000,
            floor($activity->moving_time / 60),
            $activity->moving_time % 60,
            $activity->total_elevation_gain
        );

        $streamSummary = $this->summarizeStreams($activity->streams);

        $systemPrompt = <<<PROMPT
You are a professional running coach.  
Your task: provide a **post-run analysis for a SINGLE activity**.  

🎯 Rules:  
- Use Markdown formatting.  
- Use bold subheadings (##) and bullet points.  
- Focus on: pace consistency, heart rate dynamics, power, and hill performance.  
- Always provide positive insights and actionable tips.  
- Do NOT give medical advice.  
- The response must be in English.  

Required structure:  
# Analysis for: {$activity->name}

**## Performance Summary**  
- Overall summary of how the session went.

**## Pace & Heart Rate Analysis**  
- Discuss pace stability.  
- Connect pace with HR (mention if there's drift).  

**## Power & Hill Performance**  
- Power stability throughout the run.  
- How the runner handled elevation changes.  

**## Conclusion & Tips**  
- One positive conclusion.  
- One actionable tip for the next run.  

PROMPT;

        return $systemPrompt . "\n\nHere is the activity data:\n" . $activityDetails . $streamSummary;
    }


    private function summarizeStreams($streams): string
    {
        if (is_string($streams)) {
            $streams = json_decode($streams, true);
        }

        if (!is_array($streams) || count($streams) === 0) {
            return '';
        }

        $heartrates = collect($streams)->pluck('heartrate')->filter();
        $paces = collect($streams)->pluck('pace')->filter();
        $powers = collect($streams)->pluck('watts')->filter();
        $altitudes = collect($streams)->pluck('altitude')->filter();

        $summary = "\n\nStream Summary:\n";
        if ($heartrates->isNotEmpty()) {
            $summary .= sprintf(
                "- Heart Rate: avg %d bpm (min %d, max %d)\n",
                round($heartrates->avg()),
                $heartrates->min(),
                $heartrates->max()
            );
        }
        if ($paces->isNotEmpty()) {
            $summary .= sprintf(
                "- Pace: avg %.2f min/km (min %.2f, max %.2f)\n",
                round($paces->avg(), 2),
                $paces->min(),
                $paces->max()
            );
        }
        if ($powers->isNotEmpty()) {
            $summary .= sprintf(
                "- Power: avg %d W (max %d)\n",
                round($powers->avg()),
                $powers->max()
            );
        }
        if ($altitudes->isNotEmpty()) {
            $summary .= sprintf(
                "- Elevation: min %d m, max %d m\n",
                $altitudes->min(),
                $altitudes->max()
            );
        }

        return $summary;
    }
}
