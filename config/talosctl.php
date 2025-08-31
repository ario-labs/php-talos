<?php

declare(strict_types=1);

return [
    'bin' => env('TALOSCTL_BIN', 'talosctl'),
    'log' => (bool) env('TALOSCTL_LOG', true),
    'timeout' => (int) env('TALOSCTL_TIMEOUT', 120),
    'workdir' => env('TALOSCTL_WORKDIR') ?: null,
];
