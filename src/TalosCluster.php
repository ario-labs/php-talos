<?php

declare(strict_types=1);

namespace ArioLabs\Talos;

use ArioLabs\Talos\Exceptions\CommandFailed;
use ArioLabs\Talos\Support\Runner;
use Closure;
use Symfony\Component\Yaml\Yaml;

final class TalosCluster
{
    public function __construct(
        private Runner $runner,
        private ?string $talosconfig = null,
        private ?string $clusterName = null,
        /** @var array<int, string> */
        private array $nodes = [],
        /** @var array<int, string> */
        private array $endpoints = [],
    ) {}

    public function talosconfig(?string $path): static
    {
        $this->talosconfig = $path;

        return $this;
    }

    public function clusterName(?string $name): static
    {
        $this->clusterName = $name;

        return $this;
    }

    public function getClusterName(): ?string
    {
        return $this->clusterName;
    }

    /** @param  array<int, string>  $ips */
    public function nodes(array $ips): static
    {
        $this->nodes = array_values($ips);

        return $this;
    }

    /** @param  array<int, string>  $ips */
    public function endpoints(array $ips): static
    {
        $this->endpoints = array_values($ips);

        return $this;
    }

    /** @param  array<int|string, string|bool>  $flags */
    public function genConfig(string $cluster, string $endpoint, string $outputDir, array $flags = []): string
    {
        $args = ['gen', 'config', $cluster, $endpoint, '--output', $outputDir];
        foreach ($flags as $k => $v) {
            if (is_int($k)) {
                if (is_string($v)) {
                    $args[] = $v;
                }

                continue;
            }
            if (is_bool($v)) {
                if ($v) {
                    $args[] = $k;
                }

                continue;
            }
            $args[] = $k.'='.$v;
        }
        [$c,$o,$e] = $this->run($args);
        if ($c !== 0) {
            throw new CommandFailed($args, $c, $e);
        }

        return mb_rtrim($outputDir, '/');

    }

    /** @param  array<int|string, mixed>  $data */
    public function writeYaml(array $data, string $path): string
    {
        @mkdir(dirname($path), 0775, true);
        $normalized = $this->normalizeSequences($data);
        file_put_contents($path, Yaml::dump($normalized, 8, 2));

        return $path;
    }

    /** @param  array<int|string, mixed>  $patch */
    public function patchYaml(string $path, array $patch): string
    {
        $parsed = file_exists($path) ? Yaml::parseFile($path) : [];
        $base = is_array($parsed) ? $parsed : [];
        $merged = $this->deepMerge($base, $patch);
        $normalized = $this->normalizeSequences($merged);
        file_put_contents($path, Yaml::dump($normalized, 8, 2));

        return $path;
    }

    public function applyConfig(string $yamlPath, bool $insecure = false): void
    {
        $args = array_merge(['apply-config', '-f', $yamlPath], $this->nodeFlags(), $insecure ? ['--insecure'] : []);
        [$c,$o,$e] = $this->run($args);
        if ($c !== 0) {
            throw new CommandFailed($args, $c, $e);
        }
    }

    public function kubeconfig(?string $outPath = null, bool $force = true): string
    {
        $args = array_merge(['kubeconfig'], $this->endpointFlags());
        if ($force) {
            $args[] = '--force';
        }
        if ($outPath) {
            $args[] = '-f';
            $args[] = $outPath;
        }
        [$c,$o,$e] = $this->run($args);
        if ($c !== 0) {
            throw new CommandFailed($args, $c, $e);
        }

        return $outPath ?: mb_trim($o);
    }

    public function upgrade(string $image, bool $reboot = true): void
    {
        $args = array_merge(['upgrade', '--image', $image], $this->nodeFlags());
        if ($reboot) {
            $args[] = '--reboot';
        }
        [$c,$o,$e] = $this->run($args);
        if ($c !== 0) {
            throw new CommandFailed($args, $c, $e);
        }
    }

    /**
     * @param  array<int, string>  $extra
     * @return array<int|string, mixed>|string
     */
    public function get(string $resource, array $extra = [], bool $json = false): string|array
    {
        $args = array_merge(['get', $resource], $this->endpointFlags(), $extra);
        if ($json) {
            $args[] = '-ojson';
        }
        [$c,$o,$e] = $this->run($args);
        if ($c !== 0) {
            throw new CommandFailed($args, $c, $e);
        }

        if (! $json) {
            return $o;
        }
        /** @var array<int|string, mixed>|null $decoded */
        $decoded = json_decode($o, true);

        return $decoded ?? [];
    }

    public function logs(string $service, ?Closure $onChunk = null): int
    {
        $args = array_merge(['logs', $service], $this->nodeFlags());

        [$code] = $this->run($args, $onChunk);

        return $code;
    }

