<?php

namespace App\Livewire\Settings;

use App\Models\InstituteModuleOverride;
use App\Services\ModuleAccessService;
use Livewire\Component;

class ModuleToggle extends Component
{
    public array $enabledModules = [];

    public array $optionalModules = [];

    public array $disabledModules = [];

    public array $defaultModules = [];

    protected $listeners = ['refreshModules' => 'loadModules'];

    public function mount(): void
    {
        $this->loadModules();
    }

    public function loadModules(): void
    {
        $institute = auth()->user()->institute;
        $industry = $institute->industry ?? 'real_estate';

        $config = config("industry-modules.{$industry}", []);

        $this->defaultModules = $config['default'] ?? [];
        $this->optionalModules = $config['optional'] ?? [];
        $this->disabledModules = $config['disabled'] ?? [];

        $service = app(ModuleAccessService::class);
        $resolved = $service->resolveEnabled($institute);
        $this->enabledModules = array_keys(array_filter($resolved));
    }

    public function toggleModule(string $moduleKey): void
    {
        $institute = auth()->user()->institute;

        if (! in_array($moduleKey, $this->optionalModules, true)) {
            session()->flash('error', 'This module cannot be toggled.');

            return;
        }

        $isEnabled = in_array($moduleKey, $this->enabledModules, true);

        InstituteModuleOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'module_key' => $moduleKey],
            ['enabled' => ! $isEnabled, 'overridden_by' => auth()->id()]
        );

        app(ModuleAccessService::class)->flushCache($institute->id);
        app(ModuleAccessService::class)->flushFeatureCache($institute->id);

        $this->loadModules();
        session()->flash('success', 'Module updated successfully.');
    }

    public function render()
    {
        return view('livewire.settings.module-toggle');
    }
}
