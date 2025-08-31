<?php

declare(strict_types=1);

use ArioLabs\Talos\Exceptions\CommandFailed;
use ArioLabs\Talos\TalosCluster;
use Tests\Fakes\ProcessRunnerFake;

it('generates config with flags and trims output dir', function (): void {
    $runner = new ProcessRunnerFake();
    $cluster = new TalosCluster($runner);

    $outDir = sys_get_temp_dir().'/talos-tests-'.uniqid().'/subdir/';
    $dir = $cluster->genConfig('my-cluster', '10.0.0.1', $outDir, [
        '--foo',
        '--toggle' => true,
        '--cni' => 'flannel',
    ]);

    // Compare with a trailing slash appended to avoid rtrim and satisfy mb_str_functions rule
    expect($dir.'/')->toBe($outDir);
    // Ensure the command was built as expected
    expect($runner->calls)->toHaveCount(1);
    $args = $runner->calls[0]['args'];
    expect($args)->toContain('gen', 'config', 'my-cluster', '10.0.0.1', '--output', $outDir);
    expect($args)->toContain('--foo', '--toggle', '--cni=flannel');
});

it('throws on failed genConfig', function (): void {
    $runner = new ProcessRunnerFake(defaultExit: 1, defaultErr: 'bad');
    $cluster = new TalosCluster($runner);

    $cluster->genConfig('c', 'e', sys_get_temp_dir().'/x');
})->throws(CommandFailed::class);

it('writes and patches yaml', function (): void {
    $runner = new ProcessRunnerFake();
    $cluster = new TalosCluster($runner);

    $dir = sys_get_temp_dir().'/talos-tests-'.uniqid();
    $file = $dir.'/cfg.yaml';

    $cluster->writeYaml(['a' => ['b' => 1]], $file);
    expect(is_file($file))->toBeTrue();

    $cluster->patchYaml($file, ['a' => ['c' => 2], 'd' => 3]);
    $yaml = file_get_contents($file);
    expect($yaml)->toContain('b: 1', 'c: 2', 'd: 3');
});

it('applies config and throws on error', function (): void {
    $runner = new ProcessRunnerFake();
    $cluster = new TalosCluster($runner);
    $yaml = sys_get_temp_dir().'/talos-tests-'.uniqid().'/cfg.yaml';
    @mkdir(dirname($yaml), 0775, true);
    file_put_contents($yaml, 'x: y');

    $cluster->nodes(['1.1.1.1']);
    $cluster->applyConfig($yaml, insecure: true);
    // First call should include flags
    $args = $runner->calls[array_key_last($runner->calls)]['args'];
    expect($args)->toContain('apply-config', '-f', $yaml, '--nodes', '1.1.1.1', '--insecure');

    $runner = new ProcessRunnerFake(defaultExit: 1, defaultErr: 'nope');
    $cluster = new TalosCluster($runner);
    $cluster->applyConfig($yaml);
})->throws(CommandFailed::class);

it('retrieves kubeconfig path', function (): void {
    $runner = new ProcessRunnerFake(defaultOut: "/tmp/kc\n");
    $cluster = new TalosCluster($runner);
    $path = $cluster->kubeconfig();
    expect($path)->toBe('/tmp/kc');

    $runner = new ProcessRunnerFake();
    $cluster = new TalosCluster($runner);
    $out = sys_get_temp_dir().'/kubeconfig-'.uniqid();
    $path2 = $cluster->kubeconfig($out, force: true);
    expect($path2)->toBe($out);
    $args = $runner->calls[0]['args'];
    expect($args)->toContain('kubeconfig', '--force', '-f', $out);
});

it('kubeconfig throws on error', function (): void {
    $runner = new ProcessRunnerFake(defaultExit: 1, defaultErr: 'boom');
    $cluster = new TalosCluster($runner);
    $cluster->kubeconfig();
})->throws(CommandFailed::class);

