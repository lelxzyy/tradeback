<?php

namespace App\Services\Trading;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AiAnalysisService
{
    public function explain(array $analysis, string $timeframe): array
    {
        $key = (string) config('services.groq.key');
        if ($key === '') {
            throw new RuntimeException('Groq API is not configured.');
        }
        $fingerprint = hash('sha256', json_encode([$timeframe, $analysis]));

        return Cache::store('file')->remember("ai.analysis.$fingerprint", 60, function () use ($key, $analysis, $timeframe) {
            $response = Http::withToken($key)->timeout(30)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model', 'openai/gpt-oss-20b'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Explain XAUUSD technical analysis in concise Indonesian. Never invent or alter prices, indicators, scores, entry, SL, or TP. Use only the engine result. If setup levels are incomplete, recommend waiting. Use sections: Kondisi, Alasan Teknikal, Aksi, Invalidation, Risiko. State that this is informational, not financial advice.'],
                    ['role' => 'user', 'content' => "Timeframe: $timeframe\nVerified engine result:\n".json_encode($analysis, JSON_PRETTY_PRINT)],
                ],
                'temperature' => 0.2,
                'max_completion_tokens' => 500,
            ])->throw()->json();
            $text = $response['choices'][0]['message']['content'] ?? null;
            if (! $text) {
                throw new RuntimeException('AI returned no explanation.');
            }

            return ['explanation' => $text, 'model' => $response['model'] ?? config('services.groq.model'), 'provider' => 'Groq', 'generated_at' => now()->toIso8601String()];
        });
    }
}
