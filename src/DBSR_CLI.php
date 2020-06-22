<?php
/**
 * DBSR_CLI provides a CLI interface for the DBSR class.
 */
class DBSR_CLI
{
    // Static properties
    /**
     * Options available as parameters and their default values.
     * @var array
     */
    protected static $default_options = array(
        'CLI' => array(
            'help' => array(
                'name' => array('help', 'h', '?'),
                'parameter' => null,
                'description' => 'display this help and exit',
                'default_value' => null,
            ),
            'version' => array(
                'name' => array('version', 'v'),
                'parameter' => null,
                'description' => 'print version information and exit',
                'default_value' => null,
            ),
            'file' => array(
                'name' => array('file', 'configfile', 'config', 'f'),
                'parameter' => 'FILENAME',
                'description' => 'JSON-encoded config file to load',
                'default_value' => null,
            ),
            'output' => array(
                'name' => array('output', 'o'),
                'parameter' => 'text|json',
                'description' => 'output format',
                'default_value' => 'text',
            ),
        ),

        'PDO' => array(
            'host' => array(
                'name' => array('host', 'hostname'),
                'parameter' => 'HOSTNAME',
                'description' => 'hostname of the MySQL server',
                'default_value' => null,
            ),
            'port' => array(
                'name' => array('port', 'portnumber'),
                'parameter' => 'PORTNUMBER',
                'description' => 'port number of the MySQL server',
                'default_value' => null,
            ),
            'user' => array(
                'name' => array('user', 'username', 'u'),
                'parameter' => 'USERNAME',
                'description' => 'username used for connecting to the MySQL server',
                'default_value' => null,
            ),
            'password' => array(
                'name' => array('password', 'pass', 'p'),
                'parameter' => 'PASSWORD',
                'description' => 'password used for connecting to the MySQL server',
                'default_value' => null,
            ),
            'database' => array(
                'name' => array('database', 'db', 'd'),
                'parameter' => 'DATABASE',
                'description' => 'name of the database to be searched',
                'default_value' => null,
            ),
            'charset' => array(
                'name' => array('charset', 'characterset', 'char'),
                'parameter' => 'CHARSET',
                'description' => 'character set used for connecting to the MySQL server',
                'default_value' => null,
            ),
        ),

        'DBSR' => array(
            DBSR::OPTION_CASE_INSENSITIVE => array(
                'name' => 'case-insensitive',
                'parameter' => '[true|false]',
                'description' => 'use case-insensitive search and replace',
                'default_value' => false,
            ),
            DBSR::OPTION_EXTENSIVE_SEARCH => array(
                'name' => 'extensive-search',
                'parameter' => '[true|false]',
                'description' => 'process *all* database rows',
                'default_value' => false,
            ),
            DBSR::OPTION_SEARCH_PAGE_SIZE => array(
                'name' => 'search-page-size',
                'parameter' => 'SIZE',
                'description' => 'number of rows to process simultaneously',
                'default_value' => 10000,
            ),
            DBSR::OPTION_VAR_MATCH_STRICT => array(
                'name' => 'var-match-strict',
                'parameter' => '[true|false]',
                'description' => 'use strict matching',
                'default_value' => true,
            ),
            DBSR::OPTION_FLOATS_PRECISION => array(
                'name' => 'floats-precision',
                'parameter' => 'PRECISION',
                'description' => 'up to how many decimals floats should be matched',
                'default_value' => 5,
            ),
            DBSR::OPTION_CONVERT_CHARSETS => array(
                'name' => 'convert-charsets',
                'parameter' => '[true|false]',
                'description' => 'automatically convert character sets',
                'default_value' => true,
            ),
            DBSR::OPTION_VAR_CAST_REPLACE => array(
                'name' => 'var-cast-replace',
                'parameter' => '[true|false]',
                'description' => 'cast all replace-values to the original type',
                'default_value' => true,
            ),
            DBSR::OPTION_DB_WRITE_CHANGES => array(
                'name' => 'db-write-changes',
                'parameter' => '[true|false]',
                'description' => 'write changed values back to the database',
                'default_value' => true,
            ),
            DBSR::OPTION_HANDLE_SERIALIZE => array(
                'name' => 'handle-serialize',
                'parameter' => '[true|false]',
                'description' => 'interpret serialized strings as their PHP types',
                'default_value' => true,
            ),
            DBSR::OPTION_LOCK_TABLES => array(
                'name' => 'lock-tables',
                'parameter' => '[true|false]',
                'description' => 'lock tables when running',
                'default_value' => true,
            ),
        ),
    );

