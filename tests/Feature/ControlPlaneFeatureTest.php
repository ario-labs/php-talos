<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerFake;

it('customizes control plane component images, args, and cert SANs', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);

    $out = sys_get_temp_dir().'/talos-feature-cp-'.uniqid();
    @mkdir($out, 0775, true);

    $b = new ClusterBuilder($talos, 'demo', '10.0.0.1', $out);
    $b->apiServerImage('registry.k8s.io/kube-apiserver:v1.33.3')
        ->apiServerArgs(['bind-address' => '0.0.0.0'])
        ->apiServerCertSANs(['127.0.0.1', '10.255.0.50'])
        ->controllerManagerArgs(['bind-address' => '0.0.0.0'])
        ->schedulerArgs(['bind-address' => '0.0.0.0'])
        ->schedulerConfig(['apiVersion' => 'kubescheduler.config.k8s.io/v1']);

    $dir = $b->generate();
    $cp = Yaml::parseFile($dir.'/controlplane.yaml');

    expect(Arr::get($cp, 'cluster.apiServer.image'))->toBe('registry.k8s.io/kube-apiserver:v1.33.3');
    expect(Arr::get($cp, 'cluster.apiServer.extraArgs.bind-address'))->toBe('0.0.0.0');
    expect(Arr::get($cp, 'cluster.apiServer.certSANs'))->toBe(['127.0.0.1', '10.255.0.50']);
    expect(Arr::get($cp, 'cluster.controllerManager.extraArgs.bind-address'))->toBe('0.0.0.0');
    expect(Arr::get($cp, 'cluster.scheduler.extraArgs.bind-address'))->toBe('0.0.0.0');
    expect(Arr::get($cp, 'cluster.scheduler.config.apiVersion'))->toBe('kubescheduler.config.k8s.io/v1');
});
