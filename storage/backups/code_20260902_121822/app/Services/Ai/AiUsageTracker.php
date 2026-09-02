<?php

namespace App\Services\Ai;

use App\Models\AiUsage;
use App\Support\AiConfig;
use Illuminate\Support\Carbon;

class AiUsageException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $period = '')
    {
        parent::__construct($message);
    }
}

/**
 * Per-tenant AI usage limits and counters. Limits live in the tenant's ai_config
 * (0 = unlimited). Counters are upserted daily and monthly so both windows can be
 * enforced and reset without scheduled jobs.
 */
class AiUsageTracker
{
    public function enforceLimits(AiContext $context): void
    {
        if ($context->institute === null) {
            return;
        }

        $config = $context->institute->settings?->ai_config ?? [];
        $dailyLimit = $this->effectiveLimit(
            (int) ($config['daily_limit'] ?? 0),
            AiConfig::dailyLimit()
        );
        $monthlyLimit = $this->effectiveLimit(
            (int) ($config['monthly_limit'] ?? 0),
            AiConfig::monthlyLimit()
        );

        if ($dailyLimit > 0 && $this->count($context, AiUsage::PERIOD_TYPE_DAILY) >= $dailyLimit) {
            throw new AiUsageException('Daily AI request limit reached.', AiUsage::PERIOD_TYPE_DAILY);
        }

        if ($monthlyLimit > 0 && $this->count($context, AiUsage::PERIOD_TYPE_MONTHLY) >= $monthlyLimit) {
            throw new AiUsageException('Monthly AI request limit reached.', AiUsage::PERIOD_TYPE_MONTHLY);
        }
    }

    public function count(AiContext $context, string $type): int
    {
        return (int) AiUsage::query()
            ->where('institute_id', $context->instituteId())
            ->where('period_type', $type)
            ->where('period', $this->period($type))
            ->value('requests');
    }

    public function record(AiContext $context, int $tokens): void
    {
        if ($context->instituteId() === null) {
            return;
        }

        foreach ([AiUsage::PERIOD_TYPE_DAILY, AiUsage::PERIOD_TYPE_MONTHLY] as $type) {
            AiUsage::query()
                ->updateOrCreate(
                    [
                        'institute_id' => $context->instituteId(),
                        'period_type' => $type,
                        'period' => $this->period($type),
                    ],
                    []
                )
                ->increment('requests');
        }

        AiUsage::query()
            ->where('institute_id', $context->instituteId())
            ->where('period_type', AiUsage::PERIOD_TYPE_DAILY)
            ->where('period', $this->period(AiUsage::PERIOD_TYPE_DAILY))
            ->increment('tokens', $tokens);
    }

    private function period(string $type): string
    {
        return $type === AiUsage::PERIOD_TYPE_DAILY
            ? Carbon::today()->format('Y-m-d')
            : Carbon::today()->format('Y-m');
    }

    /**
     * The platform default is a hard cap: an institute can never exceed it, even
     * if its own limit is higher or unlimited (0). When the platform leaves the
     * limit at 0 (unlimited) the institute's own value applies unchanged.
     */
    private function effectiveLimit(int $institute, int $platform): int
    {
        if ($platform > 0) {
            return $institute > 0 ? min($institute, $platform) : $platform;
        }

        return $institute;
    }
}
