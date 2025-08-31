<?php

declare(strict_types=1);

namespace ArioLabs\Talos\Support {
    final class LoggerSpy
    {
        public static array $last = [];

        public function debug(string $msg, array $context = []): void
        {
            self::$last = compact('msg', 'context');
        }
    }

    function logger(): LoggerSpy
    {
        return new LoggerSpy();
    }
}

namespace {
    use ArioLabs\Talos\Support\ProcessRunner;

    it('runs a process and returns output', function (): void {
        $runner = new ProcessRunner(bin: PHP_BINARY, timeout: 5, log: false);
        [$code, $out, $err] = $runner->run(['-r', 'echo "hello";']);

        expect($code)->toBe(0);
        expect($out)->toBe('hello');
        expect($err)->toBe('');
    });

    it('streams chunk output when callback provided', function (): void {
        $runner = new ProcessRunner(bin: PHP_BINARY, timeout: 5, log: false);
        $chunks = [];
        [$code, $out, $err] = $runner->run(['-r', 'fwrite(STDOUT, "a\n"); fwrite(STDERR, "b\n");'], function (string $type, string $buf) use (&$chunks): void {
            $chunks[] = [$type, $buf];
        });

        expect($code)->toBe(0);
        expect($out)->toContain('a');
        expect($err)->toContain('b');
        // We expect at least 2 streamed chunks
        expect(count($chunks))->toBeGreaterThanOrEqual(2);
    });

    it('logs debug output when logger helper exists', function (): void {
        $runner = new ProcessRunner(bin: PHP_BINARY, timeout: 5, log: true);
        [$code] = $runner->run(['-r', 'echo "x";']);
        expect($code)->toBe(0);
        expect(ArioLabs\Talos\Support\LoggerSpy::$last['msg'])->toStartWith('[talosctl]');
    });

}