    // Static methods
    /**
     * Prints the version string.
     */
    public static function printVersion()
    {
        echo 'DBSR ' . DBSR::VERSION . ' CLI, running on PHP ', PHP_VERSION, ' (', \PHP_SAPI, '), ', PHP_OS, '.', "\n";
    }

    /**
     * Prints the help text based on $default_options.
     *
     * @param     $filename     The filename to display, NULL for autodetect using $argv.
     */
    public static function printHelp($filename = null)
    {
        $pad_left = 4;
        $width_left = 40;
        $width_right = 32;
        if (null === $filename) {
            if (isset($_SERVER['argv']) && is_array($_SERVER['argv'])) {
                $filename = $_SERVER['argv'][0];
            } else {
                $filename = basename($_SERVER['SCRIPT_NAME']);
            }
        }

        static::printVersion();

        echo     "\n",
                'Usage: ', $filename, ' [options] -- SEARCH REPLACE [SEARCH REPLACE...]', "\n" .
                '       ', $filename, ' --file FILENAME', "\n" .
                "\n";
        foreach (static::$default_options as $name => $optionset) {
            echo $name, ' options:', "\n";
            foreach ($optionset as $option) {
                // Force type to array
                $option['name'] = (array) $option['name'];

                // Option
                $parameter = (strlen($option['name'][0]) > 1 ? '--' : '-') . $option['name'][0];

                // Parameter
                if (null !== $option['parameter']) {
                    $parameter .= ' ' . $option['parameter'];
                }

                // Description
                $description_array = preg_split('/(.{1,' . $width_right . '}(?:\s(?!$)|(?=$)))/', $option['description'], null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                $description = $description_array[0];
                for ($i = 1; $i < count($description_array); $i++) {
                    $description .= "\n" . str_repeat(' ', $width_left + $pad_left) . $description_array[$i];
                }

                // Default
                if (null !== $option['default_value']) {
                    $default = $option['default_value'];
                    if (is_bool($default)) {
                        $default = $default ? 'true' : 'false';
                    } else {
                        $default = (string) $default;
                    }
                    $default = ' (default: ' . $default . ')';
                    if (strlen($description_array[count($description_array) - 1]) + strlen($default) > $width_right) {
                        $description .= "\n" . str_repeat(' ', $width_left + $pad_left - 1);
                    }
                    $description .= $default;
                }

                // Echo the option
                echo str_repeat(' ', $pad_left), str_pad($parameter, $width_left), $description, "\n";
            }
        }
    }

    /**
     * Returns the corresponding default option given a switch name.
     *
     * @param     string     $switch         The switch to search for.
     * @param     boolean $check_prefix     Whether to check for the correct prefix.
     * @return     mixed                     The option array, or FALSE if the switch was not found.
     */
    protected static function getOption($switch, $check_prefix = true)
    {
        foreach (static::$default_options as $setname => $set) {
            foreach ($set as $id => $option) {
                foreach ((array) $option['name'] as $name) {
                    if ($switch == ($check_prefix ? (strlen($name) > 1 ? ('--' . $name) : ('-' . $name)) : $name)) {
                        $option['set'] = $setname;
                        $option['id'] = $id;
                        return $option;
                    }
                }
            }
        }
        return false;
    }

    // Properties
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var DBSR
     */
    protected $dbsr;

    /**
     * Options currently set.
     * @var array
     */
    protected $options = array();

    /**
     * The search-values.
     * @var array
     */
    protected $search = array();

    /**
     * The replace-values.
     * @var array
     */
    protected $replace = array();

    /**
     * List of configfiles which have been processed.
     * Used to reprevent recursive inclusion.
     *
     * @var array
     */
    private $configfiles = array();

    // Methods
    /**
     * Constructor: builds a new DBSR_CLI object and initalizes all options to their defaults
     */
    public function __construct()
    {
        foreach (static::$default_options as $setname => $set) {
            foreach ($set as $id => $option) {
                if (null !== $option['default_value']) {
                    $this->options[$setname][$id] = $option['default_value'];
                }
            }
        }
    }

    /**
     * Executes DBSR with the currently set options. Does not return but dies with the result.
     */
    public function exec()
    {
        // Prepare the DSN and PDO options array
        $pdo_options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        );

        $dsn = 'mysql:';
        if (isset($this->options['PDO']['host'])) {
            $dsn .= 'host=' . $this->options['PDO']['host'];
            if (isset($this->options['PDO']['port'])) {
                $dsn .= ':' . $this->options['PDO']['port'];
            }
            $dsn .= ';';
        }
        if (isset($this->options['PDO']['database'])) {
            $dsn .= 'dbname=' . $this->options['PDO']['database'] . ';';
        }
        if (isset($this->options['PDO']['charset'])) {
            $pdo_options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $this->options['PDO']['charset'];
            $dsn .= 'charset=' . $this->options['PDO']['charset'] . ';';
        }

        try {
            // Try building a PDO, DBSR and running the search- and replace-action
            $this->pdo = new PDO($dsn, @$this->options['PDO']['user'], @$this->options['PDO']['password'], $pdo_options);
            $this->dbsr = new DBSR($this->pdo);

            // Set the DBSR options
            foreach ($this->options['DBSR'] as $option => $value) {
                $this->dbsr->setOption($option, $value);
            }

            // Set the search- and replace-values
            $this->dbsr->setValues($this->search, $this->replace);

            // Execute DBSR
            $result = $this->dbsr->exec();
        } catch (Exception $e) {
            // Check the output type for the exception
            switch ($this->options['CLI']['output']) {
                case 'json':
                    exit(json_encode(array('error' => $e->getMessage())));

                case 'text':
                default:
                    exit($e->getMessage());
            }
        }

        // Output the result
        switch ($this->options['CLI']['output']) {
            case 'text':
                exit('Result: ' . $result . ' rows were ' . ($this->options['DBSR'][DBSR::OPTION_DB_WRITE_CHANGES] ? 'changed' : 'matched (no changes were written to the databasse)') . '!');

            case 'json':
                exit(json_encode(array('result' => $result)));
        }
    }

