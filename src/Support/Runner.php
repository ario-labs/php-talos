<?php

declare(strict_types=1);

namespace ArioLabs\Talos\Support;

use Closure;

interface Runner
{
    /** Run the underlying command with optional streaming callback.
     *  @param  array<int, string>  $args
     *  @return array{0:int,1:string,2:string} */
    public function run(array $args, ?Closure $onChunk = null): array;
}
