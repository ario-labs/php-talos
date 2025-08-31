<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerFake;

it('applies set() and patchBoth(), and allows patchFile() to override per-file', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);

    $out = sys_get_temp_dir().'/talos-feature-patch-'.uniqid();
    @mkdir($out, 0775, true);

    $b = new ClusterBuilder($talos, 'demo', '10.0.0.1', $out);
    $b->set('cluster.apiServer.extraArgs.feature-gates', 'X=Y')
        ->patchBoth(['cluster' => ['controllerManager' => ['extraArgs' => ['bind-address' => '0.0.0.0']]]])
        ->patchFile('worker.yaml', ['cluster' => ['apiServer' => ['extraArgs' => ['feature-gates' => 'OVERRIDDEN']]]]);

    $dir = $b->generate();

    $cp = Yaml::parseFile($dir.'/controlplane.yaml');
    $wk = Yaml::parseFile($dir.'/worker.yaml');

    // set() visible in both files
    expect(Arr::get($cp, 'cluster.apiServer.extraArgs.feature-gates'))->toBe('X=Y');
    expect(Arr::get($wk, 'cluster.apiServer.extraArgs.feature-gates'))->toBe('OVERRIDDEN');

    // patchBoth() visible in both files
    expect(Arr::get($cp, 'cluster.controllerManager.extraArgs.bind-address'))->toBe('0.0.0.0');
    expect(Arr::get($wk, 'cluster.controllerManager.extraArgs.bind-address'))->toBe('0.0.0.0');
});