    /**
     * Parses command line arguments. Directly outputs and dies in case of errors.
     *
     * @param array $arguments The array of arguments, the first element being the script's filename.
     */
    public function parseArguments(array $arguments)
    {
        if (empty($arguments)) {
            if (isset($_SERVER['argv']) && is_array($_SERVER['argv'])) {
                $arguments = $_SERVER['argv'];
            } else {
                $arguments = array(basename($_SERVER['SCRIPT_NAME']));
            }
        }

        // Check if there are no arguments
        if (count($arguments) <= 1) {
            echo     'Usage: ', $arguments[0], ' [options] -- SEARCH REPLACE [SEARCH REPLACE...]', "\n" .
                    '       ', $arguments[0], ' --file FILENAME', "\n" .
                    'Try `', $arguments[0], ' --help` for more information.', "\n";
            exit;
        }

        // Loop over all arguments
        for ($i = 1; $i < count($arguments); $i++) {
            switch ($arguments[$i]) {
                case '--':
                    // Check the number of search- and replace-values
                    if (count($arguments) - 1 - $i == 0) {
                        exit('Missing search- and replace-values!');
                    }
                    if ((count($arguments) - 1 - $i) % 2 != 0) {
                        exit('Missing replace-value for seach-value: ' . (string) $arguments[count($arguments) - 1]);
                    }

                    // Save all search- and replace-values
                    for (++$i; $i < count($arguments); $i++) {
                        $this->search[] = $arguments[$i];
                        $this->replace[] = $arguments[++$i];
                    }
                    break;

                default:
                    // Get the option
                    $option = static::getOption($arguments[$i]);
                    if (!$option) {
                        exit('Unknown argument: ' . (string) $arguments[$i]);
                    }

                    // Check for any arguments
                    if (null !== $option['parameter']) {
                        $arg = @$arguments[$i + 1];

                        // Boolean value without argument?
                        if (is_bool($option['default_value']) && (null === $arg || preg_match('/^\-/', $arg))) {
                            $this->options[$option['set']][$option['id']] = !$option['default_value'];
                            break;
                        }

                        // Missing argument?
                        if (null === $arg || preg_match('/^\-/', $arg)) {
                            exit('Missing option for ' . (string) $arguments[$i]);
                        }

                        // Special cases
                        switch ($option['set'] . '/' . $option['id']) {
                            case 'CLI/output':
                                if ($arg == 'json' && !extension_loaded('json')) {
                                    exit('Error: The PHP JSON extension is not available!');
                                }
                                break;
                            case 'CLI/file':
                                if (!extension_loaded('json')) {
                                    exit('Error: The PHP JSON extension is not available!');
                                }
                                if (!$this->parseConfig($arg)) {
                                    exit('Failed to parse config file: ' . (string) $arg);
                                }
                                $i++;
                                break 2;
                        }

                        // Parse the argument
                        if (null !== $option['default_value']) {
                            // Special cases and specific error messages
                            if (is_bool($option['default_value'])) {
                                if (strtolower($arg) == 'true') {
                                    $arg = true;
                                } elseif (strtolower($arg) == 'false') {
                                    $arg = false;
                                } elseif (is_numeric($arg)) {
                                    $arg = (bool) (int) $arg;
                                } else {
                                    exit('Invalid argument, expected boolean for ' . (string) $arguments[$i]);
                                }
                            } elseif (is_int()) {
                                if (is_numeric($arg)) {
                                    $arg = (int) $arg;
                                } else {
                                    exit('Invalid argument, expected integer for ' . (string) $arguments[$i]);
                                }
                            } elseif (is_float()) {
                                if (is_numeric($arg)) {
                                    $arg = (float) $arg;
                                } else {
                                    exit('Invalid argument, expected float for ' . (string) $arguments[$i]);
                                }
                            }

                            // Typeset
                            settype($arg, gettype($option['default_value']));
                        }

                        // Save the argument
                        $this->options[$option['set']][$option['id']] = $arg;
                        $i++;
                    } else {
                        // Special cases
                        switch ($option['set'] . '/' . $option['id']) {
                            case 'CLI/help':
                                exit(static::printHelp($arguments[0]));

                            case 'CLI/version':
                                exit(static::printVersion());
                        }
                    }
                    break;
            }
        }
    }

