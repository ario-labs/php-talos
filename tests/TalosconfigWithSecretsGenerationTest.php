<?php

declare(strict_types=1);

use ArioLabs\Talos\TalosCluster;
use ArioLabs\Talos\TalosSecrets;
use Tests\Fakes\ProcessRunnerWritingFake;

it('generates talosconfig with secrets and cleans up temp files', function (): void {
    $runner = new ProcessRunnerWritingFake(talosconfigContent: "kind: talosconfig\nclusters: []\n");
    $talos = new TalosCluster($runner);

    $dir = sys_get_temp_dir().'/talos-tmp-test-ws-'.uniqid();
    @rmdir($dir);

    $secrets = TalosSecrets::fromArray(['cluster' => ['id' => 'abc']]);

    $content = $talos->genTalosconfigWithSecrets('demo', 'https://10.0.0.1:6443', $secrets, outDir: $dir);

    expect($content)->toContain('kind: talosconfig');
    // Files should be removed by genTalosconfigWithSecrets
    expect(is_file($dir.'/talosconfig'))->toBeFalse();
    expect(is_file($dir.'/controlplane.yaml'))->toBeFalse();
    expect(is_file($dir.'/worker.yaml'))->toBeFalse();

    // It should have invoked talosctl with --with-secrets pointing to a temp file
    $withSecretsPath = null;
    foreach ($runner->calls as $call) {
        foreach ($call as $arg) {
            if (is_string($arg) && str_starts_with($arg, '--with-secrets=')) {
                $withSecretsPath = (string) mb_substr($arg, mb_strlen('--with-secrets='));
                break 2;
            }
        }
    }
    expect($withSecretsPath)->not()->toBeNull();
    // Temp secrets file should be cleaned up by finally block
    expect(is_file((string) $withSecretsPath))->toBeFalse();
});
