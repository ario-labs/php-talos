<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\Enums\Cni;
use ArioLabs\Talos\TalosCluster;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerFake;

it('selects the CNI via enum and patches both machine configs', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);

    $out = sys_get_temp_dir().'/talos-feature-cni-'.uniqid();
    @mkdir($out, 0775, true);

    $builder = new ClusterBuilder($talos, 'demo', '10.0.0.1', $out);
    $builder->cni(Cni::Flannel);

    $dir = $builder->generate();

    $cp = Yaml::parseFile($dir.'/controlplane.yaml');
    $wk = Yaml::parseFile($dir.'/worker.yaml');

    expect($cp['cluster']['network']['cni']['name'] ?? null)->toBe('flannel');
    expect($wk['cluster']['network']['cni']['name'] ?? null)->toBe('flannel');
});
