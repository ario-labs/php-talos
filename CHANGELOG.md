# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2025-08-31

### Fixed
- `ClusterBuilder::generateInMemory()` no longer delegates to disk generation; now builds YAML purely in memory.

### Added
- `TalosCluster::genTalosconfig(string $cluster, string $endpoint, array $flags = [])` to generate and return a `talosconfig` string using a temporary `talosctl gen config` run (no persistence).
- `ClusterBuilder::talosconfigInMemory(array $flags = [])` convenience wrapper for fetching an in‑memory `talosconfig` for the builder’s cluster/endpoint.
- `ClusterBuilder::generateTo(string $dir)` to explicitly generate configs to a target directory.

### Changed
- `ClusterBuilder` constructor `outDir` parameter is now optional (`?string`); when omitted, `generate()` uses a temporary directory under the system temp path.
- README updated to recommend in-memory generation + `GeneratedConfigs::writeTo()` and to document `generateTo()`.

## [1.0.0] - YYYY-MM-DD

Initial stable release.

### Added
- Fluent builder API for Talos machine configuration (networking, control plane, etcd, install helpers).
- Secrets workflow: `TalosCluster::genSecrets()`, `TalosSecrets` (serialize/deserialize), `ClusterBuilder::secrets(...)` injection.
- YAML patching helpers: `set()`, `patchBoth()`, `patchFile()` with documented precedence.
- Integration validation with `talosctl validate`; CI matrix for Talos current + prior minor.
- Laravel integration: `TalosFactory`, service provider, facade.

### Quality
- 100% test coverage, PHPStan clean, Pint formatted.
