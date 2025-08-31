<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Tests\Fakes\ProcessRunnerFake;

it('validates dnsDomain and manifests and nameservers and staticInterface routes', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);
    $builder = new ClusterBuilder($talos, 'v', '10.0.0.1', sys_get_temp_dir().'/talos-val-'.uniqid());

    // dnsDomain too long (>253)
    $tooLong = str_repeat('a', 254);
    expect(fn () => $builder->dnsDomain($tooLong))->toThrow(InvalidArgumentException::class);

    // dnsDomain with a label > 63
    $longLabel = str_repeat('a', 64).'.local';
    expect(fn () => $builder->dnsDomain($longLabel))->toThrow(InvalidArgumentException::class);

    // dnsDomain invalid label characters
    expect(fn () => $builder->dnsDomain('bad_label.local'))->toThrow(InvalidArgumentException::class);

    // manifests invalid
    expect(fn () => $builder->manifests(['']))->toThrow(InvalidArgumentException::class);
    expect(fn () => $builder->manifest(''))->toThrow(InvalidArgumentException::class);

    // nameservers invalid IP
    expect(fn () => $builder->nameservers(['not-an-ip']))->toThrow(InvalidArgumentException::class);

    // staticInterface empty name
    expect(fn () => $builder->staticInterface('', ['10.0.0.1/24']))->toThrow(InvalidArgumentException::class);

    // staticInterface route missing keys
    expect(fn () => $builder->staticInterface('eth0', ['10.0.0.1/24'], [['network' => '0.0.0.0/0']]))
        ->toThrow(InvalidArgumentException::class);

    // staticInterface route non-string types
    expect(fn () => $builder->staticInterface('eth0', ['10.0.0.1/24'], [['network' => 123, 'gateway' => 456]]))
        ->toThrow(InvalidArgumentException::class);

    // staticInterface route invalid gateway IP
    expect(fn () => $builder->staticInterface('eth0', ['10.0.0.1/24'], [['network' => '0.0.0.0/0', 'gateway' => 'x']]))
        ->toThrow(InvalidArgumentException::class);

    // set() invalid path
    expect(fn () => $builder->set('', 'x'))->toThrow(InvalidArgumentException::class);
    // cover normalization by generating a config without optional sequence keys
    $dir2 = sys_get_temp_dir().'/talos-val-empty-'.uniqid();
    @mkdir($dir2, 0775, true);
    $b2 = new ClusterBuilder(new TalosCluster(new ProcessRunnerFake()), 'demo', '10.0.0.1', $dir2);
    // Write a minimal patch to force file creation and exercise YAML dump
    $b2->patchFile('controlplane.yaml', ['cluster' => ['network' => ['podSubnets' => []]]]);
    $out2 = $b2->generate();
    expect(is_file($out2.'/controlplane.yaml'))->toBeTrue();
});
