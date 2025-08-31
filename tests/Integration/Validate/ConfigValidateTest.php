<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\Support\ProcessRunner;
use ArioLabs\Talos\TalosFactory;

function validate_has_subcommand_config(): bool
{
    $help = (string) shell_exec('talosctl --help 2>/dev/null');

    return str_contains($help, 'validate config');
}

/** @return array{0:int,1:string,2:string} */
function validate_config_file_full(string $file): array
{
    $runner = new ProcessRunner('talosctl', timeout: 60, log: false);
    $mode = getenv('VALIDATE_MODE') ?: 'metal';

    $attempts = [
        ['validate', '-c', $file, '--mode', $mode],
        ['validate', '--config', $file, '--mode', $mode],
    ];
    if (validate_has_subcommand_config()) {
        $attempts[] = ['validate', 'config', '-f', $file, '--mode', $mode];
        $attempts[] = ['validate', 'config', '--config', $file, '--mode', $mode];
    }

    $firstFailure = [1, '', ''];
    foreach ($attempts as $i => $args) {
        [$c, $o, $e] = $runner->run($args);
        if ($i === 0) {
            $firstFailure = [$c, $o, $e];
        }
        if ($c === 0) {
            return [$c, $o, $e];
        }
    }

    return $firstFailure;
}

it('validates a minimal generated config', function (): void {

    $out = sys_get_temp_dir().'/talos-validate-min-'.uniqid();
    @mkdir($out, 0775, true);

    $talos = (new TalosFactory(['log' => false]))->for();
    $builder = new ClusterBuilder($talos, 'demo', 'https://10.0.0.1:6443', $out);
    $dir = $builder->generate();

    $cp = $dir.'/controlplane.yaml';
    $wk = $dir.'/worker.yaml';

    expect(is_file($cp))->toBeTrue();
    expect(is_file($wk))->toBeTrue();

    [$cCp] = validate_config_file_full($cp);
    expect($cCp)->toBe(0);
    [$cWk] = validate_config_file_full($wk);
    expect($cWk)->toBe(0);
})->group('integration');

it('validates a config with common network settings', function (): void {

    $out = sys_get_temp_dir().'/talos-validate-net-'.uniqid();
    @mkdir($out, 0775, true);

    $talos = (new TalosFactory(['log' => false]))->for();
    $builder = new ClusterBuilder($talos, 'demo', 'https://10.0.0.1:6443', $out);
    // Conservative set widely accepted by validate across versions
    $builder
        ->dnsDomain('cluster.local')
        ->podSubnets(['10.42.0.0/16'])
        ->serviceSubnets(['10.43.0.0/16']);

    $dir = $builder->generate();

    $cp = $dir.'/controlplane.yaml';
    $wk = $dir.'/worker.yaml';

    [$cCp] = validate_config_file_full($cp);
    expect($cCp)->toBe(0);
    [$cWk] = validate_config_file_full($wk);
    expect($cWk)->toBe(0);
})->group('integration');
