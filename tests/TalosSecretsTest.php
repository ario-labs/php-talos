<?php

declare(strict_types=1);

use ArioLabs\Talos\TalosSecrets;

it('serializes and deserializes secrets and builds a patch', function (): void {
    $arr = [
        'cluster' => [
            'id' => 'abc',
            'secret' => 'def',
            'token' => 'ghi',
            'secretboxEncryptionSecret' => 'jkl',
            'ca' => ['crt' => 'C', 'key' => 'K'],
            'aggregatorCA' => ['crt' => 'AC', 'key' => 'AK'],
            'serviceAccount' => ['key' => 'SAK'],
        ],
        'machine' => [
            'ca' => ['crt' => 'MC', 'key' => 'MK'],
        ],
    ];

    $secrets = TalosSecrets::fromArray($arr);
    expect($secrets->toArray())->toBe($arr);
    $yaml = $secrets->toYaml();
    expect($yaml)->toContain('cluster:', 'machine:', 'id: abc');
    $fromYaml = TalosSecrets::fromYaml($yaml);
    expect($fromYaml->toArray())->toBe($arr);

    $round = TalosSecrets::fromBase64Json($secrets->toBase64Json());
    expect($round->toArray())->toBe($arr);

    $patch = $secrets->toPatch();
    expect($patch['cluster']['id'] ?? null)->toBe('abc');
    expect($patch['cluster']['ca']['crt'] ?? null)->toBe('C');
    expect($patch['machine']['ca']['key'] ?? null)->toBe('MK');
});
