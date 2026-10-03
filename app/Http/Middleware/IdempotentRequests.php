<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class IdempotentRequests
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $rawKey = $this->normalizeKey($request->header('X-Idempotency-Key'));
        $user = $request->user();

        if ($rawKey === null || $user === null || ! $this->isMutating($request)) {
            return $next($request);
        }

        $method = strtoupper($request->method());
        $path = $this->canonicalPath($request);
        $signature = $this->requestSignature($method, $path, $rawKey);

        if ($cached = $this->findFinishedRecord($user->id, $signature)) {
            return $this->replayResponse($cached);
        }

        $lockKey = $this->lockKey($user->id, $method, $path, $signature);
        if (! Cache::add($lockKey, 1, now()->addMinutes(5))) {
            return $this->retryLaterResponse();
        }

        try {
            if ($cached = $this->findFinishedRecord($user->id, $signature)) {
                return $this->replayResponse($cached);
            }

            $this->pruneOpportunistically();

            $response = $next($request);

            if ($this->shouldStore($response)) {
                $this->storeResponse($user->id, $method, $path, $rawKey, $signature, $response);
            }

            return $response;
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function normalizeKey(?string $key): ?string
    {
        $key = trim((string) $key);

        if ($key === '') {
            return null;
        }

        return mb_substr($key, 0, 80);
    }

    private function isMutating(Request $request): bool
    {
        return in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function findFinishedRecord(int $userId, string $signature): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('key', $signature)
            ->first();
    }

    private function lockKey(int $userId, string $method, string $path, string $signature): string
    {
        return "idempotency-lock:{$userId}:{$method}:{$path}:{$signature}";
    }

    private function replayResponse(IdempotencyKey $record): SymfonyResponse
    {
        if ($record->body_truncated) {
            return $this->retryLaterResponse();
        }

        if ($record->status >= 300 && $record->status < 400 && $record->location) {
            /** @var RedirectResponse $response */
            $response = redirect()->to($record->location, $record->status);
            return $response->header('X-Idempotent-Replay', 'true');
        }

        $response = response($record->body ?? '', $record->status);
        if ($record->content_type) {
            $response->headers->set('Content-Type', $record->content_type);
        }

        return $response->header('X-Idempotent-Replay', 'true');
    }

    private function retryLaterResponse(): SymfonyResponse
    {
        return response()->json([
            'message' => __('sync.retry_later'),
        ], 409);
    }

    private function shouldStore(SymfonyResponse $response): bool
    {
        $status = $response->getStatusCode();

        return ($status >= 200 && $status < 400) || ($status >= 400 && $status < 500);
    }

    private function storeResponse(int $userId, string $method, string $path, string $rawKey, string $signature, SymfonyResponse $response): void
    {
        [$body, $truncated] = $this->extractBodyForStorage($response);
        if ($truncated) {
            return;
        }

        IdempotencyKey::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'key' => $signature,
            ],
            [
                'request_key' => $rawKey,
                'method' => $method,
                'path' => $path,
                'status' => $response->getStatusCode(),
                'body' => $body,
                'body_truncated' => false,
                'location' => $response->headers->get('Location'),
                'content_type' => $response->headers->get('Content-Type'),
                'created_at' => now(),
            ]
        );
    }

    private function extractBodyForStorage(SymfonyResponse $response): array
    {
        $body = null;
        if ($response instanceof Response) {
            $content = $response->getContent();
            if (is_string($content) && $content !== '') {
                $body = $content;
            }
        } elseif (method_exists($response, 'getContent')) {
            $content = $response->getContent();
            if (is_string($content) && $content !== '') {
                $body = $content;
            }
        }

        if ($body !== null && strlen($body) > 65535) {
            return [null, true];
        }

        return [$body, false];
    }

    private function canonicalPath(Request $request): string
    {
        return mb_substr('/' . ltrim($request->path(), '/'), 0, 255);
    }

    private function requestSignature(string $method, string $path, string $rawKey): string
    {
        return sha1($method . '|' . $path . '|' . $rawKey);
    }

    private function pruneOpportunistically(): void
    {
        if (random_int(1, 100) === 1) {
            IdempotencyKey::pruneExpired();
        }
    }
}
