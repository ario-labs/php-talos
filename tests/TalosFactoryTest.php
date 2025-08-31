<?php

declare(strict_types=1);

use ArioLabs\Talos\TalosCluster;
use ArioLabs\Talos\TalosFactory;

it('creates clusters via factory', function (): void {
    $factory = new TalosFactory(config: [
        'bin' => 'talosctl',
        'timeout' => 3,
        'log' => false,
    ]);

    $cluster = $factory->for('/tmp/talos.yaml');
    expect($cluster)->toBeInstanceOf(TalosCluster::class);

    $cluster2 = $factory->cluster('demo');
    expect($cluster2)->toBeInstanceOf(TalosCluster::class);
});

it('uses defaults when config values are missing', function (): void {
    $factory = new TalosFactory(config: []);
    $cluster = $factory->for();
    expect($cluster)->toBeInstanceOf(TalosCluster::class);
});

it('honors provided workdir', function (): void {
    $factory = new TalosFactory(config: [
        'workdir' => sys_get_temp_dir(),
    ]);
    $cluster = $factory->for();
    expect($cluster)->toBeInstanceOf(TalosCluster::class);
});
