<?php

namespace App\Mcp\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class McpMutationGuard
{
    /**
     * @param  Closure(): array<string, mixed>  $mutate
     */
    public function handle(Request $request, MutationPreview $preview, Closure $mutate): ResponseFactory
    {
        $dryRun = $this->boolean($request->get('dry_run', true));
        $confirm = $this->boolean($request->get('confirm', false));
        $fingerprint = $this->fingerprint($request, $preview);

        if ($dryRun || ! $confirm) {
            $token = $this->storePreview($fingerprint);

            return Response::structured([
                'status' => 'preview',
                'would_write' => false,
                'requires_confirmation' => true,
                'message' => 'No changes were made. Re-run with dry_run=false, confirm=true, and this preview_token to execute.',
                'preview' => $preview->toArray($token),
            ]);
        }

        $previewToken = (string) $request->get('preview_token', '');

        if (! $this->validPreviewToken($previewToken, $fingerprint)) {
            return Response::make(Response::error('Invalid or expired preview_token. Run the tool in dry-run mode again to get a fresh preview.'))
                ->withStructuredContent([
                    'status' => 'blocked',
                    'would_write' => false,
                    'requires_confirmation' => true,
                    'preview' => $preview->toArray(),
                ]);
        }

        Cache::forget($this->cacheKey($previewToken));

        return Response::structured([
            'status' => 'executed',
            'would_write' => true,
            'requires_confirmation' => false,
            'preview' => $preview->toArray(),
            'result' => $mutate(),
        ]);
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function storePreview(string $fingerprint): string
    {
        $token = hash_hmac('sha256', $fingerprint.'|'.bin2hex(random_bytes(16)), (string) config('app.key'));

        Cache::put($this->cacheKey($token), $fingerprint, now()->addMinutes(10));

        return $token;
    }

    private function validPreviewToken(string $token, string $fingerprint): bool
    {
        if ($token === '') {
            return false;
        }

        return hash_equals($fingerprint, (string) Cache::get($this->cacheKey($token), ''));
    }

    private function fingerprint(Request $request, MutationPreview $preview): string
    {
        $arguments = collect($request->all())
            ->except(['dry_run', 'confirm', 'preview_token'])
            ->sortKeys()
            ->all();

        return hash('sha256', json_encode([
            'action' => $preview->action,
            'summary' => $preview->summary,
            'risk' => $preview->risk,
            'arguments' => $arguments,
        ], JSON_THROW_ON_ERROR));
    }

    private function cacheKey(string $token): string
    {
        return 'mcp-preview:'.$token;
    }
}
