<?php
/**
 * Provides basic bootstrapper functionality.
 */
class Bootstrapper
{
    /**
     * Private variable used to prevent initialization function from running multiple times.
     *
     * @var boolean
     */
    private static $is_initialized = false;

    /**
     * Helper function for converting errors to exceptions.
     *
     * @param     integer     $errno         The type of the error.
     * @param     string         $errstr     The error message.
     * @param     string         $errfile     The filename where the error occured.
     * @param     integer     $errline     The line number where the error occured.
     * @throws     ErrorException             With the given error, unless the error_reporting value does not include given error number.
     */
    public static function exception_error_handler($errno, $errstr, $errfile, $errline)
    {
        if (($errno & error_reporting()) != 0) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }
    }

    /**
     * Helper function for autoloading classes.
     *
     * @param     string     $class_name     The name of the class to be loaded.
     * @return     boolean                 TRUE when the class was loaded successfully, FALSE otherwise.
     */
    public static function autoloader($class_name)
    {
        // Check if class already exists
        if (class_exists($class_name)) {
            return true;
        }

        // For each include path
        $include_paths = explode(PATH_SEPARATOR, get_include_path());
        foreach ($include_paths as $include_path) {
            // Skip empty items
            if (empty($include_path) || !is_dir($include_path)) {
                continue;
            }

            // Clean up the include path
            $include_path = rtrim($include_path, '\\/' . \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;

            // For each extension
            foreach (array('.php', '.php5', '.inc.php', '.inc.php5', '.inc') as $extension) {
                // Check for filename in subdirectories
                $count = substr_count($class_name, '_');
                for ($i = 0; $i <= $count; $i++) {
                    // Replace $i'th first underscores by directory separators
                    $filename = $include_path . preg_replace('/_/', \DIRECTORY_SEPARATOR, $class_name, $i) . $extension;
                    if (is_readable($filename)) {
                        include_once $filename;
                        if (class_exists($class_name)) {
                            return true;
                        }
                    }
                }
            }
        }

        // Appearantly, the class couldn't be loaded
        return false;
    }

    /**
     * Helper function for magic quote reversal: runs stripslashes recursively on an array.
     *
     * @see stripslashes()
     *
     * @param     mixed     $value     The value to strip slashes from.
     * @return     mixed             The value with slashes stripped off.
     */
    protected static function stripslashes_recursive($value)
    {
        return is_array($value) ? array_map(array(__CLASS__, 'stripslashes_recursive'), $value) : (is_string($value) ? stripslashes($value) : $value);
    }

    /**
     * Initializes basic PHP stuff like error handling, include paths, magic quote reversal, internal character encoding, timezones.
     */
    public static function initialize()
    {
        // Check initialization status
        if (static::$is_initialized) {
            return;
        }

        // Set up error handling
        set_error_handler(array(__CLASS__, 'exception_error_handler'));

        // Define DEBUG constant
        if (!defined('DEBUG')) {
            define('DEBUG', false);
        }

        // Set error reporting level
        error_reporting(DEBUG ? E_ALL : 0);

        // Set up include path
        set_include_path(get_include_path() . PATH_SEPARATOR . realpath(__DIR__));

        // Set up autoloader
        spl_autoload_register(array(__CLASS__, 'autoloader'));

        // Get rid of magic quotes
        if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) {
            $_POST = static::stripslashes_recursive($_POST);
            $_GET = static::stripslashes_recursive($_GET);
            $_COOKIE = static::stripslashes_recursive($_COOKIE);
            $_REQUEST = static::stripslashes_recursive($_REQUEST);
            @ini_set('magic_quotes_gpc', false);
        }
        if (function_exists('set_magic_quotes_runtime')) {
            @set_magic_quotes_runtime(false);
        }

        // Try to remove any memory limitations
        @ini_set('memory_limit', '-1');

        // Try to set the PCRE recursion limit to a sane value
        // See http://stackoverflow.com/a/7627962
        @ini_set('pcre.recursion_limit', '100');

        // Set internal character encoding
        @ini_set('default_charset', 'UTF-8');
        if (extension_loaded('mbstring')) {
            @mb_internal_encoding('UTF-8');
        }
        if (version_compare(PHP_VERSION, '5.6', '<') && extension_loaded('iconv')) {
            @iconv_set_encoding('internal_encoding', 'UTF-8');
        }

        // Set the timezone
        date_default_timezone_set('UTC');

        // Set initialization status
        static::$is_initialized = true;
    }

    /**
     * Destroys the current PHP session.
     */
    public static function sessionDestroy()
    {
        $_SESSION = array();
        session_destroy();
        session_commit();
    }

    /**
     * Starts a PHP session and provides basic protection against session hijacking.
     */
    public static function sessionStart()
    {
        // Determine current security data
        $security_data = array(
                'server_ip'    => @$_SERVER['SERVER_ADDR'],
                'server_file'  => __FILE__,
                'client_ip'    => $_SERVER['REMOTE_ADDR'],
                'client_ua'    => $_SERVER['HTTP_USER_AGENT']
        );

        // Set the session life time to 24 hours
        @ini_set('sessions.gc_maxlifetime', (string) (60 * 60 * 24));

        // Set the session name
        session_name('DBSR_session');

        // Open a session to access and store user data
        session_start();

        // If the session is new...
        if (session_id() == '' || !isset($_SESSION['_session_security_data'])) {
            // Set the security data
            $_SESSION['_session_security_data'] = $security_data;
        } else {
            // Check if the session is invalid
            if ($_SESSION['_session_security_data'] !== $security_data) {
                // Destroy the current session
                static::sessionDestroy();

                // Start a new one
                static::sessionStart();
            }
        }
    }
}
