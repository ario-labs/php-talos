<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerWritingFake;

it('applies per-file patches in generateInMemory() and normalizes sequences', function (): void {
    // Use writer fake so talosctl gen config writes real files to temp dir
    $runner = new ProcessRunnerWritingFake();
    $talos = new TalosCluster($runner);

    $b = new ClusterBuilder($talos, 'demo', '10.0.0.1');

    // Add an empty sequence to exercise normalization + nested merge on both CP/WK
    $b->set('cluster.apiServer.certSANs', []);

    // Per-file patches with nested structures to hit recursive deepMerge
    $b->patchFile('controlplane.yaml', [
        'cluster' => [
            'network' => [
                'dnsDomain' => 'cluster.local',
            ],
        ],
    ]);
    $b->patchFile('worker.yaml', [
        'machine' => [
            'network' => [
                'nameservers' => ['1.1.1.1'],
            ],
        ],
    ]);

    $configs = $b->generateInMemory();

    $cp = Yaml::parse($configs->controlplane()) ?: [];
    $wk = Yaml::parse($configs->worker()) ?: [];

    expect($cp['cluster']['network']['dnsDomain'] ?? null)->toBe('cluster.local');
    expect($wk['machine']['network']['nameservers'] ?? [])->toBe(['1.1.1.1']);

    // certSANs was an empty sequence; it should be omitted after normalization
    expect(isset($cp['cluster']['apiServer']['certSANs']))->toBeFalse();
});
