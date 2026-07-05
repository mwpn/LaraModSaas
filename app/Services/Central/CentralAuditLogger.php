<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\CentralAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CentralAuditLogger
{
    public function info(string $eventKey, string $summary, array $context = []): void
    {
        $this->write('info', $eventKey, $summary, $context);
    }

    public function warning(string $eventKey, string $summary, array $context = []): void
    {
        $this->write('warning', $eventKey, $summary, $context);
    }

    public function error(string $eventKey, string $summary, array $context = []): void
    {
        $this->write('error', $eventKey, $summary, $context);
    }

    public function write(string $level, string $eventKey, string $summary, array $context = []): void
    {
        try {
            /** @var User|null $actor */
            $actor = Auth::guard('central')->user();

            CentralAuditLog::query()->create([
                'actor_id' => $actor?->getKey(),
                'actor_email' => $actor?->email,
                'level' => $level,
                'event_key' => $eventKey,
                'target_type' => isset($context['target_type']) ? (string) $context['target_type'] : null,
                'target_id' => isset($context['target_id']) ? (string) $context['target_id'] : null,
                'summary' => $summary,
                'meta' => $context['meta'] ?? [],
            ]);
        } catch (Throwable) {
            // Audit logging must never break the primary workflow.
        }
    }
}
