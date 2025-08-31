<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\GeneratedConfigs;
use ArioLabs\Talos\TalosCluster;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerWritingFake;

it('generates in-memory configs from builder patches', function (): void {
    $runner = new ProcessRunnerWritingFake();
    $talos = new TalosCluster($runner);

    $out = sys_get_temp_dir().'/talos-generated-inmem-'.uniqid();
    @mkdir($out, 0775, true);

    $b = new ClusterBuilder($talos, 'demo', '10.0.0.1', $out);
    $b->set('cluster.network.dnsDomain', 'cluster.local')
        ->set('cluster.network.podSubnets', ['10.244.0.0/16'])
        ->set('machine.network.nameservers', ['1.1.1.1']);

    $configs = $b->generateInMemory();
    expect($configs)->toBeInstanceOf(GeneratedConfigs::class);

    // Parse YAML strings to verify content
    $cp = Yaml::parse($configs->controlplane());
    $wk = Yaml::parse($configs->worker());

    expect($cp['cluster']['network']['dnsDomain'] ?? null)->toBe('cluster.local');
    expect($cp['cluster']['network']['podSubnets'] ?? [])->toBe(['10.244.0.0/16']);
    expect($wk['machine']['network']['nameservers'] ?? [])->toBe(['1.1.1.1']);
});

it('writes generated configs to disk via writeTo()', function (): void {
    $configs = new GeneratedConfigs("controlplane: true\n", "worker: true\n");

    $dir = sys_get_temp_dir().'/talos-generated-write-'.uniqid();
    $configs->writeTo($dir);

    $cpPath = $dir.'/controlplane.yaml';
    $wkPath = $dir.'/worker.yaml';

    expect(is_file($cpPath))->toBeTrue();
    expect(is_file($wkPath))->toBeTrue();
    expect(file_get_contents($cpPath))->toBe("controlplane: true\n");
    expect(file_get_contents($wkPath))->toBe("worker: true\n");
});
