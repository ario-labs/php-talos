<?php

declare(strict_types=1);

use ArioLabs\Talos\Facades\Talos;
use ArioLabs\Talos\TalosFactory;

it('facade accessor returns TalosFactory class', function (): void {
    $ref = new ReflectionClass(Talos::class);
    $m = $ref->getMethod('getFacadeAccessor');
    $m->setAccessible(true);
    $val = $m->invoke(null);
    expect($val)->toBe(TalosFactory::class);
});