    public function version(): string
    {
        [$c,$o,$e] = $this->run(['version']);
        if ($c !== 0) {
            throw new CommandFailed(['version'], $c, $e);
        }

        return $o;
    }

    public function genSecrets(): TalosSecrets
    {
        [$c, $out, $err] = $this->run(['gen', 'secrets', '--force']);
        if ($c !== 0) {
            throw new CommandFailed(['gen', 'secrets'], $c, $err);
        }
        $data = [];
        $parsed = Yaml::parse($out);
        if (is_array($parsed)) {
            $data = $parsed;
        } elseif (is_file('secrets.yaml')) {
            $fileParsed = Yaml::parseFile('secrets.yaml');
            $data = is_array($fileParsed) ? $fileParsed : [];
            @unlink('secrets.yaml');
        }

        return TalosSecrets::fromArray($data);
    }

    /** Generate a talosconfig and return its contents as a string.
     *  Uses a temporary directory; no persistent files are left behind.
     *  @param  array<int|string, string|bool>  $flags
     */
    public function genTalosconfig(string $cluster, string $endpoint, array $flags = []): string
    {
        $tmp = rtrim(sys_get_temp_dir(), '/').'/talos-tmp-'.uniqid();
        @mkdir($tmp, 0775, true);

        // Reuse genConfig to invoke talosctl with any flags provided.
        $dir = $this->genConfig($cluster, $endpoint, $tmp, $flags);
        $path = $dir.'/talosconfig';

        $content = is_file($path) ? (string) file_get_contents($path) : '';

        // Best-effort cleanup
        if (is_file($path)) {
            @unlink($path);
        }
        // Remove other files if present
        foreach (['controlplane.yaml', 'worker.yaml'] as $f) {
            if (is_file($dir.'/'.$f)) {
                @unlink($dir.'/'.$f);
            }
        }
        @rmdir($dir);

        return $content;
    }

    /** @param array<int|string, string|bool> $flags */
    public function genConfigWithSecrets(string $cluster, string $endpoint, string $outputDir, TalosSecrets $secrets, array $flags = []): string
    {
        $dir = $this->genConfig($cluster, $endpoint, $outputDir, $flags);
        $patch = $secrets->toPatch();
        if ($patch) {
            $this->patchYaml($dir.'/controlplane.yaml', $patch);
            $this->patchYaml($dir.'/worker.yaml', $patch);
        }

        return $dir;
    }

    /** @param  array<int, string>  $args
     *  @return array{0:int,1:string,2:string} */
    private function run(array $args, ?Closure $onChunk = null): array
    {
        $args = array_merge($this->talosFlags(), $args);

        return $this->runner->run($args, $onChunk);
    }

    /** @return array<int, string> */
    private function talosFlags(): array
    {
        $flags = [];
        if ($this->talosconfig) {
            $flags[] = '--talosconfig='.$this->talosconfig;
        }

        return $flags;
    }

    /** @return array<int, string> */
    private function nodeFlags(): array
    {
        return $this->nodes ? ['--nodes', implode(',', $this->nodes)] : [];
    }

    /** @return array<int, string> */
    private function endpointFlags(): array
    {
        return $this->endpoints ? ['--endpoints', implode(',', $this->endpoints)] : [];
    }

    /** @param  array<int|string, mixed>  $a
     *  @param  array<int|string, mixed>  $b
     *  @return array<int|string, mixed> */
    private function deepMerge(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            if (is_array($v) && isset($a[$k]) && is_array($a[$k])) {
                $a[$k] = $this->deepMerge($a[$k], $v);
            } else {
                $a[$k] = $v;
            }
        }

        return $a;
    }

    /** Normalize known sequence-typed keys: if empty, omit them so the YAML dumper doesn't emit '{}'.
     *  Keeps empty map-typed keys (e.g., registries: {}) untouched.
     *
     *  @param  array<int|string, mixed>  $data
     *  @return array<int|string, mixed> */
    private function normalizeSequences(array $data): array
    {
        // Keys that are sequences in Talos schema when present.
        $sequenceKeys = [
            'certSANs',
            'extraManifests',
            'inlineManifests',
            'podSubnets',
            'serviceSubnets',
            'advertisedSubnets',
            'nameservers',
            'interfaces',
        ];

        foreach ($data as $k => $v) {
            if (is_array($v)) {
                if ($v === [] && in_array((string) $k, $sequenceKeys, true)) {
                    // Omit empty sequences so the dumper doesn't render '{}' for them.
                    unset($data[$k]);

                    continue;
                }
                // Recurse for nested structures
                $data[$k] = $this->normalizeSequences($v);
            }
        }

        return $data;
    }
}
