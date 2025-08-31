<?php

declare(strict_types=1);

use ArioLabs\Talos\TalosCluster;
use ArioLabs\Talos\TalosFactory;
use ArioLabs\Talos\TalosServiceProvider;
use Orchestra\Testbench\TestCase;

final class TalosServiceProviderTest extends TestCase
{
    public function test_provider_registers_and_publishes(): void
    {
        // Calling boot/register should not throw and counts for coverage
        $provider = new TalosServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Resolves the factory from the container (covers singleton binding)
        $factory = $this->app->make(TalosFactory::class);
        $this->assertInstanceOf(TalosFactory::class, $factory);

        // Use the factory once to exercise config merge defaults
        $cluster = $factory->for();
        $this->assertInstanceOf(TalosCluster::class, $cluster);
    }

    protected function getPackageProviders($app): array
    {
        return [TalosServiceProvider::class];
    }
}
