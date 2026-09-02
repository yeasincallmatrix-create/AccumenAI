<?php

namespace App\Services\Ai;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\User;
use App\Support\TenantContext;

/**
 * Immutable context snapshot for one AI request: who is asking, which tenant,
 * which industry, which features are enabled and what the actor may do.
 * The model never receives raw tenant ids or credentials — only this context
 * is used to authorise tool execution server-side.
 */
class AiContext
{
    public function __construct(
        public readonly InstituteUser|User|null $actor,
        public readonly ?Institute $institute,
        public readonly string $industry = 'other',
        public readonly bool $aiEnabled = false,
        public readonly array $enabledFeatures = [],
        public readonly array $modules = [],
        public readonly array $permissions = [],
        public readonly string $roleSlug = '',
        public readonly ?int $branchId = null,
    ) {}

    public function instituteId(): ?int
    {
        return $this->institute?->id;
    }

    public function tenantContextId(): ?int
    {
        return TenantContext::id();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function featureEnabled(string $feature): bool
    {
        return in_array($feature, $this->enabledFeatures, true);
    }

    public static function resolve(InstituteUser|User|null $actor, ?Institute $institute): self
    {
        $industry = $institute?->industry ?? 'other';

        $aiEnabled = false;
        $enabledFeatures = [];
        if ($institute?->settings !== null) {
            $config = $institute->settings->ai_config ?? [];
            $aiEnabled = (bool) ($config['enabled'] ?? false);
            $enabledFeatures = array_values(array_filter((array) ($config['features'] ?? [])));
        }

        $permissions = [];
        $roleSlug = '';
        $branchId = $actor instanceof InstituteUser ? $actor->branch_id : null;
        if ($actor instanceof InstituteUser) {
            $roleSlug = $actor->role?->slug ?? '';
            $permissions = $actor->isOwner()
                ? ['*']
                : $actor->role?->permissions->pluck('slug')->all() ?? [];
        } elseif ($actor instanceof User) {
            $membership = Membership::query()
                ->where('user_id', $actor->id)
                ->where('institution_id', $institute?->id)
                ->with('role.permissions')
                ->first();
            if ($membership !== null) {
                $roleSlug = $membership->role?->slug ?? '';
                $permissions = $membership->isOwner()
                    ? ['*']
                    : $membership->role?->permissions->pluck('slug')->all() ?? [];
            }
        }

        return new self(
            actor: $actor,
            institute: $institute,
            industry: $industry,
            aiEnabled: $aiEnabled,
            enabledFeatures: $enabledFeatures,
            modules: $enabledFeatures,
            permissions: $permissions,
            roleSlug: $roleSlug,
            branchId: $branchId,
        );
    }
}
