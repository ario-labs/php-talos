<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerFake;

it('adds single and multiple extra manifests uniquely', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);

    $out = sys_get_temp_dir().'/talos-feature-manifests-'.uniqid();
    @mkdir($out, 0775, true);

    $b = new ClusterBuilder($talos, 'demo', '10.0.0.1', $out);
    $b->manifest('https://example.com/one.yaml')
        ->manifests([
            'https://example.com/two.yaml',
            // duplicate should be uniqued
            'https://example.com/one.yaml',
        ]);

    $dir = $b->generate();
    $cp = Yaml::parseFile($dir.'/controlplane.yaml');
    $manifests = Arr::get($cp, 'cluster.extraManifests');

    expect($manifests)->toBe([
        'https://example.com/one.yaml',
        'https://example.com/two.yaml',
    ]);
});
