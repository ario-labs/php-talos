<?php

declare(strict_types=1);

namespace ArioLabs\Talos;

use ArioLabs\Talos\Support\ProcessRunner;

final class TalosFactory
{
    /** @param  array<string, mixed>  $config */
    public function __construct(private array $config) {}

    public function for(?string $talosconfig = null): TalosCluster
    {
        return new TalosCluster(
            runner: $this->makeRunner(),
            talosconfig: $talosconfig,
        );
    }

    public function cluster(?string $name = null): TalosCluster
    {
        return $this->for()->clusterName($name);
    }

    private function makeRunner(): ProcessRunner
    {
        $bin = isset($this->config['bin']) && is_string($this->config['bin'])
            ? $this->config['bin']
            : 'talosctl';
        $timeout = isset($this->config['timeout']) && is_int($this->config['timeout'])
            ? $this->config['timeout']
            : 120;
        $log = isset($this->config['log']) ? (bool) $this->config['log'] : true;
        $cwd = isset($this->config['workdir']) && is_string($this->config['workdir'])
            ? $this->config['workdir']
            : null;

        return new ProcessRunner(
            bin: $bin,
            timeout: $timeout,
            log: $log,
            cwd: $cwd,
        );
    }
}
