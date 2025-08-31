<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\Support\ProcessRunner;
use ArioLabs\Talos\TalosFactory;

/** @return array{0:int,1:string,2:string} */
function validate_config_file_full_local(string $file): array
{
    $runner = new ProcessRunner('talosctl', timeout: 60, log: false);
    $mode = getenv('VALIDATE_MODE') ?: 'metal';
    [$c1, $o1, $e1] = $runner->run(['validate', '-c', $file, '--mode', $mode]);
    if ($c1 === 0) {
        return [$c1, $o1, $e1];
    }
    [$c2, $o2, $e2] = $runner->run(['validate', '--config', $file, '--mode', $mode]);

    return [$c2, $o2, $e2];
}

it('generates secrets and validates config using them', function (): void {
    $out = sys_get_temp_dir().'/talos-validate-secrets-'.uniqid();
    @mkdir($out, 0775, true);

    $talos = (new TalosFactory(['log' => false]))->for();
    $secrets = $talos->genSecrets();

    $builder = new ClusterBuilder($talos, 'demo', 'https://10.0.0.1:6443', $out);
    $builder->secrets($secrets);
    $dir = $builder->generate();

    $cp = $dir.'/controlplane.yaml';
    $wk = $dir.'/worker.yaml';

    [$cCp] = validate_config_file_full_local($cp);
    expect($cCp)->toBe(0);
    [$cWk] = validate_config_file_full_local($wk);
    expect($cWk)->toBe(0);
})->group('integration');
