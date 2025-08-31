<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;
use Tests\Fakes\ProcessRunnerFake;

it('configures cluster and machine networking with network()', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);

    $out = sys_get_temp_dir().'/talos-feature-net-'.uniqid();
    @mkdir($out, 0775, true);

    $builder = new ClusterBuilder($talos, 'demo', '10.0.0.1', $out);
    $builder->network(
        dns: 'cluster.local',
        pod: ['10.42.0.0/16'],
        svc: ['10.43.0.0/16'],
        nameservers: ['10.40.0.1'],
        interfaces: [[
            'name' => 'eth0',
            'addresses' => ['10.255.0.50/24'],
            'routes' => [['network' => '0.0.0.0/0', 'gateway' => '10.255.0.1']],
        ]],
    );

    $dir = $builder->generate();
    $cp = Yaml::parseFile($dir.'/controlplane.yaml');

    expect(Arr::get($cp, 'cluster.network.dnsDomain'))->toBe('cluster.local');
    expect(Arr::get($cp, 'cluster.network.podSubnets'))->toBe(['10.42.0.0/16']);
    expect(Arr::get($cp, 'cluster.network.serviceSubnets'))->toBe(['10.43.0.0/16']);
    expect(Arr::get($cp, 'machine.network.nameservers'))->toBe(['10.40.0.1']);
    expect(Arr::get($cp, 'machine.network.interfaces.0.interface'))->toBe('eth0');
    expect(Arr::get($cp, 'machine.network.interfaces.0.addresses.0'))->toBe('10.255.0.50/24');
    expect(Arr::get($cp, 'machine.network.interfaces.0.routes.0.gateway'))->toBe('10.255.0.1');
});
