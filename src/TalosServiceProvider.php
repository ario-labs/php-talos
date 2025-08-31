<?php

declare(strict_types=1);

namespace ArioLabs\Talos;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class TalosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/talosctl.php', 'talosctl');

        $this->app->singleton(TalosFactory::class, function (Application $app): TalosFactory {
            $repo = $app->make(ConfigRepository::class);
            /** @var array<string, mixed> $config */
            $config = (array) $repo->get('talosctl', []);

            return new TalosFactory(config: $config);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/talosctl.php' => config_path('talosctl.php'),
        ], 'config');
    }
}
