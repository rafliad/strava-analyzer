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
            return response()->json(['error' => 'No activities found in the selected date range.'], 422);
        }

        $promptData = $activities->map(function ($activity) {
            return sprintf(
                "- Tanggal: %s, Jenis: %s, Jarak: %.2f km, Waktu Bergerak: %d menit",
                $activity->start_date->format('Y-m-d'),
                $activity->type,
                $activity->distance / 1000,
                $activity->moving_time / 60
            );
        })->implode("\n");

        $startDateFormatted = \Carbon\Carbon::parse($validated['startDate'])->format('d F Y');
        $endDateFormatted = \Carbon\Carbon::parse($validated['endDate'])->format('d F Y');

        $systemPrompt = "Anda adalah pelatih lari profesional. Gunakan Markdown.
        Berikut contoh cara memberi analisis:

        ## Analisis Tren
        Anda konsisten dalam melakukan 3-4 kali latihan per minggu. Pace rata-rata membaik dari 7:00/km ke 6:30/km. Heart rate cenderung lebih stabil di zona 2.

        ## Rekomendasi Latihan
        - Tambahkan easy run 30-40 menit untuk meningkatkan endurance.
        - Lakukan interval 5x400m dengan istirahat 2 menit untuk melatih kecepatan.
        - Pastikan ada 1 hari recovery penuh tanpa lari.
        Sekarang gunakan format dan gaya yang sama untuk data berikut.

        Gunakan format Markdown berikut:
        - Gunakan `##` untuk judul utama.
        - Gunakan `###` untuk sub-judul (contoh: Analisis Tren, Rekomendasi Latihan).
        - Pisahkan setiap paragraf dengan satu baris kosong.
        - Untuk saran, gunakan bullet point `-`.
        - Gunakan **bold** hanya untuk menekankan kata penting, bukan untuk judul.";


        $userPrompt = "Berikut adalah data lari saya dari tanggal {$startDateFormatted} hingga {$endDateFormatted}:\n\n{$promptData}\n\n Tolong analisis sesuai format yang sudah ditentukan.";

        try {
            $apiKey = config('services.gemini.key');
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

            $response = Http::post($apiUrl, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $userPrompt]
                        ]
                    ]
                ],
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt]
                    ]
                ]
            ]);

            if ($response->successful() && isset($response->json()['candidates'][0]['content']['parts'][0]['text'])) {
                $analysisText = $response->json()['candidates'][0]['content']['parts'][0]['text'];
                return response()->json(['analysis' => $analysisText]);
            } else {
                Log::error('Gemini API Error: ' . $response->body());
                return response()->json(['error' => 'Failed to get analysis from AI service.'], 500);
            }
        } catch (\Exception $e) {
            Log::error('Exception calling Gemini API: ' . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred while contacting the AI service.'], 500);
        }
    }
}
