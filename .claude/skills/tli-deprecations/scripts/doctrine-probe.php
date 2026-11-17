<?php
/**
 * Loaded before everything else (auto_prepend_file) by `deprecations.py doctrine`: it makes Doctrine report every
 * deprecation, every time, and records where the project called it from.
 *
 * It must not load Composer's autoloader: vendor/autoload_runtime.php returns at once when autoload.php has
 * already been required, and bin/console would then exit 0 having run nothing. So it requires only the few files
 * it needs, from the same paths Composer would load them from later.
 */
$vendor = getenv('TLI_PROBE_VENDOR');
$log    = getenv('TLI_PROBE_LOG');
if (!is_string($vendor) || !is_string($log) || !is_file($vendor . '/doctrine/deprecations/src/Deprecation.php')) {
    return;
}

foreach (['psr/log/src/LogLevel.php', 'psr/log/src/LoggerInterface.php', 'psr/log/src/LoggerTrait.php',
          'psr/log/src/AbstractLogger.php', 'doctrine/deprecations/src/Deprecation.php'] as $file) {
    require_once $vendor . '/' . $file;
}

final class TliDoctrineDeprecationProbe extends Psr\Log\AbstractLogger
{
    public function __construct(private readonly string $log) {}

    public function log($level, string|Stringable $message, array $context = []) : void
    {
        // the first frame outside Doctrine and the logger: the line of ours (or of a package) that made the call
        $where = 'unknown';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $file = $frame['file'] ?? '';
            if ($file !== '' && $file !== __FILE__ && !str_contains($file, '/vendor/doctrine/')
                    && !str_contains($file, '/vendor/psr/log/')) {
                $where = $file . ':' . ($frame['line'] ?? 0);
                break;
            }
        }
        $line = $where . "\t" . strtr((string) $message, "\n\t", '  ') . "\n";
        @file_put_contents($this->log, $line, FILE_APPEND | LOCK_EX);
    }
}

Doctrine\Deprecations\Deprecation::enableWithPsrLogger(new TliDoctrineDeprecationProbe($log));
// by default Doctrine reports each kind of deprecation once per process: here every call counts
Doctrine\Deprecations\Deprecation::withoutDeduplication();
