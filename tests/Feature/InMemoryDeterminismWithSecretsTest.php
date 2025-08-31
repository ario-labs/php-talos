<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use ArioLabs\Talos\TalosSecrets;
use Tests\Fakes\ProcessRunnerWritingFake;

it('generates identical YAML on repeated in-memory runs with same secrets', function (): void {
    $runner = new ProcessRunnerWritingFake();
    $talos = new TalosCluster($runner);

    $builder = new ClusterBuilder($talos, 'demo', 'https://10.0.0.1:6443');

    // Fixed secrets payload we expect to drive deterministic generation
    $secrets = TalosSecrets::fromArray([
        'cluster' => [
            'id' => 'static-cluster-id',
            'secret' => 'static-cluster-secret',
        ],
        'machine' => [
            'ca' => [
                'crt' => 'CERTDATA',
                'key' => 'KEYDATA',
            ],
        ],
    ]);

    // First generation
    $configs1 = $builder
        ->secrets($secrets)
        ->generateInMemory();

    // Second generation with same builder/secrets
    $configs2 = $builder->generateInMemory();

    // Ensure we passed secrets through to talosctl via --with-secrets
    $firstArgs = $runner->calls[0] ?? [];
    $hasWithSecrets = false;
    foreach ($firstArgs as $arg) {
        if (is_string($arg) && str_starts_with($arg, '--with-secrets=')) {
            $hasWithSecrets = true;
            break;
        }
    }
    expect($hasWithSecrets)->toBeTrue();

    // Deterministic: resulting YAML strings are identical between runs
    expect($configs1->controlplane())->toBe($configs2->controlplane());
    expect($configs1->worker())->toBe($configs2->worker());
});
