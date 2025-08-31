<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerFake;

it('configures etcd advertised subnets and extra args', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);

    $out = sys_get_temp_dir().'/talos-feature-etcd-'.uniqid();
    @mkdir($out, 0775, true);

    $b = new ClusterBuilder($talos, 'demo', '10.0.0.1', $out);
    $b->etcdAdvertisedSubnets(['10.255.0.0/24'])->etcdExtraArgs(['listen-metrics-urls' => 'http://0.0.0.0:2381']);

    $dir = $b->generate();
    $cp = Yaml::parseFile($dir.'/controlplane.yaml');

    expect(Arr::get($cp, 'cluster.etcd.advertisedSubnets'))->toBe(['10.255.0.0/24']);
    expect(Arr::get($cp, 'cluster.etcd.extraArgs.listen-metrics-urls'))->toBe('http://0.0.0.0:2381');
});
