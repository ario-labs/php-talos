<?php

declare(strict_types=1);

use ArioLabs\Talos\TalosCluster;
use Tests\Fakes\ProcessRunnerWritingFake;

it('generates talosconfig in memory and cleans up temp files', function (): void {
    $runner = new ProcessRunnerWritingFake(talosconfigContent: "kind: talosconfig\nclusters: []\n");
    $talos = new TalosCluster($runner);

    $dir = sys_get_temp_dir().'/talos-tmp-test-'.uniqid();
    // Ensure directory does not exist yet
    @rmdir($dir);

    $content = $talos->genTalosconfig('demo', 'https://10.0.0.1:6443', outDir: $dir);

    expect($content)->toContain('kind: talosconfig');
    // Files should be removed by genTalosconfig
    expect(is_file($dir.'/talosconfig'))->toBeFalse();
    expect(is_file($dir.'/controlplane.yaml'))->toBeFalse();
    expect(is_file($dir.'/worker.yaml'))->toBeFalse();
    // Best-effort rmdir — directory may or may not be removed depending on FS; assert no files
});
