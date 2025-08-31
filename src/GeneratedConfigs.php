<?php

declare(strict_types=1);

namespace ArioLabs\Talos;

final class GeneratedConfigs
{
    public function __construct(
        private string $controlplane,
        private string $worker
    ) {}

    public function controlplane(): string
    {
        return $this->controlplane;
    }

    public function worker(): string
    {
        return $this->worker;
    }

    public function writeTo(string $dir): void
    {
        @mkdir($dir, 0775, true);
        file_put_contents(mb_rtrim($dir, '/').'/controlplane.yaml', $this->controlplane);
        file_put_contents(mb_rtrim($dir, '/').'/worker.yaml', $this->worker);
    }
}
