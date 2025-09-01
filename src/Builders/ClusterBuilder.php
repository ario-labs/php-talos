<?php

declare(strict_types=1);

namespace ArioLabs\Talos\Builders;

use ArioLabs\Talos\Enums\Cni;
use ArioLabs\Talos\TalosCluster;
use InvalidArgumentException;
use RuntimeException;

final class ClusterBuilder
{
    /** @var array<int|string, string|bool> */
    private array $flags = [];

    /** @var array<string, array<string, mixed>> */
    private array $patches = [];

    /** @var array<int|string, mixed> */
    private array $machinePatches = [];

    /** @var array<int, array<string, mixed>> */
    private array $interfaces = [];

    /** @var array<int, string> */
    private array $extraManifests = [];

    private ?\ArioLabs\Talos\TalosSecrets $secrets = null;

    public function __construct(
        private readonly TalosCluster $talos,
        private readonly string $cluster,
        private readonly string $endpoint,
        private readonly ?string $outDir = null,
    ) {}

    /** @param  array<int, string>  $sans */
    public function additionalSans(array $sans): static
    {
        if ($sans !== []) {
            $this->flags['--additional-sans'] = implode(',', $sans);
        }

        return $this;
    }

    // Clean, fluent QoL helpers -------------------------------------------------

    public function cni(Cni $cni): static
    {
        // Use object form { name: <value> } to match Talos schema
        $this->mergeMachinePatch([
            'cluster' => [
                'network' => [
                    'cni' => [
                        'name' => $cni->value,
                    ],
                ],
            ],
        ]);

        return $this;
    }

    /** @param  array<string, mixed>  $patch */
    public function patchFile(string $relativeYaml, array $patch): static
    {
        $this->patches[$relativeYaml] = $patch;

        return $this;
    }

    public function secrets(\ArioLabs\Talos\TalosSecrets $secrets): static
    {
        $this->secrets = $secrets;

        return $this;
    }

    /** @param  array<int, string>  $cidrs */
    public function podSubnets(array $cidrs): static
    {
        $this->validateCidrs($cidrs, 'podSubnets');
        if ($cidrs !== []) {
            $this->mergeMachinePatch([
                'cluster' => [
                    'network' => [
                        'podSubnets' => array_values($cidrs),
                    ],
                ],
            ]);
        }

        return $this;
    }

    /** @param  array<int, string>  $cidrs */
    public function serviceSubnets(array $cidrs): static
    {
        $this->validateCidrs($cidrs, 'serviceSubnets');
        if ($cidrs !== []) {
            $this->mergeMachinePatch([
                'cluster' => [
                    'network' => [
                        'serviceSubnets' => array_values($cidrs),
                    ],
                ],
            ]);
        }

        return $this;
    }

    public function dnsDomain(string $domain): static
    {
        if (! $this->isValidDnsName($domain)) {
            throw new InvalidArgumentException('Invalid DNS domain: '.$domain);
        }
        $this->mergeMachinePatch([
            'cluster' => [
                'network' => [
                    'dnsDomain' => $domain,
                ],
            ],
        ]);

        return $this;
    }

    public function withoutKubeProxy(bool $disabled = true): static
    {
        $this->mergeMachinePatch([
            'cluster' => [
                'proxy' => [
                    'disabled' => $disabled,
                ],
            ],
        ]);

        return $this;
    }

    public function withoutCoreDNS(bool $disabled = true): static
    {
        $this->mergeMachinePatch([
            'cluster' => [
                'coreDNS' => [
                    'disabled' => $disabled,
                ],
            ],
        ]);

        return $this;
    }

    /** @param  array<int, string>  $urls */
    public function manifests(array $urls): static
    {
        foreach ($urls as $i => $url) {
            if ($url === '') {
                throw new InvalidArgumentException(sprintf('extraManifests[%d] must be a non-empty string URL', $i));
            }
        }
        $this->extraManifests = array_values(array_unique(array_merge($this->extraManifests, array_values($urls))));
        $this->mergeMachinePatch([
            'cluster' => [
                'extraManifests' => $this->extraManifests,
            ],
        ]);

        return $this;
    }

    public function manifest(string $url): static
    {
        if ($url === '') {
            throw new InvalidArgumentException('Manifest URL must be non-empty string.');
        }

        return $this->manifests([$url]);
    }

    /** @param  array<int, string>  $ips */
    public function nameservers(array $ips): static
    {
        foreach ($ips as $i => $ip) {
            if (! $this->isValidIp($ip)) {
                throw new InvalidArgumentException(sprintf('nameservers[%d] must be a valid IP', $i));
            }
        }
        $this->mergeMachinePatch([
            'machine' => [
                'network' => [
                    'nameservers' => array_values($ips),
                ],
            ],
        ]);

        return $this;
    }

