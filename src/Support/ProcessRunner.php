<?php

declare(strict_types=1);

namespace ArioLabs\Talos\Support;

use Closure;
use Symfony\Component\Process\Process;

final class ProcessRunner implements Runner
{
    public function __construct(
        private string $bin,
        private int $timeout = 120,
        private bool $log = true,
        private ?string $cwd = null,
        /** @var array<string, string> */
        private array $env = [],
    ) {}

    /** @param  array<int, string>  $args
     *  @return array{0:int,1:string,2:string} */
    public function run(array $args, ?Closure $onChunk = null): array
    {
        $cmd = array_merge([$this->bin], $args);
        $proc = new Process($cmd, $this->cwd, $this->env, null, $this->timeout);

        if ($onChunk) {
            $proc->run(fn (string $type, string $buffer) => $onChunk($type, $buffer));
        } else {
            $proc->run();
        }

        $exit = $proc->getExitCode() ?? 0;
        $out = $proc->getOutput();
        $err = $proc->getErrorOutput();

        if ($this->log && function_exists('logger')) {
            logger()->debug('[talosctl]'.implode(' ', $cmd), compact('exit', 'out', 'err'));
        }

        return [$exit, $out, $err];
    }
}
