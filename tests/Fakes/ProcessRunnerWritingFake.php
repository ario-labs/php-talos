<?php

declare(strict_types=1);

namespace Tests\Fakes;

use ArioLabs\Talos\Support\Runner;
use Closure;

final class ProcessRunnerWritingFake implements Runner
{
    public array $calls = [];

    public function __construct(
        private string $talosconfigContent = "kind: talosconfig\n",
        private string $cpContent = "controlplane: true\n",
        private string $wkContent = "worker: true\n",
    ) {}

    public function run(array $args, ?Closure $onChunk = null): array
    {
        $this->calls[] = $args;
        // Find output directory in args (after --output)
        $outIndex = array_search('--output', $args, true);
        if ($outIndex !== false && isset($args[$outIndex + 1])) {
            $dir = (string) $args[$outIndex + 1];
            @mkdir($dir, 0775, true);
            file_put_contents($dir.'/talosconfig', $this->talosconfigContent);
            file_put_contents($dir.'/controlplane.yaml', $this->cpContent);
            file_put_contents($dir.'/worker.yaml', $this->wkContent);
        }

        return [0, '', ''];
    }
}