    /** @param  array<int, string>  $addresses  CIDRs like 10.0.0.1/24
     *  @param  array<int, array{network:mixed,gateway:mixed}>  $routes */
    public function staticInterface(string $name, array $addresses, array $routes = [], bool $dhcp = false): static
    {
        if (mb_trim($name) === '') {
            throw new InvalidArgumentException('interface name must be non-empty');
        }
        $this->validateCidrs($addresses, 'interfaces.addresses');
        foreach ($routes as $i => $route) {
            if (! isset($route['network'], $route['gateway'])) {
                throw new InvalidArgumentException(sprintf('routes[%d] must contain network and gateway', $i));
            }
            if (! is_string($route['network']) || ! is_string($route['gateway'])) {
                throw new InvalidArgumentException(sprintf('routes[%d] network and gateway must be strings', $i));
            }
            // network may be CIDR, gateway must be IP
            if (! $this->isValidIp($route['gateway'])) {
                throw new InvalidArgumentException(sprintf('routes[%d].gateway must be valid IP', $i));
            }
        }

        $entry = [
            'interface' => $name,
            'dhcp' => $dhcp,
            'addresses' => array_values($addresses),
        ];
        if ($routes !== []) {
            $entry['routes'] = array_values($routes);
        }
        $this->interfaces[] = $entry;
        $this->mergeMachinePatch([
            'machine' => [
                'network' => [
                    'interfaces' => $this->interfaces,
                ],
            ],
        ]);

        return $this;
    }

    /** @param array<string, mixed> $selector */
    public function installDiskSelector(array $selector): static
    {
        $this->mergeMachinePatch([
            'machine' => [
                'install' => [
                    'diskSelector' => $selector,
                ],
            ],
        ]);

        return $this;
    }

    public function installWipe(bool $wipe = true): static
    {
        $this->mergeMachinePatch([
            'machine' => [
                'install' => [
                    'wipe' => $wipe,
                ],
            ],
        ]);

        return $this;
    }

    /** One-stop network configuration. Any argument can be omitted.
     *  @param  array<int, string>  $pod
     *  @param  array<int, string>  $svc
     *  @param  array<int, string>  $nameservers
     *  @param  array<int, array<string, mixed>>  $interfaces */
    public function network(
        ?string $dns = null,
        array $pod = [],
        array $svc = [],
        array $nameservers = [],
        array $interfaces = []
    ): static {
        if ($dns !== null) {
            $this->dnsDomain($dns);
        }
        if ($pod !== []) {
            $this->podSubnets($pod);
        }
        if ($svc !== []) {
            $this->serviceSubnets($svc);
        }
        if ($nameservers !== []) {
            $this->nameservers($nameservers);
        }
        foreach ($interfaces as $i => $cfg) {
            if (! isset($cfg['name'], $cfg['addresses'])) {
                throw new InvalidArgumentException(sprintf('interfaces[%d] must include name and addresses', $i));
            }
            $name = $cfg['name'];
            $addresses = $cfg['addresses'];
            $routes = $cfg['routes'] ?? [];
            $dhcp = (bool) ($cfg['dhcp'] ?? false);

            if (! is_string($name)) {
                throw new InvalidArgumentException(sprintf('interfaces[%d].name must be string', $i));
            }
            if (! is_array($addresses)) {
                throw new InvalidArgumentException(sprintf('interfaces[%d].addresses must be array of CIDR strings', $i));
            }
            foreach ($addresses as $j => $a) {
                if (! is_string($a)) {
                    throw new InvalidArgumentException(sprintf('interfaces[%d].addresses[%d] must be string', $i, $j));
                }
            }
            if (! is_array($routes)) {
                throw new InvalidArgumentException(sprintf('interfaces[%d].routes must be array when provided', $i));
            }
            foreach ($routes as $j => $r) {
                if (! is_array($r) || ! isset($r['network'], $r['gateway']) || ! is_string($r['network']) || ! is_string($r['gateway'])) {
                    throw new InvalidArgumentException(sprintf('interfaces[%d].routes[%d] must be {network:string,gateway:string}', $i, $j));
                }
            }

            /** @var array<int, string> $addresses */
            /** @var array<int, array{network: mixed, gateway: mixed}> $routes */
            $this->staticInterface($name, $addresses, $routes, $dhcp);
        }

        return $this;
    }

