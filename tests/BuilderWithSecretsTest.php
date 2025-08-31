<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use ArioLabs\Talos\TalosSecrets;
use Tests\Fakes\ProcessRunnerFake;

it('applies secrets patch when generating config', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);

    $out = sys_get_temp_dir().'/talos-secrets-'.uniqid();
    @mkdir($out, 0775, true);

    $secrets = TalosSecrets::fromArray([
        'cluster' => [
            'id' => 'abc',
            'ca' => ['crt' => 'C', 'key' => 'K'],
        ],
        'machine' => [
            'ca' => ['crt' => 'MC', 'key' => 'MK'],
        ],
    ]);

    $builder = new ClusterBuilder($talos, 'demo', '10.0.0.1', $out);
    $builder->secrets($secrets);
    $dir = $builder->generate();

    $cp = file_get_contents($dir.'/controlplane.yaml');
    expect($cp)->toContain('id: abc', 'crt: C', 'key: K');
});
