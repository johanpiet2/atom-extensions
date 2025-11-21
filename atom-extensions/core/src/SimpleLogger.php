<?php

declare(strict_types=1);

namespace AtomExtensions;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Minimal PSR-3 logger implementation using error_log().
 *
 * Avoids external dependencies (Monolog) but still satisfies LoggerInterface.
 */
final class SimpleLogger implements LoggerInterface
{
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, (string) $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, (string) $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, (string) $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, (string) $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, (string) $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, (string) $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, (string) $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, (string) $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $levelStr = strtoupper((string) $level);
        $msg = $this->interpolate((string) $message, $context);

        error_log(sprintf('[EXT][%s] %s', $levelStr, $msg));
    }

    /**
     * Replace {placeholders} in message with context values.
     */
    private function interpolate(string $message, array $context): string
    {
        if ($context === []) {
            return $message;
        }

        $replace = [];

        foreach ($context as $key => $value) {
            $placeholder = '{' . $key . '}';

            if (
                is_null($value)
                || is_scalar($value)
                || (is_object($value) && method_exists($value, '__toString'))
            ) {
                $replace[$placeholder] = (string) $value;
            } else {
                $replace[$placeholder] = '[' . gettype($value) . ']';
            }
        }

        return strtr($message, $replace);
    }
}