    /**
     * Parses a config file
     * @param string $file Path to the config file.
     */
    public function parseConfig($file)
    {
        // Check if the file exists
        if (!file_exists($file) || !realpath($file)) {
            return false;
        }

        // Check if we've read the file before
        if (in_array(realpath($file), $this->configfiles)) {
            return false;
        }
        $this->configfiles[] = realpath($file);

        // Read file contents
        $file_contents = @file_get_contents($file);
        if (!$file_contents) {
            return false;
        }

        // Decode content
        $file_array = json_decode($file_contents, true);
        if (!is_array($file_array)) {
            return false;
        }

        // Load search- and replace-values (if existing)
        if (isset($file_array['search']) && is_array($file_array['search'])) {
            $this->search += $file_array['search'];
        }
        if (isset($file_array['replace']) && is_array($file_array['replace'])) {
            $this->replace += $file_array['replace'];
        }

        // Check for options
        if (isset($file_array['options']) && is_array($file_array['options'])) {
            // Return success
            return $this->_parseConfigArray($file_array['options']);
        } else {
            // No options, no problems
            return true;
        }
    }

    /**
     * parseConfig: runs through an option array and parses every option.
     *
     * @param     array     $array     The array of options
     * $return     boolean         TRUE if the array was parsed succesfully, FALSE otherwise.
     */
    private function _parseConfigArray(array $array)
    {
        foreach ($array as $key => $element) {
            if (is_array($element)) {
                if (!$this->_parseConfigArray($element)) {
                    return false;
                }
            } else {
                // Check the option
                $option = static::getOption($key, false);
                if (!$option) {
                    return false;
                }

                // Special cases without paramaters
                switch ($option['set'] . '/' . $option['id']) {
                    case 'CLI/help':
                        exit(static::printHelp());

                    case 'CLI/version':
                        exit(static::printVersion());
                }

                // No parameter? No go!
                if (null === $option['parameter']) {
                    return false;
                }

                // Special cases with paramaters
                switch ($option['set'] . '/' . $option['id']) {
                    case 'CLI/file':
                        if (!$this->parseConfig($element)) {
                            return false;
                        }
                }

                // Save the value
                $this->options[$option['set']][$option['id']] = $element;
            }
        }
        return true;
    }
}