it('gets resources with and without json', function (): void {
    $runner = new ProcessRunnerFake(defaultOut: '{"a":1}');
    $cluster = new TalosCluster($runner);
    $out = $cluster->get('machineconfig');
    expect($out)->toBe('{"a":1}');

    $runner = new ProcessRunnerFake(defaultOut: '{"a":1}');
    $cluster = new TalosCluster($runner);
    $arr = $cluster->get('machineconfig', json: true);
    expect($arr)->toBe(['a' => 1]);
});

it('streams logs and returns exit code', function (): void {
    $runner = new ProcessRunnerFake(defaultOut: "line1\nline2\n");
    $cluster = new TalosCluster($runner);
    $seen = [];
    $code = $cluster->logs('apiserver', function (string $type, string $buf) use (&$seen): void {
        $seen[] = [$type, $buf];
    });

    expect($code)->toBe(0);
    expect(count($seen))->toBeGreaterThanOrEqual(2);
    expect($runner->calls[0]['streamed'])->toBeTrue();
});

it('returns version or throws on error', function (): void {
    $runner = new ProcessRunnerFake(defaultOut: 'v1.2.3');
    $cluster = new TalosCluster($runner);
    expect($cluster->version())->toBe('v1.2.3');

    $runner = new ProcessRunnerFake(defaultExit: 1, defaultErr: 'x');
    $cluster = new TalosCluster($runner);
    $cluster->version();
})->throws(CommandFailed::class);

it('upgrades with and without reboot flag', function (): void {
    $runner = new ProcessRunnerFake();
    $cluster = new TalosCluster($runner);
    $cluster->nodes(['1.2.3.4']);

    $cluster->upgrade('ghcr.io/talos-systems/installer:v1.0.0');
    $args1 = $runner->calls[0]['args'];
    expect($args1)->toContain('upgrade', '--image', 'ghcr.io/talos-systems/installer:v1.0.0', '--nodes', '1.2.3.4', '--reboot');

    $runner = new ProcessRunnerFake();
    $cluster = new TalosCluster($runner);
    $cluster->nodes(['1.2.3.4']);
    $cluster->upgrade('ghcr.io/talos-systems/installer:v1.0.0', reboot: false);
    $args2 = $runner->calls[0]['args'];
    expect($args2)->toContain('upgrade', '--image', 'ghcr.io/talos-systems/installer:v1.0.0', '--nodes', '1.2.3.4');
    expect($args2)->not()->toContain('--reboot');
});

it('get throws on error', function (): void {
    $runner = new ProcessRunnerFake(defaultExit: 1, defaultErr: 'nope');
    $cluster = new TalosCluster($runner);
    $cluster->get('machineconfig');
})->throws(CommandFailed::class);

it('upgrade throws on error', function (): void {
    $runner = new ProcessRunnerFake(defaultExit: 1, defaultErr: 'bad');
    $cluster = new TalosCluster($runner);
    $cluster->upgrade('img:v');
})->throws(CommandFailed::class);

it('includes talosconfig and endpoint/node flags when set', function (): void {
    $runner = new ProcessRunnerFake();
    $cluster = (new TalosCluster($runner))
        ->talosconfig('/path/talos.yaml')
        ->nodes(['10.0.0.1', '10.0.0.2'])
        ->endpoints(['10.0.0.3']);

    $cluster->get('machineconfig', extra: ['--verbose']);
    $args = $runner->calls[0]['args'];
    expect($args[0])->toBe('--talosconfig=/path/talos.yaml');
    expect($args)->toContain('get', 'machineconfig', '--endpoints', '10.0.0.3', '--verbose');
});

it('allows setting clusterName for fluency', function (): void {
    $runner = new ProcessRunnerFake();
    $cluster = new TalosCluster($runner);
    // Just call the method to exercise the code path
    $ret = $cluster->clusterName('demo');
    expect($ret)->toBeInstanceOf(TalosCluster::class);
    expect($cluster->getClusterName())->toBe('demo');
});
