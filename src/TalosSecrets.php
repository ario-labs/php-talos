<?php

declare(strict_types=1);

namespace ArioLabs\Talos;

use Symfony\Component\Yaml\Yaml;

final class TalosSecrets
{
    /** @param array<int|string, mixed> $data */
    public function __construct(private array $data) {}

    /** @param array<int|string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function fromYaml(string $yaml): self
    {
        $parsed = Yaml::parse($yaml);

        return new self(is_array($parsed) ? $parsed : []);
    }

    public static function fromBase64Json(string $payload): self
    {
        $decoded = base64_decode($payload, true) ?: '';
        $arr = json_decode($decoded, true);

        return new self(is_array($arr) ? $arr : []);
    }

    /** @return array<int|string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function toYaml(): string
    {
        return Yaml::dump($this->data, 8, 2);
    }

    public function toBase64Json(): string
    {
        return base64_encode((string) json_encode($this->data));
    }

    /** @return array<int|string, mixed> */
    public function toPatch(): array
    {
        $d = $this->data;
        /** @var array<int|string, mixed> $cluster */
        $cluster = (isset($d['cluster']) && is_array($d['cluster'])) ? $d['cluster'] : [];
        /** @var array<int|string, mixed> $machine */
        $machine = (isset($d['machine']) && is_array($d['machine'])) ? $d['machine'] : [];

        $clusterPatch = [];
        $machinePatch = [];

        // Common cluster-level fields
        foreach (['id', 'secret', 'token', 'secretboxEncryptionSecret'] as $k) {
            if (isset($cluster[$k])) {
                $clusterPatch[$k] = $cluster[$k];
            }
        }
        // cluster.ca, aggregatorCA, serviceAccount
        foreach (['ca', 'aggregatorCA', 'serviceAccount'] as $k) {
            if (isset($cluster[$k]) && is_array($cluster[$k])) {
                $clusterPatch[$k] = $cluster[$k];
            }
        }

        // machine.ca
        if (isset($machine['ca']) && is_array($machine['ca'])) {
            $machinePatch['ca'] = $machine['ca'];
        }

        $patch = [];
        if ($clusterPatch) {
            $patch['cluster'] = $clusterPatch;
        }
        if ($machinePatch) {
            $patch['machine'] = $machinePatch;
        }

        return $patch;
    }
}
