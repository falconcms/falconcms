<?php

namespace FalconAi\Support;

use FalconAi\Data\AiResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What each AI request used, so an administrator can see the month's tokens (AI → Usage) and a
 * monthly limit can be held. Only counts are kept: never the prompt or the answer.
 */
final class Usage
{
    public const TABLE = 'falcon_ai_usage';

    public static function record(string $feature, AiResult $result): void
    {
        try {
            DB::table(self::TABLE)->insert([
                'user_id' => auth()->id(),
                'provider' => mb_substr($result->provider, 0, 40),
                'model' => mb_substr($result->model, 0, 100),
                'feature' => mb_substr($feature, 0, 40),
                'input_tokens' => max(0, $result->inputTokens),
                'output_tokens' => max(0, $result->outputTokens),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // The answer still reaches the user; only the count is lost.
            Log::warning('Falcon AI: could not record usage: '.$e->getMessage());
        }
    }

    /** @return array{requests: int, input: int, output: int, tokens: int} */
    public static function thisMonth(): array
    {
        try {
            $row = DB::table(self::TABLE)
                ->where('created_at', '>=', now()->startOfMonth())
                ->selectRaw('COUNT(*) as requests, COALESCE(SUM(input_tokens), 0) as input, COALESCE(SUM(output_tokens), 0) as output')
                ->first();
        } catch (Throwable $e) {
            return ['requests' => 0, 'input' => 0, 'output' => 0, 'tokens' => 0];
        }

        return [
            'requests' => (int) $row->requests,
            'input' => (int) $row->input,
            'output' => (int) $row->output,
            'tokens' => (int) $row->input + (int) $row->output,
        ];
    }

    /**
     * This month by provider, model and feature, most tokens first.
     *
     * @return list<object{provider: string, model: string, feature: string, requests: int, input: int, output: int}>
     */
    public static function breakdown(): array
    {
        try {
            return DB::table(self::TABLE)
                ->where('created_at', '>=', now()->startOfMonth())
                ->groupBy('provider', 'model', 'feature')
                ->selectRaw('provider, model, feature, COUNT(*) as requests, SUM(input_tokens) as input, SUM(output_tokens) as output')
                ->orderByRaw('SUM(input_tokens) + SUM(output_tokens) DESC')
                ->get()->all();
        } catch (Throwable $e) {
            return [];
        }
    }
}
