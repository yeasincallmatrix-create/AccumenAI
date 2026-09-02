<?php

namespace App\Livewire;

use App\Models\ChartOfAccount;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\ModuleAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ChartOfAccountList extends DataTable
{
    protected const VIEW = 'livewire.chart-of-accounts.list';

    public bool $canManage = false;

    public function mount(): void
    {
        $request = request();
        $this->search = $request->query('q', '');
        $this->filters = [
            'type' => $request->query('type', ''),
            'status' => $request->query('status', ''),
        ];
        $this->perPage = 25;

        $user = $request->user();
        if ($user !== null) {
            $institute = $user instanceof \App\Models\InstituteUser
                ? \App\Models\Institute::query()->find($user->institute_id)
                : null;
            $moduleService = app(ModuleAccessService::class);
            $hasPerm = $user->hasPermission('settings.accounting.manage');
            $this->canManage = $institute !== null
                && $moduleService->isEnabled($institute, 'finance')
                && $hasPerm;
        }
    }

    protected function baseQuery(): Builder
    {
        return ChartOfAccount::query()->with('parent');
    }

    protected function searchableColumns(): array
    {
        return ['code', 'name'];
    }

    protected function filterableColumns(): array
    {
        return [
            'type' => ['type' => 'exact'],
            'status' => ['type' => 'exact', 'column' => 'is_active'],
        ];
    }

    protected function sortableColumns(): array
    {
        return ['code', 'name', 'type'];
    }

    protected function defaultSort(): ?string
    {
        return 'code';
    }

    protected function applyFilter(Builder $query, string $key, mixed $value, array $config): void
    {
        match ($key) {
            'type' => $query->where('type', $value),
            'status' => $query->where('is_active', $value === 'active'),
            default => null,
        };
    }

    public function toggle(int $accountId): void
    {
        if (! $this->canManage) {
            session()->flash('error', 'Unauthorized action.');

            return;
        }

        $user = auth()->user();
        $account = ChartOfAccount::query()->findOrFail($accountId);

        try {
            app(ChartOfAccountService::class)->toggleActive($account, $user?->id);
            session()->flash('status', 'Account "'.$account->code.'" '.($account->is_active ? 'deactivated' : 'activated').'.');
        } catch (ValidationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function destroy(int $accountId): void
    {
        if (! $this->canManage) {
            session()->flash('error', 'Unauthorized action.');

            return;
        }

        $user = auth()->user();
        $account = ChartOfAccount::query()->findOrFail($accountId);

        try {
            app(ChartOfAccountService::class)->delete($account, $user?->id);
            session()->flash('status', 'Account "'.$account->code.'" deleted.');
        } catch (ValidationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view(self::VIEW, [
            'accounts' => $this->getRows(),
            'canManage' => $this->canManage,
        ]);
    }
}
