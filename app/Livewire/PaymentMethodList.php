<?php

namespace App\Livewire;

use App\Models\PaymentMethod;
use App\Services\Accounting\PaymentMethodService;
use App\Services\ModuleAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PaymentMethodList extends DataTable
{
    protected const VIEW = 'livewire.payment-methods.list';

    public bool $canManage = false;

    public function mount(): void
    {
        $request = request();
        $this->search = $request->query('q', '');
        $this->perPage = 20;

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
        return PaymentMethod::query()->with(['coa', 'branch']);
    }

    protected function searchableColumns(): array
    {
        return ['name'];
    }

    protected function sortableColumns(): array
    {
        return ['name', 'is_active'];
    }

    protected function defaultSort(): ?string
    {
        return 'name';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    public function toggle(int $methodId): void
    {
        if (! $this->canManage) {
            session()->flash('error', 'Unauthorized action.');

            return;
        }

        $user = auth()->user();
        $method = PaymentMethod::query()->findOrFail($methodId);

        try {
            app(PaymentMethodService::class)->toggleActive($method, $user?->id);
            session()->flash('status', 'Payment method "'.$method->name.'" '.($method->is_active ? 'deactivated' : 'activated').'.');
        } catch (ValidationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function destroy(int $methodId): void
    {
        if (! $this->canManage) {
            session()->flash('error', 'Unauthorized action.');

            return;
        }

        $user = auth()->user();
        $method = PaymentMethod::query()->findOrFail($methodId);

        try {
            app(PaymentMethodService::class)->delete($method, $user?->id);
            session()->flash('status', 'Payment method "'.$method->name.'" deleted.');
        } catch (ValidationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view(self::VIEW, [
            'methods' => $this->getRows(),
            'canManage' => $this->canManage,
        ]);
    }
}
