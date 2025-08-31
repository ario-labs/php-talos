# PHP Talos SDK

[![CI](https://github.com/ario-labs/php-talos/actions/workflows/ci.yml/badge.svg)](https://github.com/ario-labs/php-talos/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb3?logo=php)
![Talos](https://img.shields.io/badge/Talos-v1.10%20%2B%20v1.9-0ea5e9)
![License](https://img.shields.io/badge/License-MIT-green)
![Coverage](https://img.shields.io/badge/Tests-100%25-success)
[![Packagist Version](https://img.shields.io/packagist/v/ario-labs/php-talos.svg)](https://packagist.org/packages/ario-labs/php-talos)
[![Total Downloads](https://img.shields.io/packagist/dt/ario-labs/php-talos.svg)](https://packagist.org/packages/ario-labs/php-talos)

An expressive, fluent PHP SDK for generating and managing [Talos](https://www.talos.dev) machine configurations via `talosctl`. Built with a Laravel-inspired API, strong typing, and a focus on delightful DX.

- Fluent builder for common Talos configuration (networking, control plane, etc.).
- First-class secrets workflow (generate → store → regenerate on demand).
- Structured YAML patching with precedence controls.
- Mandatory integration validation with `talosctl validate` (CI-ready).

> Package: `ario-labs/php-talos`

## Why Talos?

[Talos](https://www.talos.dev) is a modern, immutable, minimal Linux distribution purpose‑built to run Kubernetes. It replaces SSH and many traditional OS subsystems with a declarative, API‑driven management plane. Pairing Talos with a fluent SDK makes it natural to:

- Generate safe, versioned machine configs per environment
- Validate those configs against multiple Talos versions in CI
- Regenerate secrets/configs on the fly for ephemeral or long‑lived clusters

## Requirements

- PHP 8.3+
- `talosctl` available in PATH for integration validation and secrets generation

## Install

```bash
composer require ario-labs/php-talos
```

## Quick Start

```php
use ArioLabs\Talos\TalosFactory;
use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\Enums\Cni;

$talos = (new TalosFactory(['log' => false]))->for();

$builder = new ClusterBuilder(
    talos: $talos,
    cluster: 'demo',
    endpoint: 'https://10.255.0.50:6443',
    // outDir is optional; prefer in-memory + writeTo() or use generateTo($dir)
);

$dir = $builder
    ->network(
        dns: 'cluster.local',
        pod: ['10.42.0.0/16'],
        svc: ['10.43.0.0/16'],
        nameservers: ['10.40.0.1'],
        interfaces: [[
            'name' => 'eth0',
            'addresses' => ['10.255.0.50/24'],
            'routes' => [['network' => '0.0.0.0/0', 'gateway' => '10.255.0.1']],
        ]],
    )
    ->cni(Cni::Flannel)
    ->additionalSans(['demo.local', '10.255.0.50'])
    ->manifests(['https://example.com/addons.yaml'])
    ->generateTo(sys_get_temp_dir().'/talos-demo-'.uniqid());

// Apply to a node (example)
$talos->nodes(['10.255.0.50'])->applyConfig($dir.'/controlplane.yaml', insecure: true);
// Fetch kubeconfig
$kubeconfigPath = $talos->endpoints(['10.255.0.50'])->kubeconfig();
```

## Secrets Workflow (Generate → Store → Regenerate)

Generate Talos secrets once, store them securely (e.g., encrypt in DB), and regenerate machine configs on demand without keeping config files on disk.

```php
use ArioLabs\Talos\TalosSecrets;

$talos = (new TalosFactory(['log' => false]))->for();

// 1) Generate secrets
$secrets = $talos->genSecrets();

// 2) Serialize for storage (encrypt this in your app)
$payload = $secrets->toBase64Json();

// ... later: retrieve and decrypt
$secrets = TalosSecrets::fromBase64Json($payload);

// 3) Regenerate configs with secrets
$builder = new ClusterBuilder($talos, 'demo', 'https://10.255.0.50:6443');
$dir = $builder
    ->secrets($secrets)
    ->network(dns: 'cluster.local', pod: ['10.42.0.0/16'], svc: ['10.43.0.0/16'])
    ->generate();
```

### In‑Memory Generation

Obtain the generated YAML as strings, and only write to disk if/when you need to persist or apply them later. Under the hood, this uses a temporary directory to invoke `talosctl gen config`, applies your patches, then cleans up. Requires `talosctl` in PATH; errors from `talosctl` bubble as `ArioLabs\\Talos\\Exceptions\\CommandFailed`, and a `RuntimeException` is thrown if expected files are not produced.

```php
use ArioLabs\Talos\Builders\ClusterBuilder;
use ArioLabs\Talos\GeneratedConfigs;

$builder = new ClusterBuilder($talos, 'demo', 'https://10.255.0.50:6443');

$configs = $builder
    ->secrets($secrets)
    ->network(dns: 'cluster.local', pod: ['10.42.0.0/16'], svc: ['10.43.0.0/16'])
    ->generateInMemory();

// YAML strings
$controlplaneYaml = $configs->controlplane();
$workerYaml = $configs->worker();

// Optionally persist later
$out = sys_get_temp_dir().'/talos-out-'.uniqid();
$configs->writeTo($out);

// Also get an in-memory talosconfig (not persisted; uses a temp dir under the hood)
$talosconfig = $builder->talosconfigInMemory();
```

### Laravel Workflow (Secrets → In‑Memory Configs → talosconfig)

Below is an example of the intended application flow when using this package inside a Laravel app. Secrets are generated once, encrypted and stored by your app, and later used to regenerate configs and a talosconfig entirely in memory.

```php
use ArioLabs\Talos\TalosFactory;
use ArioLabs\Talos\TalosSecrets;
use ArioLabs\Talos\Builders\ClusterBuilder;

// 1) Generate secrets and store (encrypt before saving)
$talos = (new TalosFactory(['log' => false]))->for();
$secrets = $talos->genSecrets();
$payload = $secrets->toBase64Json(); // encrypt this string with app key, store in DB

// ... later in a request/job: load + decrypt
$secrets = TalosSecrets::fromBase64Json($payload);

// 2) Regenerate configs in memory
$builder = new ClusterBuilder($talos, 'demo', 'https://10.255.0.50:6443');
$configs = $builder
    ->secrets($secrets)
    ->network(dns: 'cluster.local', pod: ['10.42.0.0/16'], svc: ['10.43.0.0/16'])
    ->generateInMemory();

$controlplaneYaml = $configs->controlplane();
$workerYaml = $configs->worker();

// 3) Obtain a talosconfig (in memory) for subsequent operations
$talosconfig = $builder->talosconfigInMemory();

// Optionally persist if needed
$dir = storage_path('talos/'.uniqid());
$configs->writeTo($dir);
file_put_contents($dir.'/talosconfig', $talosconfig);
```

## Fluent Builder Reference

- Network & CNI
  - `network(?string $dns, array $pod, array $svc, array $nameservers, array $interfaces)`
  - `cni(Cni $cni)` → emits `cluster.network.cni: { name: ... }`
- Names & SANs
  - `additionalSans(array $sans)` → CLI flag `--additional-sans=...`
- Manifests
  - `manifest(string $url)` / `manifests(array $urls)` (unique, accumulative)
- Machine networking
  - `nameservers(array $ips)`
  - `staticInterface(string $name, array $addresses, array $routes = [], bool $dhcp = false)`
- Install
  - `installDiskSelector(array $selector)`
  - `installWipe(bool $wipe = true)`
- Control plane
  - `apiServerImage(string $image)` / `apiServerArgs(array $args)` / `apiServerCertSANs(array $sans)`
  - `controllerManagerArgs(array $args)`
  - `schedulerArgs(array $args)` / `schedulerConfig(array $config)`
- etcd
  - `etcdAdvertisedSubnets(array $cidrs)` / `etcdExtraArgs(array $args)`
- Patching & helpers
  - `set(string $dotPath, scalar|array|null $value)` → deep merge via dot path
  - `patchBoth(array $patch)` → merge into both machine configs
  - `patchFile(string $relativeYaml, array $patch)` → per-file overrides
- Secrets
  - `secrets(TalosSecrets $secrets)` → injects cluster/machine secrets
  - `generateInMemory()` → returns `GeneratedConfigs` (uses a temp dir internally; throws on failure)
  - `talosconfigInMemory(array $flags = [])` → return talosconfig content as a string
  - `generateTo(string $dir)` → generate configs to a specific directory
  - Constructor `outDir` is optional; if omitted, `generate()` uses a temp directory

## Patching Precedence

1) Generated configs (with or without secrets)
2) Machine-level patches (from builder helpers: network, control plane, etc.) applied to both `controlplane.yaml` and `worker.yaml`
3) `patchFile()` per-file overrides last

## Integration Validation (talosctl)

This project enforces validation via `talosctl validate`:

```bash
# Validate generated machine configs
talosctl validate -c controlplane.yaml --mode metal
talosctl validate -c worker.yaml       --mode metal
```

Our CI matrix runs validation against Talos current and prior minor versions.

## Laravel Integration

- The SDK ships with a service provider and a simple factory.
- In Laravel, publish config as needed and bind `TalosFactory` from the container.
- Secrets are easy to store: `TalosSecrets::toBase64Json()` → encrypt with app key → save. Regenerate configs with `secrets()` on demand.

## Testing

- Unit + Feature tests: 100% coverage, semantic YAML assertions (no brittle substrings).
- Integration tests: `talosctl validate` is required and runs in CI across versions.

Run locally:

```bash
composer test
```

## Version Support & CI

- PHP: 8.3, 8.4
- Talos: current (v1.10.x) + prior (v1.9.x) in CI

| Matrix | PHP | Talos |
|---|---|---|
| CI | 8.3, 8.4 | v1.10.7, v1.9.6 |

## SemVer & 1.0.0

- Pre‑1.0 breaking changes may occur; the current API is frozen for 1.0.0.
- After 1.0.0, standard SemVer applies.

## Contributing

- Run the full suite locally: `composer test`
- Follow the fluent API style for new helpers.
- Integration tests require `talosctl` in PATH (see CI matrix versions).
- Please include feature tests that read like usage examples.

## Changelog

All notable changes will be documented in [CHANGELOG.md](CHANGELOG.md) after 1.0.0. Prior to 1.0.0, see Git history and PRs.

## License

MIT

---

If you have ideas for presets (e.g., `ciliumPreset()`), open an issue — the fluent API makes it easy to add joyful one-liners that stay strictly type-safe.