    /** Merge an associative patch into both machine configs.
     *  @param  array<int|string, mixed>  $patch */
    public function patchBoth(array $patch): static
    {
        $this->mergeMachinePatch($patch);

        return $this;
    }

    /** Dot-path setter that merges into both machine configs. Example: set('cluster.apiServer.extraArgs.feature-gates', 'X=Y')
     *  @param  array<int|string, mixed>|string|int|float|bool|null  $value */
    public function set(string $path, string|int|float|bool|array|null $value): static
    {
        $this->mergeMachinePatch($this->buildNestedPatchFromDot($path, $value));

        return $this;
    }

    /** @param array<string, string> $args */
    public function apiServerArgs(array $args): static
    {
        $this->mergeMachinePatch([
            'cluster' => [
                'apiServer' => [
                    'extraArgs' => $args,
                ],
            ],
        ]);

        return $this;
    }

    public function apiServerImage(string $image): static
    {
        $this->mergeMachinePatch([
            'cluster' => [
                'apiServer' => [
                    'image' => $image,
                ],
            ],
        ]);

        return $this;
    }

    /** @param array<int, string> $sans */
    public function apiServerCertSANs(array $sans): static
    {
        $this->mergeMachinePatch([
            'cluster' => [
                'apiServer' => [
                    'certSANs' => array_values($sans),
                ],
            ],
        ]);

        return $this;
    }

    /** @param array<string, string> $args */
    public function controllerManagerArgs(array $args): static
    {
        $this->mergeMachinePatch([
            'cluster' => [
                'controllerManager' => [
                    'extraArgs' => $args,
                ],
            ],
        ]);

        return $this;
    }

    /** @param array<string, string> $args */
    public function schedulerArgs(array $args): static
    {
        $this->mergeMachinePatch([
            'cluster' => [
                'scheduler' => [
                    'extraArgs' => $args,
                ],
            ],
        ]);

        return $this;
    }

    /** @param array<int|string, mixed> $config */
    public function schedulerConfig(array $config): static
    {
        $this->mergeMachinePatch([
            'cluster' => [
                'scheduler' => [
                    'config' => $config,
                ],
            ],
        ]);

        return $this;
    }

    /** @param array<int, string> $cidrs */
    public function etcdAdvertisedSubnets(array $cidrs): static
    {
        $this->validateCidrs($cidrs, 'cluster.etcd.advertisedSubnets');
        $this->mergeMachinePatch([
            'cluster' => [
                'etcd' => [
                    'advertisedSubnets' => array_values($cidrs),
                ],
            ],
        ]);

        return $this;
    }

    /** @param array<string, string> $args */
    public function etcdExtraArgs(array $args): static
    {
        $this->mergeMachinePatch([
            'cluster' => [
                'etcd' => [
                    'extraArgs' => $args,
                ],
            ],
        ]);

        return $this;
    }

    public function generate(): string
    {
        $dir = $this->outDir ?? mb_rtrim(sys_get_temp_dir(), '/').'/talos-gen-'.uniqid();

        return $this->generateTo($dir);
    }

    /** Generate configs to a specific directory (explicit disk output). */
    public function generateTo(string $dir): string
    {
        if ($this->secrets instanceof \ArioLabs\Talos\TalosSecrets) {
            $dir = $this->talos->genConfigWithSecrets($this->cluster, $this->endpoint, $dir, $this->secrets, $this->flags);
        } else {
            $dir = $this->talos->genConfig($this->cluster, $this->endpoint, $dir, $this->flags);
        }
        // Apply accumulated machine-level patches (e.g., network settings) to both configs first
        if ($this->machinePatches !== []) {
            $this->talos->patchYaml($dir.'/controlplane.yaml', $this->machinePatches);
            $this->talos->patchYaml($dir.'/worker.yaml', $this->machinePatches);
        }
        foreach ($this->patches as $file => $patch) {
            $this->talos->patchYaml($dir.'/'.$file, $patch);
        }

        return $dir;
    }

