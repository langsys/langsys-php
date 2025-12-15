<?php

namespace Langsys\SDK\Log;

/**
 * File-based logger implementation with JSON Lines format.
 *
 * Writes log entries as JSON objects, one per line, to a specified file.
 * Supports log level filtering and lazy file handle opening.
 *
 * Compatible with PHP 5.6+.
 */
class Logger implements LoggerInterface
{
    /**
     * Log level priorities (lower = more verbose).
     *
     * @var array
     */
    protected static $levelPriorities = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
    ];

    /**
     * Path to log file.
     *
     * @var string
     */
    protected $logPath;

    /**
     * Minimum log level.
     *
     * @var string
     */
    protected $minLevel;

    /**
     * Minimum level priority for filtering.
     *
     * @var int
     */
    protected $minPriority;

    /**
     * File handle for writing.
     *
     * @var resource|null
     */
    protected $fileHandle;

    /**
     * Create a new Logger instance.
     *
     * @param string $logPath Path to log file
     * @param string $minLevel Minimum log level (debug, info, warning, error)
     */
    public function __construct($logPath, $minLevel = 'debug')
    {
        $this->logPath = $logPath;
        $this->minLevel = $minLevel;
        $this->minPriority = isset(self::$levelPriorities[$minLevel])
            ? self::$levelPriorities[$minLevel]
            : 0;
        $this->fileHandle = null;
    }

    /**
     * Destructor - close file handle if open.
     */
    public function __destruct()
    {
        if ($this->fileHandle !== null) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function debug($message, array $context = [])
    {
        $this->log('debug', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function info($message, array $context = [])
    {
        $this->log('info', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message, array $context = [])
    {
        $this->log('warning', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = [])
    {
        $this->log('error', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = [])
    {
        // Check if level meets minimum threshold
        $levelPriority = isset(self::$levelPriorities[$level])
            ? self::$levelPriorities[$level]
            : 0;

        if ($levelPriority < $this->minPriority) {
            return;
        }

        // Build log entry
        $entry = [
            'timestamp' => $this->getTimestamp(),
            'level' => $level,
            'message' => $message,
        ];

        if (!empty($context)) {
            $entry['context'] = $context;
        }

        // Write to file
        $this->write(json_encode($entry) . "\n");
    }

    /**
     * Get ISO 8601 timestamp with microseconds.
     *
     * @return string
     */
    protected function getTimestamp()
    {
        // PHP 5.6 compatible ISO 8601 with microseconds
        $microtime = microtime(true);
        $micro = sprintf('%06d', ($microtime - floor($microtime)) * 1000000);
        $date = new \DateTime(date('Y-m-d H:i:s.' . $micro, (int) $microtime));
        $date->setTimezone(new \DateTimeZone('UTC'));
        return $date->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Write a line to the log file.
     *
     * @param string $line
     * @return void
     */
    protected function write($line)
    {
        if ($this->fileHandle === null) {
            $this->openFile();
        }

        if ($this->fileHandle !== null) {
            fwrite($this->fileHandle, $line);
        }
    }

    /**
     * Open the log file for appending.
     *
     * Creates the directory if it doesn't exist.
     *
     * @return void
     */
    protected function openFile()
    {
        // Create directory if needed
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $this->fileHandle = @fopen($this->logPath, 'a');
    }

    /**
     * Get the log file path.
     *
     * @return string
     */
    public function getLogPath()
    {
        return $this->logPath;
    }

    /**
     * Get the minimum log level.
     *
     * @return string
     */
    public function getMinLevel()
    {
        return $this->minLevel;
    }
}
