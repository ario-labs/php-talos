<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Tests\Fakes\ProcessRunnerFake;

// Covered semantically in tests/Feature/NetworkFeatureTest.php

it('validates network() interfaces shape', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);
    $out = sys_get_temp_dir().'/talos-net-invalid-'.uniqid();
    @mkdir($out, 0775, true);

    $b = new ClusterBuilder($talos, 'net', '10.0.0.1', $out);
    $fn = function () use ($b): void {
        $b->network(interfaces: [[
            // missing name triggers validation error
            'addresses' => ['10.0.0.2/24'],
        ]]);
    };

    expect($fn)->toThrow(InvalidArgumentException::class);
});

it('validates network() interface field types', function (): void {
    $runner = new ProcessRunnerFake();
    $talos = new TalosCluster($runner);
    $out = sys_get_temp_dir().'/talos-net-invalid2-'.uniqid();
    @mkdir($out, 0775, true);
    $b = new ClusterBuilder($talos, 'net', '10.0.0.1', $out);

    // name must be string
    expect(fn () => $b->network(interfaces: [[
        'name' => 123,
        'addresses' => ['10.0.0.2/24'],
    ]]))->toThrow(InvalidArgumentException::class);

    // addresses must be array
    expect(fn () => $b->network(interfaces: [[
        'name' => 'eth0',
        'addresses' => 'oops',
    ]]))->toThrow(InvalidArgumentException::class);

    // addresses entries must be string
    expect(fn () => $b->network(interfaces: [[
        'name' => 'eth0',
        'addresses' => [123],
    ]]))->toThrow(InvalidArgumentException::class);

    // routes must be array if provided
    expect(fn () => $b->network(interfaces: [[
        'name' => 'eth0',
        'addresses' => ['10.0.0.2/24'],
        'routes' => 'bad',
    ]]))->toThrow(InvalidArgumentException::class);

    // routes entries must be proper shape
    expect(fn () => $b->network(interfaces: [[
        'name' => 'eth0',
        'addresses' => ['10.0.0.2/24'],
        'routes' => [['network' => 123, 'gateway' => '10.0.0.1']],
    ]]))->toThrow(InvalidArgumentException::class);
});
