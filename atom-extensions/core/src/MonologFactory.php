<?php

declare(strict_types=1);

namespace AtomExtensions;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Monolog Logger Factory.
 *
 * Creates enhanced PSR-3 compliant loggers with:
 * - Separate channels per extension
 * - Automatic log rotation
 * - Structured logging with context
 * - Different log levels per environment
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
class MonologFactory
{
    private static array $loggers = [];
    private static string $logPath = '/var/log/atom';
    private static string $environment = 'production';
    private static bool $initialized = false;

    /**
     * Initialize logger configuration.
     *
     * @param string $logPath     Directory for log files
     * @param string $environment Environment (dev, staging, production)
     */
    public static function initialize(string $logPath, string $environment = 'production'): void
    {
        self::$logPath = rtrim($logPath, '/');
        self::$environment = $environment;
        self::$initialized = true;

        // Ensure log directory exists
        if (!is_dir(self::$logPath)) {
            @mkdir(self::$logPath, 0755, true);
        }
    }

    /**
     * Create or get a logger for a specific channel.
     *
     * @param string $channel Logger channel name (typically extension name)
     *
     * @return LoggerInterface PSR-3 logger instance
     */
    public static function create(string $channel = 'extensions'): LoggerInterface
    {
        if (!self::$initialized) {
            self::initialize('/var/log/atom', 'production');
        }

        if (isset(self::$loggers[$channel])) {
            return self::$loggers[$channel];
        }

        $logger = new Logger($channel);

        // Determine log level based on environment
        $level = match (self::$environment) {
            'dev', 'development' => Logger::DEBUG,
            'staging' => Logger::INFO,
            'production' => Logger::WARNING,
            default => Logger::INFO,
        };

        // Add rotating file handler (keeps 30 days of logs)
        $logFile = self::$logPath.'/atom-'.$channel.'.log';
        $fileHandler = new RotatingFileHandler($logFile, 30, $level);
        $fileHandler->setFormatter(self::createFormatter());
        $logger->pushHandler($fileHandler);

        // In development, also log to stderr for immediate visibility
        if (in_array(self::$environment, ['dev', 'development'])) {
            $streamHandler = new StreamHandler('php://stderr', Logger::DEBUG);
            $streamHandler->setFormatter(self::createFormatter());
            $logger->pushHandler($streamHandler);
        }

        // Add processor for additional context
        $logger->pushProcessor(function ($record) {
            $record['extra']['memory_usage'] = memory_get_usage(true);
            $record['extra']['peak_memory'] = memory_get_peak_usage(true);

            return $record;
        });

        self::$loggers[$channel] = $logger;

        return $logger;
    }

    /**
     * Create a formatter for log messages.
     */
    private static function createFormatter(): LineFormatter
    {
        // Format: [2024-11-19 18:00:00] channel.LEVEL: message {"context":"data"} {"extra":"data"}
        $format = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $dateFormat = 'Y-m-d H:i:s';

        $formatter = new LineFormatter($format, $dateFormat, true, true);
        $formatter->includeStacktraces(true);

        return $formatter;
    }

    /**
     * Get logger for specific extension.
     */
    public static function forExtension(string $extensionName): LoggerInterface
    {
        return self::create('ext-'.$extensionName);
    }

    /**
     * Get logger for security-related events.
     */
    public static function security(): LoggerInterface
    {
        return self::create('security');
    }

    /**
     * Get logger for system/framework events.
     */
    public static function system(): LoggerInterface
    {
        return self::create('system');
    }

    /**
     * Change log level for all loggers.
     */
    public static function setLogLevel(string $level): void
    {
        $monologLevel = match (strtolower($level)) {
            'debug' => Logger::DEBUG,
            'info' => Logger::INFO,
            'notice' => Logger::NOTICE,
            'warning', 'warn' => Logger::WARNING,
            'error' => Logger::ERROR,
            'critical' => Logger::CRITICAL,
            'alert' => Logger::ALERT,
            'emergency' => Logger::EMERGENCY,
            default => Logger::INFO,
        };

        foreach (self::$loggers as $logger) {
            if ($logger instanceof Logger) {
                foreach ($logger->getHandlers() as $handler) {
                    $handler->setLevel($monologLevel);
                }
            }
        }
    }

    /**
     * Get all registered logger channels.
     */
    public static function getChannels(): array
    {
        return array_keys(self::$loggers);
    }

    /**
     * Close all loggers (useful for testing or shutdown).
     */
    public static function closeAll(): void
    {
        foreach (self::$loggers as $logger) {
            if ($logger instanceof Logger) {
                foreach ($logger->getHandlers() as $handler) {
                    $handler->close();
                }
            }
        }

        self::$loggers = [];
    }
}
