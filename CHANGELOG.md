# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - YYYY-MM-DD

Initial stable release.

- Fluent builder API for Talos machine configuration
- Secrets generation + injection (TalosSecrets; TalosCluster::genSecrets, ClusterBuilder::secrets)
- YAML sequence normalization to avoid `{}` where lists are expected
- Integration validation with `talosctl validate` (required), CI matrix for Talos current + prior
- 100 0.000000e+00st coverage, PHPStan clean, Pint clean
