<?php
/**
 * Environment Configuration Loader
 * Loads variables from .env file
 */

class Config {
    private static $config = [];
    private static $loaded = false;

    /**
     * Load configuration from .env file
     */
    public static function load() {
        if (self::$loaded) {
            return;
        }

        $env_file = __DIR__ . '/.env';
        
        if (!file_exists($env_file)) {
            throw new Exception('.env file not found. Please copy .env.example to .env and configure.');
        }

        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }

                self::$config[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get a configuration value
     * @param string $key The configuration key
     * @param mixed $default Default value if key not found
     * @return mixed The configuration value
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }

    /**
     * Get all configuration values
     * @return array All configuration values
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }

        return self::$config;
    }

    /**
     * Check if a key exists
     * @param string $key The configuration key
     * @return bool True if key exists
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$config[$key]);
    }
}

// Auto-load configuration on require
try {
    Config::load();
} catch (Exception $e) {
    // Silently fail on first load - applications can handle the error
}
?>
