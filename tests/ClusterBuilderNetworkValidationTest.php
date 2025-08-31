<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Tests\Fakes\ProcessRunnerFake;

it('validates CIDRs for pod and service subnets', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);
    $builder = new ClusterBuilder($talos, 'demo', '10.0.0.1', sys_get_temp_dir().'/talos-builder-'.uniqid());

    // Non-string entry
    $fn1 = function () use ($builder): void {
        $builder->podSubnets(['10.244.0.0/16', 123]);
    };
    expect($fn1)->toThrow(InvalidArgumentException::class);

    // Invalid CIDR format
    $fn2 = function () use ($builder): void {
        $builder->serviceSubnets(['10.96.0.0']);
    };
    expect($fn2)->toThrow(InvalidArgumentException::class);
});
