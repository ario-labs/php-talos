<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerFake;

it('disables kube-proxy and CoreDNS for CNI alternatives', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);

    $out = sys_get_temp_dir().'/talos-feature-toggles-'.uniqid();
    @mkdir($out, 0775, true);

    $b = new ClusterBuilder($talos, 'demo', '10.0.0.1', $out);
    $b->withoutKubeProxy()->withoutCoreDNS();

    $dir = $b->generate();
    $cp = Yaml::parseFile($dir.'/controlplane.yaml');

    expect(Arr::get($cp, 'cluster.proxy.disabled'))->toBeTrue();
    expect(Arr::get($cp, 'cluster.coreDNS.disabled'))->toBeTrue();
});
