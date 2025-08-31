<?php

declare(strict_types=1);

namespace Tests\Fakes;

use ArioLabs\Talos\Support\Runner;
use Closure;

final class ProcessRunnerFake implements Runner
{
    public array $calls = [];

    private array $queue = [];

    public function __construct(
        public int $defaultExit = 0,
        public string $defaultOut = '',
        public string $defaultErr = '',
    ) {}

    public function pushResult(int $exit, string $out = '', string $err = ''): void
    {
        $this->queue[] = compact('exit', 'out', 'err');
    }

    public function run(array $args, ?Closure $onChunk = null): array
    {
        $this->calls[] = ['args' => $args, 'streamed' => (bool) $onChunk];
        $result = $this->queue ? array_shift($this->queue) : null;
        [$exit,$out,$err] = $result ? [$result['exit'], $result['out'], $result['err']]
                                    : [$this->defaultExit, $this->defaultOut, $this->defaultErr];

        if ($onChunk && $out) {
            foreach (explode("\n", $out) as $line) {
                $onChunk('out', $line."\n");
            }
        }

        return [$exit, $out, $err];
    }
}
