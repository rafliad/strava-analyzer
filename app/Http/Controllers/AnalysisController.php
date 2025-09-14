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
        $analysis = $this->askGemini($prompt);

        return response()->json(['analysis' => $analysis]);
    }

    public function performSingleActivityAnalysis(Activity $activity)
    {
        if ($activity->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $prompt = $this->buildSingleActivityPrompt($activity);
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

        CRITICAL RULES (follow exactly):
        1. Use Markdown only. Do NOT wrap heading markers (#, ##, etc.) with bold or other inline formatting.
        2. Place each heading on its own line and ensure there is a blank line before and after each heading block (so headings render correctly).
        3. Follow the exact structure below and return ONLY the filled content (no extra explanation, no numbered lists before the template):
        # Analysis for: {$activity->name}

        ## Performance Summary

        - [one or two sentences summarizing the session]

        ## Pace & Heart Rate Analysis

        - [comments about pace stability, HR behavior, cardiac drift if any]

        ## Power & Hill Performance

        - [comments about power stability and how elevation affected you]

        ## Conclusion & Tips

        - [one positive conclusion]
        - [one actionable tip]

        END OF TEMPLATE.
        PROMPT;
        return $systemPrompt . "\n\nHere is the activity data:\n" . $activityDetails . $streamSummary;
    }

    private function summarizeStreams($streams): string
    {
        $normalized = $this->normalizeStreams($streams);
        if (empty($normalized)) {
            return '';
        }

        $distances = array_filter($normalized['distance'], function ($v) {
            return is_numeric($v) && $v > 0;
        });
        $times = array_filter($normalized['time'], function ($v) {
            return is_numeric($v) && $v > 0;
        });

        $global = [];
        if (!empty($distances) && !empty($times)) {
            $firstIdx = key($distances);
            $lastIdx = array_key_last($normalized['distance']);
            $firstIdx = null;
            $lastIdx = null;
            for ($i = 0; $i < count($normalized['distance']); $i++) {
                if (is_numeric($normalized['distance'][$i]) && is_numeric($normalized['time'][$i])) {
                    if ($firstIdx === null) $firstIdx = $i;
                    $lastIdx = $i;
                }
            }
            if ($firstIdx !== null && $lastIdx !== null && $lastIdx > $firstIdx) {
                $totalDistM = $normalized['distance'][$lastIdx] - $normalized['distance'][$firstIdx];
                $totalTimeS = $normalized['time'][$lastIdx] - $normalized['time'][$firstIdx];
                if ($totalDistM > 0 && $totalTimeS > 0) {
                    $global['pace_sec_per_km'] = $totalTimeS / ($totalDistM / 1000.0);
                }
            }
        }

        $hrVals = array_filter($normalized['heartrate'], function ($v) {
            return is_numeric($v) && $v > 0;
        });
        $pwVals = array_filter($normalized['watts'], function ($v) {
            return is_numeric($v) && $v > 0;
        });
        $pacePointVals = array_filter($normalized['pace'], function ($v) {
            return is_numeric($v) && $v > 0;
        });

        if (!empty($hrVals)) {
            $global['hr_avg'] = round(array_sum($hrVals) / count($hrVals));
            $global['hr_min'] = round(min($hrVals));
            $global['hr_max'] = round(max($hrVals));
        }
        if (!empty($pwVals)) {
            $global['pw_avg'] = round(array_sum($pwVals) / count($pwVals));
            $global['pw_max'] = round(max($pwVals));
        }
        if (!empty($pacePointVals)) {
            $global['pace_point_avg'] = round(array_sum($pacePointVals) / count($pacePointVals));
            $global['pace_point_min'] = round(min($pacePointVals));
            $global['pace_point_max'] = round(max($pacePointVals));
        }

        $splits = $this->analyzeSplits($normalized, 1000);
        $splitSummary = "";
        if (!empty($splits)) {
            $splitSummary .= "\n\nSplit analysis (per 1 km):\n";
            foreach ($splits as $i => $s) {
                $idx = $i + 1;
                $paceStr = $this->formatPace($s['pace_sec_per_km']);
                $hrStr = $s['avgHR'] ? round($s['avgHR']) . " bpm" : "-";
                $pwStr = $s['avgPower'] ? round($s['avgPower']) . " W" : "-";
                $splitSummary .= sprintf("Split %d: Pace %s, HR %s, Power %s\n", $idx, $paceStr, $hrStr, $pwStr);
            }
            $paces = array_column($splits, 'pace_sec_per_km');
            $validPaces = array_filter($paces, function ($p) {
                return is_numeric($p) && $p > 0;
            });
            if (!empty($validPaces)) {
                $fastestIdx = array_search(min($validPaces), $paces);
                $slowestIdx = array_search(max($validPaces), $paces);
                $splitSummary .= sprintf(
                    "\nFastest split: #%d (%s). Slowest split: #%d (%s).\n",
                    $fastestIdx + 1,
                    $this->formatPace($paces[$fastestIdx]),
                    $slowestIdx + 1,
                    $this->formatPace($paces[$slowestIdx])
                );
            }
        }

        // Cardiac drift detection
        $driftNote = "";
        if (!empty($splits) && count($splits) >= 3) {
            $cnt = count($splits);
            $third = max(1, (int) floor($cnt / 3));
            $firstSlice = array_slice($splits, 0, $third);
            $lastSlice = array_slice($splits, -$third);
            $firstHRs = array_column($firstSlice, 'avgHR');
            $lastHRs  = array_column($lastSlice, 'avgHR');
            $firstAvgHR = !empty($firstHRs) ? (array_sum(array_filter($firstHRs)) / count(array_filter($firstHRs))) : null;
            $lastAvgHR  = !empty($lastHRs) ? (array_sum(array_filter($lastHRs)) / count(array_filter($lastHRs))) : null;
            if ($firstAvgHR && $lastAvgHR && ($lastAvgHR - $firstAvgHR) >= 6) {
                $driftNote = "\nNote: Cardiac drift detected — average HR increased by " . round($lastAvgHR - $firstAvgHR) . " bpm from start to end of the run.\n";
            }
        }

        $summary = "\n\nStream Summary:\n";
        if (isset($global['hr_avg'])) {
            $summary .= sprintf("- Heart Rate: avg %d bpm (min %d, max %d)\n", $global['hr_avg'], $global['hr_min'], $global['hr_max']);
        }
        if (isset($global['pw_avg'])) {
            $summary .= sprintf("- Power: avg %d W (max %d W)\n", $global['pw_avg'], $global['pw_max']);
        }
        if (isset($global['pace_sec_per_km'])) {
            $summary .= sprintf("- Overall Pace (calc): %s min/km\n", $this->formatPace($global['pace_sec_per_km']));
        } elseif (isset($global['pace_point_avg'])) {
            $summary .= sprintf("- Average point-wise pace: %s min/km (approx)\n", $this->formatPace($global['pace_point_avg']));
        }

        $summary .= $splitSummary;
        $summary .= $driftNote;

        return $summary;
    }

    private function normalizeStreams($streams): array
    {
        if (is_null($streams) || $streams === '') {
            return [];
        }

        if (is_string($streams)) {
            $decoded = json_decode($streams, true);
            if ($decoded !== null) $streams = $decoded;
        }

        if (is_object($streams)) {
            $streams = json_decode(json_encode($streams), true);
        }

        $result = [
            'distance'  => [], // in meters
            'time'      => [], // in seconds
            'heartrate' => [],
            'watts'     => [],
            'pace'      => [], // sec per km (calculated)
            'altitude'  => [],
        ];

        // Case A: array of points [{distance:..., time:..., heartrate:...}, ...]
        if (isset($streams[0]) && is_array($streams[0]) && (isset($streams[0]['distance']) || isset($streams[0]['time']))) {
            foreach ($streams as $pt) {
                $result['distance'][]  = isset($pt['distance']) ? $pt['distance'] : null;
                $result['time'][]      = isset($pt['time']) ? $pt['time'] : null;
                $result['heartrate'][] = $pt['heartrate'] ?? null;
                $result['watts'][]     = $pt['watts'] ?? ($pt['power'] ?? null);
                $result['altitude'][]  = $pt['altitude'] ?? null;
            }
        } else {
            // Case B: associative streams like ['distance' => ['data' => [...]], 'time' => ['data'=>[...] ] ] or direct arrays
            $extract = function ($maybe) {
                if ($maybe === null) return [];
                if (is_array($maybe) && isset($maybe['data'])) return $maybe['data'];
                if (is_array($maybe) && array_values($maybe) === $maybe) return $maybe;
                if (isset($maybe[0]) && is_array($maybe[0])) {
                    $vals = array_column($maybe, 'value');
                    if (!empty($vals)) return $vals;
                }
                return [];
            };

            $result['distance']  = $extract($streams['distance'] ?? null);
            $result['time']      = $extract($streams['time'] ?? null);
            $result['heartrate'] = $extract($streams['heartrate'] ?? $streams['heart_rate'] ?? null);
            $result['watts']     = $extract($streams['watts'] ?? $streams['power'] ?? null);
            $result['altitude']  = $extract($streams['altitude'] ?? null);
        }

        $maxLen = max(
            count($result['distance']),
            count($result['time']),
            count($result['heartrate']),
            count($result['watts']),
            count($result['altitude'])
        );
        if ($maxLen === 0) return [];

        foreach (['distance', 'time', 'heartrate', 'watts', 'altitude'] as $k) {
            $result[$k] = array_values($result[$k]);
            if (count($result[$k]) < $maxLen) {
                $result[$k] = array_pad($result[$k], $maxLen, null);
            }
        }

        $distanceMeters = $result['distance'];
        $timeSeconds = $result['time'];
        $pace = [];
        for ($i = 0; $i < $maxLen; $i++) {
            if ($i === 0) {
                $pace[] = null;
                continue;
            }
            $d0 = $distanceMeters[$i - 1];
            $d1 = $distanceMeters[$i];
            $t0 = $timeSeconds[$i - 1];
            $t1 = $timeSeconds[$i];

            if (is_numeric($d0) && is_numeric($d1) && is_numeric($t0) && is_numeric($t1)) {
                $deltaDist = $d1 - $d0; // meters
                $deltaTime = $t1 - $t0; // seconds
                if ($deltaDist > 0 && $deltaTime > 0) {
                    // seconds per km = deltaTime / (deltaDist / 1000)
                    $secPerKm = $deltaTime / ($deltaDist / 1000.0);
                    $pace[] = $secPerKm;
                } else {
                    $pace[] = null;
                }
            } else {
                $pace[] = null;
            }
        }

        $result['distance'] = $distanceMeters;
        $result['time']     = $timeSeconds;
        $result['pace']     = $pace;

        return $result;
    }


    private function analyzeSplits(array $normalized, int $splitDistanceMeters = 1000): array
    {
        $distance = $normalized['distance'] ?? [];
        $time     = $normalized['time'] ?? [];
        $hr       = $normalized['heartrate'] ?? [];
        $power    = $normalized['watts'] ?? [];
        $paceRaw  = $normalized['pace'] ?? [];

        $n = max(count($distance), count($time));
        if ($n === 0) return [];

        // Make arrays the same length using null filling
        $maxLen = max(count($distance), count($time), count($hr), count($power), count($paceRaw));
        $distance = array_pad($distance, $maxLen, null);
        $time     = array_pad($time, $maxLen, null);
        $hr       = array_pad($hr, $maxLen, null);
        $power    = array_pad($power, $maxLen, null);
        $paceRaw  = array_pad($paceRaw, $maxLen, null);

        $distanceInKm = $distance;
        $splits = [];
        $currentStartIdx = 0;
        $currentStartKm = ($distanceInKm[0] ?? 0.0);
        $targetKm = ($currentStartKm + ($splitDistanceMeters / 1000.0));

        for ($i = 0; $i < $maxLen; $i++) {
            $d = $distanceInKm[$i] ?? null;
            if ($d === null) continue;

            if ($d <= $targetKm) {
                continue;
            } else {
                $endIdx = $i - 1;
                $startIdx = $currentStartIdx;
                while ($startIdx <= $endIdx && ($distanceInKm[$startIdx] === null || $time[$startIdx] === null)) {
                    $startIdx++;
                }
                while ($endIdx >= $startIdx && ($distanceInKm[$endIdx] === null || $time[$endIdx] === null)) {
                    $endIdx--;
                }
                if ($startIdx <= $endIdx) {
                    $distKm = $distanceInKm[$endIdx] - $distanceInKm[$startIdx];
                    $timeSec = $time[$endIdx] - $time[$startIdx];
                    $paceSecPerKm = null;
                    if ($distKm > 0 && $timeSec > 0) {
                        $paceSecPerKm = $timeSec / $distKm;
                    }

                    $sliceHR = array_slice($hr, $startIdx, $endIdx - $startIdx + 1);
                    $slicePW = array_slice($power, $startIdx, $endIdx - $startIdx + 1);
                    $hrVals = array_filter($sliceHR, function ($v) {
                        return is_numeric($v) && $v > 0;
                    });
                    $pwVals = array_filter($slicePW, function ($v) {
                        return is_numeric($v) && $v > 0;
                    });

                    $avgHR = !empty($hrVals) ? array_sum($hrVals) / count($hrVals) : null;
                    $avgPW = !empty($pwVals) ? array_sum($pwVals) / count($pwVals) : null;

                    $splits[] = [
                        'distance_km' => $distKm,
                        'time_sec'    => $timeSec,
                        'pace_sec_per_km' => $paceSecPerKm,
                        'avgHR'       => $avgHR,
                        'avgPower'    => $avgPW,
                    ];
                }
                $currentStartIdx = $i;
                $currentStartKm = $d;
                $targetKm = $currentStartKm + ($splitDistanceMeters / 1000.0);
            }
        }

        $lastIdx = $maxLen - 1;
        while ($lastIdx >= $currentStartIdx && ($distanceInKm[$lastIdx] === null || $time[$lastIdx] === null)) {
            $lastIdx--;
        }
        if ($lastIdx > $currentStartIdx) {
            $startIdx = $currentStartIdx;
            while ($startIdx <= $lastIdx && ($distanceInKm[$startIdx] === null || $time[$startIdx] === null)) {
                $startIdx++;
            }
            if ($startIdx <= $lastIdx) {
                $distKm = $distanceInKm[$lastIdx] - $distanceInKm[$startIdx];
                $timeSec = $time[$lastIdx] - $time[$startIdx];
                $paceSecPerKm = null;
                if ($distKm > 0 && $timeSec > 0) {
                    $paceSecPerKm = $timeSec / $distKm;
                }
                $sliceHR = array_slice($hr, $startIdx, $lastIdx - $startIdx + 1);
                $slicePW = array_slice($power, $startIdx, $lastIdx - $startIdx + 1);
                $hrVals = array_filter($sliceHR, function ($v) {
                    return is_numeric($v) && $v > 0;
                });
                $pwVals = array_filter($slicePW, function ($v) {
                    return is_numeric($v) && $v > 0;
                });

                $avgHR = !empty($hrVals) ? array_sum($hrVals) / count($hrVals) : null;
                $avgPW = !empty($pwVals) ? array_sum($pwVals) / count($pwVals) : null;

                $splits[] = [
                    'distance_km' => $distKm,
                    'time_sec'    => $timeSec,
                    'pace_sec_per_km' => $paceSecPerKm,
                    'avgHR'       => $avgHR,
                    'avgPower'    => $avgPW,
                ];
            }
        }

        return $splits;
    }
    private function formatPace(?float $secPerKm): string
    {
        if ($secPerKm === null) return '-';
        $totalSec = (int) round($secPerKm);
        $min = intdiv($totalSec, 60);
        $sec = $totalSec % 60;
        return sprintf('%02d:%02d', $min, $sec);
    }

    private function postProcessMarkdown(string $text): string
    {
        if ($text === '') return $text;
        // 1) Remove bold around headings: **## Heading**  OR **# Heading** OR **### ...**
        //    Capture patterns like **## Heading**  or ** ## Heading ** and replace with ## Heading
        $text = preg_replace('/\*\*\s*(#{1,6}\s*)(.*?)\s*\*\*/m', '$1$2', $text);
        // 2) Remove italic/other wrapping that may include leading #, e.g. *__## Heading__*  (best-effort)
        $text = preg_replace('/[_\*]+\s*(#{1,6}\s*)(.*?)\s*[_\*]+/m', '$1$2', $text);
        // 3) Ensure headings are on their own line: if there is no newline before a heading, add one.
        //    e.g. "Some text ## Heading" -> "Some text\n\n## Heading"
        $text = preg_replace('/([^\\n])\\s*(\\n?)(#{1,6}\\s)/', "$1\n\n$3", $text);
        // 4) If a line starts with whitespace then ##, trim leading spaces so markdown parser treats it as heading
        $text = preg_replace('/^[ \\t]+(#{1,6}\\s+)/m', '$1', $text);
        // 5) Collapse more than 2 consecutive newlines into max 2 (neater)
        $text = preg_replace("/\\n{3,}/", "\n\n", $text);
        // Trim edges
        $text = trim($text) . "\n";
        return $text;
    }
}
