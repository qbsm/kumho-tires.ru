<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Переводит ошибки уровня PHP в общий лог приложения.
 *
 * Без этого warning/notice уходят в error_log хостинга (на FTP-хостингах он недоступен),
 * а фатальные ошибки не попадают никуда: обработчик ошибок Slim их не видит.
 */
final class PhpErrorLogger
{
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    public static function register(LoggerInterface $logger): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($logger): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            $logger->log(self::levelFor($severity), $message, [
                'severity' => self::severityName($severity),
                'file' => $file,
                'line' => $line,
                'path' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            ]);

            return false;
        });

        register_shutdown_function(static function () use ($logger): void {
            $error = error_get_last();
            if ($error === null || ($error['type'] & self::FATAL) === 0) {
                return;
            }

            $logger->critical($error['message'], [
                'severity' => self::severityName($error['type']),
                'file' => $error['file'],
                'line' => $error['line'],
                'path' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            ]);
        });
    }

    private static function levelFor(int $severity): string
    {
        return match ($severity) {
            E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR => LogLevel::ERROR,
            E_WARNING, E_USER_WARNING, E_COMPILE_WARNING, E_CORE_WARNING => LogLevel::WARNING,
            E_DEPRECATED, E_USER_DEPRECATED => LogLevel::NOTICE,
            default => LogLevel::NOTICE,
        };
    }

    private static function severityName(int $severity): string
    {
        return match ($severity) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'E_UNKNOWN(' . $severity . ')',
        };
    }
}
