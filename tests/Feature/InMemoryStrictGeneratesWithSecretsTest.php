<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use ArioLabs\Talos\TalosSecrets;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerWritingFake;

it('generateInMemory strictly produces real configs and applies secrets', function (): void {
    $runner = new ProcessRunnerWritingFake();
    $talos = new TalosCluster($runner);

    $b = new ClusterBuilder($talos, 'demo', 'https://10.0.0.1:6443');

    // Provide secrets that should appear in both machine configs
    $secrets = TalosSecrets::fromArray([
        'cluster' => [
            'id' => 'abc123',
        ],
    ]);

    $configs = $b
        ->secrets($secrets)
        ->network(dns: 'cluster.local', pod: ['10.42.0.0/16'], svc: ['10.43.0.0/16'])
        ->generateInMemory();

    $cp = Yaml::parse($configs->controlplane()) ?: [];
    $wk = Yaml::parse($configs->worker()) ?: [];

    expect($cp['cluster']['id'] ?? null)->toBe('abc123');
    expect($wk['cluster']['id'] ?? null)->toBe('abc123');
});
