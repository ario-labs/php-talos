<?php

declare(strict_types=1);

use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\TalosCluster;
use Tests\Fakes\ProcessRunnerWritingFake;

it('applies per-call flags override when generating talosconfig in memory', function (): void {
    $runner = new ProcessRunnerWritingFake("kind: talosconfig\n");
    $talos = new TalosCluster($runner);

    $b = new ClusterBuilder($talos, 'demo', 'https://10.0.0.1:6443');
    // Set a builder-level flag
    $b->additionalSans(['initial.local']);

    // Per-call flag should override the builder flag
    $cfg = $b->talosconfigInMemory(['--additional-sans' => 'override.local']);
    expect($cfg)->toContain('kind: talosconfig');

    $args = $runner->calls[0] ?? [];
    $hasOverride = false;
    foreach ($args as $arg) {
        if (is_string($arg) && $arg === '--additional-sans=override.local') {
            $hasOverride = true;
            break;
        }
    }
    expect($hasOverride)->toBeTrue();
});
