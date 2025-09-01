<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use ArioLabs\Talos\TalosSecrets;
use Tests\Fakes\ProcessRunnerWritingFake;

it('fetches talosconfig via builder talosconfigInMemory() when secrets are set', function (): void {
    $runner = new ProcessRunnerWritingFake("kind: talosconfig\n");
    $talos = new TalosCluster($runner);

    $secrets = TalosSecrets::fromArray(['cluster' => ['id' => 'xyz']]);

    $b = new ClusterBuilder($talos, 'demo', 'https://10.0.0.1:6443');
    $cfg = $b->secrets($secrets)->talosconfigInMemory();

    expect($cfg)->toContain('kind: talosconfig');
});
