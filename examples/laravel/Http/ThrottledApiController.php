<?php

declare(strict_types=1);

/*
 * Controller: three ways to react to the limit inside a web request, where you
 * usually do NOT want to block the request thread for long.
 */

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use ZeroxBliv\CallThrottle\Exceptions\RateLimitExceededException;
use ZeroxBliv\CallThrottle\Laravel\Facades\CallThrottle;

final class ThrottledApiController
{
    // 1) Non-blocking: skip and return 429 immediately if no slot is free.
    public function search(): JsonResponse
    {
        $result = CallThrottle::limiter('external-api')->attempt(
            fn () => Http::get('https://api.example.com/search')->json()
        );

        if ($result === null) {
            return response()->json(['error' => 'busy, try again'], 429);
        }

        return response()->json($result);
    }

    // 2) Fail fast with an exception you can map to a 429 (e.g. in the handler).
    public function show(string $id): JsonResponse
    {
        try {
            $data = CallThrottle::limiter('external-api')
                ->withThrowOnLimit()
                ->run(fn () => Http::get("https://api.example.com/items/{$id}")->json());
        } catch (RateLimitExceededException $e) {
            return response()->json(
                ['error' => 'rate limited', 'retry_after' => $e->waitSeconds],
                429,
            );
        }

        return response()->json($data);
    }

    // 3) Willing to wait a little: block up to 2s, else it throws.
    public function sync(): JsonResponse
    {
        $data = CallThrottle::limiter('external-api')
            ->withMaxWait(2.0)
            ->run(fn () => Http::get('https://api.example.com/sync')->json());

        return response()->json($data);
    }
}
