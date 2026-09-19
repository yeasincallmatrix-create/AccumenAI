<?php

namespace App\Services\LabIntegration;

use App\Contracts\LabIntegration\AnalyzerAdapterInterface;

class AnalyzerAdapterRegistry
{
    /** @var array<string, AnalyzerAdapterInterface> */
    protected array $adapters = [];

    public function register(AnalyzerAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->key() . ':' . $adapter->version()] = $adapter;
    }

    public function resolve(string $key, string $version = 'v1'): ?AnalyzerAdapterInterface
    {
        return $this->adapters[$key . ':' . $version] ?? null;
    }

    public function all(): array
    {
        return $this->adapters;
    }
}
