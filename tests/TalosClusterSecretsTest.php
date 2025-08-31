<?php

declare(strict_types=1);

use ArioLabs\Talos\TalosCluster;
use Tests\Fakes\ProcessRunnerFake;

it('genSecrets falls back to reading secrets.yaml when stdout is empty', function (): void {
    $runner = new ProcessRunnerFake(defaultOut: '');
    $talos = new TalosCluster($runner);

    // Simulate talosctl writing secrets.yaml to CWD
    $yaml = "cluster:\n  id: xyz\n";
    file_put_contents('secrets.yaml', $yaml);

    $secrets = $talos->genSecrets();
    expect($secrets->toArray()['cluster']['id'] ?? null)->toBe('xyz');
    expect(is_file('secrets.yaml'))->toBeFalse();
});

it('genSecrets parses YAML from stdout when provided', function (): void {
    $yaml = "cluster:\n  id: abc2\n";
    $runner = new ProcessRunnerFake(defaultOut: $yaml);
    $talos = new TalosCluster($runner);
    $secrets = $talos->genSecrets();
    expect($secrets->toArray()['cluster']['id'] ?? null)->toBe('abc2');
});

it('genSecrets throws on error exit', function (): void {
    $runner = new ProcessRunnerFake(defaultExit: 1, defaultErr: 'boom');
    $talos = new TalosCluster($runner);
    $talos->genSecrets();
})->throws(ArioLabs\Talos\Exceptions\CommandFailed::class);
