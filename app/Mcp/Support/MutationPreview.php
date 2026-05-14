<?php

namespace App\Mcp\Support;

class MutationPreview
{
    /**
     * @param  array<int, array<string, mixed>>  $changes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $action,
        public readonly string $summary,
        public readonly array $changes,
        public readonly string $risk = 'medium',
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $previewToken = null): array
    {
        return [
            'action' => $this->action,
            'summary' => $this->summary,
            'risk' => $this->risk,
            'changes' => $this->changes,
            'metadata' => $this->metadata,
            'preview_token' => $previewToken,
            'expires_in_minutes' => $previewToken ? 10 : null,
        ];
    }
}
