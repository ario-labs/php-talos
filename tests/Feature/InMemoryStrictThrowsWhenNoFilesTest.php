<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Tests\Fakes\ProcessRunnerFake;

it('generateInMemory throws when talosctl did not produce files', function (): void {
    $runner = new ProcessRunnerFake(); // does not write files
    $talos = new TalosCluster($runner);

    $b = new ClusterBuilder($talos, 'demo', 'https://10.0.0.1:6443');

    $b->generateInMemory();
})->throws(RuntimeException::class);
