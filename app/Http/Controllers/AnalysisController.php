<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class AnalysisController extends Controller
{
    public function index()
    {
        return Inertia::render('Analysis/Index');
    }

    /**
     * Memproses permintaan analisis performa.
     */
    public function performAnalysis()
    {
        try {
            $user = Auth::user();
            /** @var \App\Models\User $user **/
            $activities = $user->activities()->orderBy('start_date', 'desc')->take(15)->get();

            if ($activities->isEmpty()) {
                return response()->json(['error' => 'Not enough activity data to analyze. Please sync with Strava first.'], 422);
            }

            // 2. Format data menjadi sebuah prompt yang jelas untuk AI
            $prompt = $this->buildPrompt($activities);
            $apiKey = env('GEMINI_API_KEY');
            if (!$apiKey) {
                throw new Exception('GEMINI_API_KEY is not set.');
            }
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key={$apiKey}";

            // 3. Kirim data ke Gemini API
            $response = Http::post($apiUrl, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('Gemini API request failed', ['response' => $response->body()]);
                throw new Exception('Failed to get a response from the AI service.');
            }

            // 4. Ekstrak dan kembalikan teks analisis
            $analysisText = $response->json('candidates.0.content.parts.0.text');

            return response()->json(['analysis' => $analysisText]);
        } catch (Exception $e) {
            Log::error('Analysis Error: ' . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred during analysis.'], 500);
        }
    }

    /**
     * Membangun prompt teks dari koleksi aktivitas.
     */
    private function buildPrompt($activities)
    {
        $dataText = "";
        foreach ($activities as $activity) {
            $distanceKm = round($activity->distance / 1000, 2);
            $movingTimeMinutes = round($activity->moving_time / 60);
            $dataText .= "- {$activity->start_date->format('d M Y')}: Tipe {$activity->type}, Jarak {$distanceKm} km, Waktu Bergerak {$movingTimeMinutes} menit. Nama: {$activity->name}\n";
        }

        return "Anda adalah seorang pelatih lari ahli kelas dunia. Analisis data performa berikut dari seorang atlet. 
        Berikan ringkasan singkat dalam satu paragraf. 
        Kemudian, identifikasi 2-3 tren positif atau pencapaian yang menonjol.
        Terakhir, berikan satu saran konkret dan bisa ditindaklanjuti untuk membantu atlet ini berkembang.
        Gunakan format markdown dan berikan jawaban dalam Bahasa Indonesia.

        Berikut adalah data aktivitas terbarunya (diurutkan dari yang terbaru):
        {$dataText}";
    }
}
