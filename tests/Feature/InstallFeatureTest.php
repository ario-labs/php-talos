<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerFake;

it('configures install disk selector and wipe flag', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);

    $out = sys_get_temp_dir().'/talos-feature-install-'.uniqid();
    @mkdir($out, 0775, true);

    $b = new ClusterBuilder($talos, 'demo', '10.0.0.1', $out);
    $b->installDiskSelector(['size' => '40GB'])->installWipe(false);

    $dir = $b->generate();
    $cp = Yaml::parseFile($dir.'/controlplane.yaml');

    expect(Arr::get($cp, 'machine.install.diskSelector.size'))->toBe('40GB');
    expect(Arr::get($cp, 'machine.install.wipe'))->toBeFalse();
});
