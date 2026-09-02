<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Models\FiscalYear;
use App\Models\InstituteUser;
use App\Support\TenantContext;
use Livewire\Component;

class AccountingDashboardFilter extends Component
{
    public string $preset = 'fiscal_year';
    public string $from = '';
    public string $to = '';
    public ?int $branchId = null;
    public ?int $fiscalYearId = null;

    public array $presets = [
        'this_month',
        'last_month',
        'this_quarter',
        'fiscal_year',
        'previous_fiscal_year',
        'custom',
    ];

    public array $branches = [];
    public array $fiscalYears = [];

    public function mount(): void
    {
        $request = request();
        $user = $request->user();

        if ($user === null) {
            return;
        }

        $instituteId = null;
        if ($user instanceof InstituteUser) {
            $instituteId = (int) $user->institute_id;
        } else {
            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $instituteId = (int) $tenantId;
            }
        }

        if ($instituteId === null) {
            return;
        }

        // Load only active branches belonging to the authenticated tenant
        $this->branches = Branch::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        // Load fiscal years for this tenant
        $this->fiscalYears = FiscalYear::query()
            ->where('institute_id', $instituteId)
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->toArray();

        // Resolve current values from query
        $this->preset = in_array($request->query('range'), $this->presets, true)
            ? $request->query('range')
            : 'fiscal_year';
        $this->from = $request->query('from', '');
        $this->to = $request->query('to', '');
        $this->fiscalYearId = $request->query('fiscal_year_id') ? (int) $request->query('fiscal_year_id') : null;

        // Validate branch_id belongs to this tenant
        $requestedBranch = $request->query('branch_id');
        if ($requestedBranch !== null && $this->branches !== []) {
            $branchIds = array_column($this->branches, 'id');
            if (in_array((int) $requestedBranch, $branchIds, true)) {
                $this->branchId = (int) $requestedBranch;
            }
        }
    }

    public function apply(): void
    {
        $params = ['range' => $this->preset];

        if ($this->preset === 'custom') {
            $params['from'] = $this->from;
            $params['to'] = $this->to;
        }

        if ($this->branchId !== null) {
            $params['branch_id'] = $this->branchId;
        }

        if ($this->fiscalYearId !== null) {
            $params['fiscal_year_id'] = $this->fiscalYearId;
        }

        $this->redirect(route('accounting.dashboard', $params));
    }

    public function render()
    {
        return view('livewire.accounting-dashboard.filter');
    }
}
