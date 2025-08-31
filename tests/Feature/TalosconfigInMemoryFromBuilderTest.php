<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Tests\Fakes\ProcessRunnerWritingFake;

it('fetches talosconfig via builder talosconfigInMemory()', function (): void {
    $runner = new ProcessRunnerWritingFake("kind: talosconfig\n");
    $talos = new TalosCluster($runner);

    $b = new ClusterBuilder($talos, 'demo', 'https://10.0.0.1:6443');
    $cfg = $b->talosconfigInMemory();

    expect($cfg)->toContain('kind: talosconfig');
});