    public function generateInMemory(): \ArioLabs\Talos\GeneratedConfigs
    {
        // Strict mode: always generate via talosctl to ensure real Talos configs.
        $tmp = mb_rtrim(sys_get_temp_dir(), '/').'/talos-inmem-'.uniqid();
        @mkdir($tmp, 0775, true);

        $dir = $tmp;
        try {
            if ($this->secrets instanceof \ArioLabs\Talos\TalosSecrets) {
                $dir = $this->talos->genConfigWithSecrets($this->cluster, $this->endpoint, $tmp, $this->secrets, $this->flags);
            } else {
                $dir = $this->talos->genConfig($this->cluster, $this->endpoint, $tmp, $this->flags);
            }

            $cpPath = $dir.'/controlplane.yaml';
            $wkPath = $dir.'/worker.yaml';

            if (! is_file($cpPath) || ! is_file($wkPath)) {
                throw new RuntimeException('talosctl gen config did not produce controlplane.yaml/worker.yaml');
            }

            if ($this->machinePatches !== []) {
                $this->talos->patchYaml($cpPath, $this->machinePatches);
                $this->talos->patchYaml($wkPath, $this->machinePatches);
            }
            foreach ($this->patches as $file => $patch) {
                $target = $dir.'/'.$file;
                if (is_file($target)) {
                    $this->talos->patchYaml($target, $patch);
                }
            }

            $cpYaml = (string) file_get_contents($cpPath);
            $wkYaml = (string) file_get_contents($wkPath);

            return new \ArioLabs\Talos\GeneratedConfigs($cpYaml, $wkYaml);
        } finally {
            // Best-effort cleanup of temp artifacts
            $cp = $dir.'/controlplane.yaml';
            $wk = $dir.'/worker.yaml';
            if (is_file($cp)) {
                @unlink($cp);
            }
            if (is_file($wk)) {
                @unlink($wk);
            }
            $tc = $dir.'/talosconfig';
            if (is_file($tc)) {
                @unlink($tc);
            }
            @rmdir($dir);
        }
    }

    /** Convenience: generate a talosconfig string for this builder's
     *  cluster/endpoint using a temporary gen-config run (no persistence).
     *
     * @param  array<int|string, string|bool>  $flags
     */
    public function talosconfigInMemory(array $flags = []): string
    {
        // Merge builder flags with per-call flags (per-call overrides by key)
        $mergedFlags = $this->flags;
        foreach ($flags as $k => $v) {
            $mergedFlags[$k] = $v;
        }

        if ($this->secrets instanceof \ArioLabs\Talos\TalosSecrets) {
            return $this->talos->genTalosconfigWithSecrets($this->cluster, $this->endpoint, $this->secrets, $mergedFlags);
        }

        return $this->talos->genTalosconfig($this->cluster, $this->endpoint, $mergedFlags);
    }

    /** @param  array<int|string, mixed>  $patch */
    private function mergeMachinePatch(array $patch): void
    {
        $this->machinePatches = array_replace_recursive($this->machinePatches, $patch);
    }

    /** Build nested array from dot path.
     *  @param  array<int|string, mixed>|string|int|float|bool|null  $value
     *  @return array<int|string, mixed> */
    private function buildNestedPatchFromDot(string $path, string|int|float|bool|array|null $value): array
    {
        $keys = array_filter(explode('.', $path), static fn (string $k): bool => $k !== '');
        if ($keys === []) {
            throw new InvalidArgumentException('set() path must be non-empty');
        }

        $patch = $value;
        for ($i = count($keys) - 1; $i >= 0; $i--) {
            $patch = [$keys[$i] => $patch];
        }

        return $patch;
    }

    /** @param  array<int, mixed>  $cidrs */
    private function validateCidrs(array $cidrs, string $field): void
    {
        foreach ($cidrs as $i => $cidr) {
            if (! is_string($cidr)) {
                throw new InvalidArgumentException(sprintf('%s[%d] must be a string CIDR', $field, $i));
            }
            // Basic CIDR validation for IPv4 or IPv6
            $isIpv4 = (bool) preg_match('/^((25[0-5]|2[0-4]\\d|[01]?\\d?\\d)(\\.)){3}(25[0-5]|2[0-4]\\d|[01]?\\d?\\d)\/(3[0-2]|[12]?\\d)$/', $cidr);
            $isIpv6 = (bool) preg_match('/^([0-9a-fA-F]{0,4}:){1,7}[0-9a-fA-F]{0,4}\/(\d|[1-9]\d|1[01]\d|12[0-8])$/', $cidr);
            if (! $isIpv4 && ! $isIpv6) {
                throw new InvalidArgumentException(sprintf('Invalid CIDR "%s" for %s', $cidr, $field));
            }
        }
    }

    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private function isValidDnsName(string $name): bool
    {
        // RFC 1123-ish hostname (letters, digits, hyphen; labels 1-63; total <= 253)
        if (mb_strlen($name) > 253) {
            return false;
        }
        $labels = explode('.', $name);
        foreach ($labels as $label) {
            if ($label === '' || mb_strlen($label) > 63) {
                return false;
            }
            if (in_array(preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?$/', $label), [0, false], true)) {
                return false;
            }
        }

        return true;
    }
}
