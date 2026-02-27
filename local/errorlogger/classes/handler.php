<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_errorlogger;

/**
 * Error and exception handler wrappers.
 *
 * Wraps Moodle's existing handlers to capture errors into the database
 * before passing them through to the original handlers.
 *
 * @package    local_errorlogger
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class handler {

    /** @var bool Prevent recursive logging. */
    private static $logging = false;

    /** @var bool Whether handlers have already been registered. */
    private static $registered = false;

    /** @var int Counter for rate limiting within the current minute. */
    private static $logcount = 0;

    /** @var int Timestamp of the current one-minute window. */
    private static $minutewindow = 0;

    /**
     * Register our wrapper handlers around Moodle's existing handlers.
     * Safe to call multiple times — will only register once.
     */
    public static function register(): void {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        // Wrap the exception handler.
        $prev_exception = set_exception_handler(function (\Throwable $ex) use (&$prev_exception) {
            self::handle_exception($ex);
            if (is_callable($prev_exception)) {
                call_user_func($prev_exception, $ex);
            }
        });

        // Wrap the error handler.
        $prev_error = set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$prev_error) {
            self::handle_error($errno, $errstr, $errfile, $errline);
            if (is_callable($prev_error)) {
                return call_user_func($prev_error, $errno, $errstr, $errfile, $errline);
            }
            return false;
        }, E_ALL);

        // Shutdown handler for fatal errors.
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                self::handle_fatal($error);
            }
        });
    }

    /**
     * Handle an uncaught exception.
     *
     * @param \Throwable $ex
     */
    public static function handle_exception(\Throwable $ex): void {
        if (self::$logging || !self::rate_limit_ok()) {
            return;
        }
        self::$logging = true;

        try {
            $capturebt = self::should_capture_backtrace();
            logger::log([
                'type'      => 'exception',
                'severity'  => 1,
                'message'   => $ex->getMessage(),
                'component' => self::detect_component($ex->getFile()),
                'details'   => json_encode([
                    'class' => get_class($ex),
                    'code'  => $ex->getCode(),
                    'file'  => $ex->getFile(),
                    'line'  => $ex->getLine(),
                    'trace' => $capturebt ? $ex->getTraceAsString() : null,
                ], JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
        } catch (\Throwable $ignore) {
            // Never let logging break the error handler.
        }

        self::$logging = false;
    }

    /**
     * Handle a PHP error/warning/notice.
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     */
    public static function handle_error(int $errno, string $errstr, string $errfile, int $errline): void {
        // Skip deprecation notices entirely — they are noisy on PHP 8.x and not actionable errors.
        if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
            return;
        }

        if (self::$logging || !self::rate_limit_ok()) {
            return;
        }

        $severity = self::errno_to_severity($errno);
        $minseverity = (int) get_config('local_errorlogger', 'min_severity');
        if ($minseverity > 0 && $severity > $minseverity) {
            return;
        }

        self::$logging = true;

        try {
            $capturebt = self::should_capture_backtrace();
            logger::log([
                'type'      => self::errno_to_type($errno),
                'severity'  => $severity,
                'message'   => $errstr,
                'component' => self::detect_component($errfile),
                'details'   => json_encode([
                    'errno' => $errno,
                    'file'  => $errfile,
                    'line'  => $errline,
                    'trace' => $capturebt ? self::get_backtrace_string() : null,
                ], JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
        } catch (\Throwable $ignore) {
        }

        self::$logging = false;
    }

    /**
     * Handle a fatal error caught by the shutdown function.
     *
     * @param array $error From error_get_last()
     */
    public static function handle_fatal(array $error): void {
        if (self::$logging) {
            return;
        }
        self::$logging = true;

        try {
            logger::log([
                'type'      => 'error',
                'severity'  => 1,
                'message'   => $error['message'] ?? 'Fatal error',
                'component' => self::detect_component($error['file'] ?? ''),
                'details'   => json_encode([
                    'errno' => $error['type'] ?? E_ERROR,
                    'file'  => $error['file'] ?? '',
                    'line'  => $error['line'] ?? 0,
                ], JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
        } catch (\Throwable $ignore) {
        }

        self::$logging = false;
    }

    /**
     * Map PHP error number to our 4-level severity.
     *
     * @param int $errno
     * @return int 1=critical, 2=warning, 3=notice, 4=debug
     */
    private static function errno_to_severity(int $errno): int {
        return match ($errno) {
            E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR,
            E_USER_ERROR, E_RECOVERABLE_ERROR           => 1,
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING,
            E_USER_WARNING                               => 2,
            E_NOTICE, E_USER_NOTICE, E_STRICT            => 3,
            E_DEPRECATED, E_USER_DEPRECATED              => 4,
            default                                      => 3,
        };
    }

    /**
     * Map PHP error number to a type string.
     *
     * @param int $errno
     * @return string
     */
    private static function errno_to_type(int $errno): string {
        return match ($errno) {
            E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR,
            E_USER_ERROR, E_RECOVERABLE_ERROR            => 'error',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING,
            E_USER_WARNING                                => 'warning',
            E_NOTICE, E_USER_NOTICE                       => 'notice',
            E_STRICT, E_DEPRECATED, E_USER_DEPRECATED     => 'notice',
            default                                       => 'debug',
        };
    }

    /**
     * Check if we're within the rate limit.
     *
     * @return bool
     */
    private static function rate_limit_ok(): bool {
        $now = time();
        $currentwindow = (int) ($now / 60);
        if (self::$minutewindow !== $currentwindow) {
            self::$minutewindow = $currentwindow;
            self::$logcount = 0;
        }

        $max = (int) get_config('local_errorlogger', 'max_logs_per_minute');
        if ($max <= 0) {
            $max = 100;
        }

        self::$logcount++;
        return self::$logcount <= $max;
    }

    /**
     * Check if backtrace capture is enabled.
     *
     * @return bool
     */
    private static function should_capture_backtrace(): bool {
        return (bool) get_config('local_errorlogger', 'capture_backtraces');
    }

    /**
     * Detect the Moodle component from a file path.
     *
     * @param string $file
     * @return string e.g. 'mod_assign', 'local_learner', 'core'
     */
    private static function detect_component(string $file): string {
        global $CFG;

        if (empty($file) || empty($CFG->dirroot)) {
            return '';
        }

        $relative = str_replace($CFG->dirroot . '/', '', $file);
        $parts = explode('/', $relative);

        $plugintypes = [
            'mod' => 'mod', 'local' => 'local', 'blocks' => 'block',
            'auth' => 'auth', 'enrol' => 'enrol', 'filter' => 'filter',
            'report' => 'report', 'repository' => 'repository', 'theme' => 'theme',
            'admin' => 'admin',
        ];

        if (count($parts) >= 2 && isset($plugintypes[$parts[0]])) {
            return $plugintypes[$parts[0]] . '_' . $parts[1];
        }
        if (count($parts) >= 1 && $parts[0] === 'lib') {
            return 'core';
        }

        return '';
    }

    /**
     * Get a formatted backtrace string, excluding our own handler frames.
     *
     * @return string
     */
    private static function get_backtrace_string(): string {
        try {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
            // Remove our own handler frames (first 3-4 frames).
            $trace = array_slice($trace, 4);
            return format_backtrace($trace, true);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
