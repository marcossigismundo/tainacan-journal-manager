<?php

declare(strict_types=1);

namespace TainacanJournalManager;

/**
 * Simple PSR-4 autoloader for environments without Composer.
 */
final class Autoloader
{
    private const PREFIX   = 'TainacanJournalManager\\';
    private const BASE_DIR = __DIR__ . '/';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (! str_starts_with($class, self::PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        $file     = self::BASE_DIR . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
