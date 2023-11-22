<?php


namespace Tuples\Utils;

/**
 * Set of methods to prepare PHP and Directory Structure for the framework
 * Use once (new PhpBootstrapper('./'))->boot();
 */
class PhpBootstrapper
{
    public function __construct($projectBasePath)
    {
        // run only once
        if (!file_exists(basePath("/bootstraped.keep"))) {

            $path = realpath($projectBasePath);
            if (!file_exists($path) && !is_dir($path)) {
                throw new \Error("Invalid project base Path");
            }

            // Load base_path to ENVIORMENT for further use basePath() & storagePath() helpers
            $_ENV['base_path'] = $path;
        }
    }

    public function boot(): void
    {
        // run only once
        if (!file_exists(basePath("/bootstraped.keep"))) {
            $this->createDirs();
            $this->setMemoryLimit();
            $this->setTimezone();
            $this->setErrorReporting();
            $this->setupSessionConfig();
        }

        file_put_contents(basePath("/bootstraped.keep"), '1');
    }

    /**
     * Creates the minimum required directories.
     *
     * @return void
     */
    private function createDirs(): void
    {
        if (!file_exists(basePath('/public'))) {
            mkdir(basePath('/public'));
        }

        if (!file_exists(storagePath())) {
            mkdir(storagePath());
        }

        if (!file_exists(storagePath('/sessions'))) {
            mkdir(storagePath('/sessions'));
        }

        if (!file_exists(storagePath('/logs'))) {
            mkdir(storagePath('/logs'));
        }
    }

    /**
     * Configure session parameters (if you dont useit doesnt matter, this is only ini_sets with secure configuration)
     *
     * @return void
     */
    private function setupSessionConfig(): void
    {
        // Set the session lifetime to 24 minutes (1440 seconds)
        ini_set('session.gc_maxlifetime', 1440);

        // Set the session save path to a secure and writable directory
        ini_set('session.save_path', storagePath('/sessions'));

        // Configure session cookie parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);

        // Enable session ID regeneration to prevent session fixation attacks
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_cookies', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.regenerate_id', 1);

        // Change the default session name
        ini_set('session.name', 'my_custom_session_name');

        // Use the nocache session cache limiter to prevent caching of sensitive information
        ini_set('session.cache_limiter', 'nocache');

        // Increase the session entropy length for better security
        ini_set('session.entropy_length', 32);

        // Adjust the probability of running the garbage collection routine (cleanup) on session data
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100);

        // Use a secure session hash function (SHA-256)
        ini_set('session.hash_function', 'sha256');

        // Increase the number of bits used to represent the hash in the session ID
        ini_set('session.hash_bits_per_character', 5);
    }

    private function setMemoryLimit(): void
    {
        ini_set('memory_limit', env('PHP_MEMORY_LIMIT', '512M'));
    }

    private function setTimezone(): void
    {
        date_default_timezone_set(env('TIMEZONE', 'UTC'));
    }

    private function setErrorReporting(): void
    {
        if (env('ENVIORMENT', 'dev') === 'dev') {
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        } else {
            ini_set('display_errors', 'Off');
        }
        ini_set('log_errors', 'On');
        ini_set('error_log', storagePath('/logs/php_error.log'));
    }
}