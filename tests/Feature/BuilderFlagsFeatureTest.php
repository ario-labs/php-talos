<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\Enums\Cni;
use ArioLabs\Talos\TalosCluster;
use Tests\Fakes\ProcessRunnerFake;

it('passes expected CLI flags and avoids deprecated ones', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);

    $dir = sys_get_temp_dir().'/talos-flags-'.uniqid();
    @mkdir($dir, 0775, true);

    (new ClusterBuilder($talos, 'demo', '10.0.0.1', $dir))
        ->additionalSans(['demo.local', '10.0.0.2'])
        ->cni(Cni::Flannel)
        ->generate();

    $args = $runner->calls[0]['args'];
    expect($args)->toContain('gen', 'config', 'demo', '10.0.0.1', '--output', $dir);
    expect($args)->toContain('--additional-sans=demo.local,10.0.0.2');
    // We do not pass a non-existent --cni flag; we patch YAML instead
    expect($args)->not()->toContain('--cni=flannel');
});
