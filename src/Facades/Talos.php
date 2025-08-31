<?php

declare(strict_types=1);

namespace ArioLabs\Talos\Facades;

use ArioLabs\Talos\TalosFactory;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \ArioLabs\Talos\TalosCluster for(?string $talosconfig = null)
 * @method static \ArioLabs\Talos\TalosCluster cluster(?string $name = null)
 */
final class Talos extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TalosFactory::class;
    }
}
