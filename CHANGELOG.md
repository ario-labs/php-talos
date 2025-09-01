# Changelog

All notable changes to this project will be documented in this file.

## [1.1.2] - 2025-08-31

### Fixed
- `TalosCluster::genConfigWithSecrets()` now invokes `talosctl gen config` with `--with-secrets` using a temporary secrets YAML, ensuring deterministic config generation when reusing the same `TalosSecrets`. The method still applies the patch from `TalosSecrets::toPatch()` idempotently after generation.

### Tests
- Added `tests/Feature/InMemoryDeterminismWithSecretsTest.php` to verify that generating in memory twice with the same secrets yields identical YAML and that `--with-secrets` is passed through.

### Notes
- No public API changes. README remains accurate; secrets workflow and usage stay the same.

## [1.1.1] - 2025-08-30

### Fixed
- `ClusterBuilder::generateInMemory()` now generates real Talos machine configs by invoking `talosctl gen config` in a temporary directory, applying patches, reading YAML back, and cleaning up. This fixes prior behavior introduced in 1.1.0 that produced patch-shaped YAML not guaranteed to be valid Talos configs.

### Notes
- Exceptions: errors from `talosctl` surface as `ArioLabs\\Talos\\Exceptions\\CommandFailed`; a `RuntimeException` is thrown if `controlplane.yaml`/`worker.yaml` are not produced.

## [1.1.0] - 2025-08-30

### Fixed
- `ClusterBuilder::generateInMemory()` no longer delegates to disk generation; now builds YAML purely in memory.

### Added
- `TalosCluster::genTalosconfig(string $cluster, string $endpoint, array $flags = [])` to generate and return a `talosconfig` string using a temporary `talosctl gen config` run (no persistence).
- `ClusterBuilder::talosconfigInMemory(array $flags = [])` convenience wrapper for fetching an in‑memory `talosconfig` for the builder’s cluster/endpoint.
- `ClusterBuilder::generateTo(string $dir)` to explicitly generate configs to a target directory.

### Changed
- `ClusterBuilder` constructor `outDir` parameter is now optional (`?string`); when omitted, `generate()` uses a temporary directory under the system temp path.
- README updated to recommend in-memory generation + `GeneratedConfigs::writeTo()` and to document `generateTo()`.

## [1.0.0] - 2025-08-30

Initial stable release.

### Added
- Fluent builder API for Talos machine configuration (networking, control plane, etcd, install helpers).
- Secrets workflow: `TalosCluster::genSecrets()`, `TalosSecrets` (serialize/deserialize), `ClusterBuilder::secrets(...)` injection.
- YAML patching helpers: `set()`, `patchBoth()`, `patchFile()` with documented precedence.
- Integration validation with `talosctl validate`; CI matrix for Talos current + prior minor.
- Laravel integration: `TalosFactory`, service provider, facade.

### Quality
- 100% test coverage, PHPStan clean, Pint formatted.
