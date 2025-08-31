<?php

declare(strict_types=1);

namespace ArioLabs\Talos\Exceptions;

use RuntimeException;

final class CommandFailed extends RuntimeException
{
    /** @param  array<int, string>  $args */
    public function __construct(public array $args, public int $exit, public string $stderr)
    {
        parent::__construct('talosctl failed (exit '.$exit.'): '.$stderr);
    }
}
