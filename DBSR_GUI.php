<?php
	/* This file is part of DBSR.
	 *
	 * DBSR is free software: you can redistribute it and/or modify
	 * it under the terms of the GNU General Public License as published by
	 * the Free Software Foundation, either version 3 of the License, or
	 * (at your option) any later version.
	 *
	 * DBSR is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 * GNU General Public License for more details.
	 *
	 * You should have received a copy of the GNU General Public License
	 * along with DBSR.  If not, see <http://www.gnu.org/licenses/>.
	 */

	/**
	 * DBSR_GUI provides functionality for the GUI interface for the DBSR class.
	 *
	 * @author Daniël van de Giessen
	 * @package DBSR
	 * @version 2.0.3
	 */
	class DBSR_GUI {
		/* Constants */
		/**
		 * Version string indicating the DBSR GUI version.
		 * @var string
		 */
		const VERSION = '2.0.3';

		/**
		 * Formatting option: formats as a plain, HTML-safe, string.
		 */
		const FORMAT_STRING_PLAINHTML = 0;

		/**
		 * Formatting option: formats as a PHP escaped string.
		 */
		const FORMAT_STRING_PHPESCAPE = 1;

		/**
		 * Formatting option: formats as a hex editor.
		 */
		const FORMAT_STRING_HEXEDITOR = 2;

		/* Properties */
		/**
		 * Options set during for this DBSR_GUI instance.
		 * @var array
		 */
		protected $options = array();

		/**
		 * The maximum step completed succesfully.
		 * @var integer
		 */
		protected $maxStep = 0;

		/* Static methods */
		/**
		 * Formats a string according to the given formatting style.
		 *
		 * @param 	string 	$string 	The string to be formatted.
		 * @param 	int 	$format 	One of the DBSR_GUI::FORMAT_STRING_* constants.
		 * @return 	string 				The formatted string.
		 */
		public static function formatString($string, $format = self::FORMAT_STRING_PLAINHTML) {
			// Check input
			if(!is_string($string)) return FALSE;

			// Result string
			$result = '';

			// Switch format
			switch($format) {
				default:
				case self::FORMAT_STRING_PLAINHTML:
					$result = htmlspecialchars($string);
					break;

				case self::FORMAT_STRING_PHPESCAPE:
					$result .= '"';
					for($i = 0; $i < strlen($string); $i++) {
						switch($string[$i]) {
							case "\n":
								$result .= '\\n';
								break;
							case "\r":
								$result .= '\\r';
								break;
							case "\t":
								$result .= '\\t';
								break;
							case "\x0B":
								$result .= '\\v';
								break;
							case "\x1B":
								$result .= '\\e';
								break;
							case "\x0C":
								$result .= '\\f';
								break;
							case '\\':
								$result .= '\\\\';
								break;
							case "\"":
								$result .= '\\"';
								break;
							default:
								$ord = ord($string[$i]);
								if($ord >= 32 && $ord < 127) {
									$result .= htmlspecialchars($string[$i]);
								} else {
									$result .= '\\x' . str_pad(strtoupper(dechex($ord)), 2, '0', STR_PAD_LEFT);
								}
								break;
						}
					}
					$result .= '"';
					break;

				case self::FORMAT_STRING_HEXEDITOR:
					// Padding for non-visible characters
					static $pad = '.';

					// Calculate strst padding string
					static $from = '';
					static $to = '';
					if($from === '') {
						for($i = 0; $i <= 0xFF; $i++) {
							$from .= chr($i);
							$to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
						}
					}

					// Number of bytes per line
					$width = max(min(strlen($string), strlen($string) > 48 ? 16 : 8), 1);

					$hex = str_split(bin2hex($string), $width * 2);
					$chars = str_split(strtr($string, $from, $to), $width);

					$offset = 0;
					$leftpad = strlen((string) strlen($string));
					foreach($hex as $i => $line) {
						$result .= '<b>';
						$result .= str_pad($offset, $leftpad, ' ', STR_PAD_LEFT);
						$result .= '</b> : ';
						$result .= str_pad(implode(' ', str_split($line, 2)), 3 * $width, ' ', STR_PAD_RIGHT);
						$result .= ' [<i>';
						$result .= htmlspecialchars(str_pad($chars[$i], $width, ' ', STR_PAD_RIGHT));
						$result .= '</i>]' . "\n";
						$offset += $width;
					}
					break;
			}

			// Return the result
			return $result;
		}

		/**
		 * Returns the levenshtein distance between two strings.
		 *
		 * Though having the same complexity (O(n*m)) as the
		 * build-in PHP function it's implemented a lot more
		 * efficiently. The build-in version of PHP uses a m*n
		 * matrix to calculate the distance, resulting in a huge
		 * memory hog (which is why the maximum string length is
		 * limited to 255 chars). This version uses a bottom-up
		 * dynamic programming approach which limits the matrix
		 * size to 2*n, speeding up the memory allocation and
		 * allowing for longer input strings.
		 *
		 * @param 	string 	$str1 	The first string to be compared.
		 * @param 	string 	$str2 	The seconds string to be compared.
		 * @return 	integer 		The levenshtein distance between the two strings.
		 */
		public static function levenshtein($str1, $str2) {
			// Save string lengths
		    $len1 = strlen($str1);
		    $len2 = strlen($str2);

		    // Strip common prefix
		    $i = 0;
		    do {
		        if(substr($str1, $i, 1) != substr($str2, $i, 1)) break;
		        $i++;
		        $len1--;
		        $len2--;
		    } while($len1 > 0 && $len2 > 0);
		    if($i > 0) {
		        $str1 = substr($str1, $i);
		        $str2 = substr($str2, $i);
		    }

		    // Strip common suffix
		    $i = 0;
		    do {
		        if(substr($str1, $len1 - 1, 1) != substr($str2, $len2-1, 1)) break;
		        $i++;
		        $len1--;
		        $len2--;
		    } while($len1 > 0 && $len2 > 0);
		    if($i > 0) {
		        $str1 = substr($str1, 0, $len1);
		        $str2 = substr($str2, 0, $len2);
		    }

		    // If either of the strings has length 0; return the length of the other string
		    if ($len1 == 0) return $len2;
		    if ($len2 == 0) return $len1;

		    // Create the arrays
		    $v0 = range(0, $len1);
		    $v1 = array();

		    // The actual algorithm
		    for ($i = 1; $i <= $len2; $i++) {
		        $v1[0] = $i;
		        $str2j = substr($str2, $i - 1, 1);

		        for ($j = 1; $j <= $len1; $j++) {
		            $cost = (substr($str1, $j - 1, 1) == $str2j) ? 0 : 1;

		            $m_min = $v0[$j] + 1;
		            $b = $v1[$j - 1] + 1;
		            $c = $v0[$j - 1] + $cost;

		            if ($b < $m_min) $m_min = $b;
		            if ($c < $m_min) $m_min = $c;

		            $v1[$j] = $m_min;
		        }

		        $vTmp = $v0;
		        $v0 = $v1;
		        $v1 = $vTmp;
		    }

		    return $v0[$len1];
		}

		/**
		 * Get the resource file content.
		 * @param 	string 	$resource 	The filename of the resource.
		 * @return 	mixed 				The content of the file as string, or FALSE if unsuccessful.
		 */
		public static function getResource($resource) {
			// Check if a compiled version is available
			if(class_exists('DBSR_GUI_Resources', FALSE)) return DBSR_GUI_Resources::getResource($resource);

			// No directory traversing
			if(preg_match('/\\.\\.[\\/\\\\]/', $resource)) {
				return FALSE;
			}

			// Add path to filename
			$resource = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'DBSR_GUI_Resources' . DIRECTORY_SEPARATOR . $resource;

			// Does the file exists
			if(!is_readable($resource) || !is_file($resource)) {
				return FALSE;
			}

			// Return the content
			return @file_get_contents($resource);
		}

		/**
		 * Returns a MySQL PDO instance according to the given parameters.
		 *
		 * @param string $db_host
		 * @param integer $db_port
		 * @param string $db_user
		 * @param string $db_pass
		 * @param string $db_name
		 * @param sttring $db_char
		 *
		 * @throws PDOException
		 */
		public static function getPDO($db_host = NULL, $db_port = NULL, $db_user = NULL, $db_pass = NULL, $db_name = NULL, $db_char = NULL) {
			// Prepare the DSN and PDO options array
			$pdo_options = array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			);

			$dsn = 'mysql:';
			if(!empty($db_host)) {
				$dsn .= 'host=' . $db_host;
				if(!empty($db_port)) {
					$dsn .= ':' . $db_port;
				}
				$dsn .= ';';
			}
			if(!empty($db_name)) $dsn .= 'dbname=' . $db_name. ';';
			if(!empty($db_char)) {
				$pdo_options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $db_char;
				$dsn .= 'charset=' . $db_char . ';';
			}

			// Try connecting
			return new PDO($dsn, $db_user, $db_pass, $pdo_options);
		}

		/**
		 * Searches available configuration files for database configuration.
		 *
		 * @return array All values detected from configuration files.
		 */
		public static function detectConfig() {
			// Variables to retrieve
			$variables = array(
				'db_host' => 'DB_HOST',
				'db_user' => 'DB_USER',
				'db_pass' => 'DB_PASSWORD',
				'db_name' => 'DB_NAME',
				'db_char' => 'DB_CHARSET',
				'wp_prefix' => '$table_prefix',
			);

			// Configfiles, will be seached in order
			$configfiles = array(
				'database.conf.php',
				'wp-config.php',
				'..' . DIRECTORY_SEPARATOR . 'database.conf.php',
				'..' . DIRECTORY_SEPARATOR . 'wp-config.php',
			);

			// Result array
			$detected = array();

			// For each configfile
			foreach($configfiles as $configfile) if(count($variables) > 0) {
				// Load it
				if(file_exists($configfile) && ($config = file_get_contents($configfile))) {
					// By default, the entire file is the block
					$config_blocks = array($config);

					// Try to determine if a specific block contains our needs
					$regex_block = '/(?:[iI][fF]\s*\(\s*[sS][tT][rR][iI]?(?:[sS][tT][rR]|[pP][oO][sS])\s*\(\s*\$_SERVER\s*\[\s*[\'"]SERVER_NAME[\'"]\s*\]\s*,\s*[\'"]((?:[^\'"]|\\\\\'|\\\\")*)[\'"]\s*\)[^\)]*\)|[eE][lL][sS][eE])\s*(\{(?:[^\{\}]*|\2)*\})/ms';
					if(preg_match_all($regex_block, $config, $matches, PREG_SET_ORDER)) {
						// For each subset
						foreach($matches as &$match) {
							// Discard the complete match
							array_shift($match);

							// Check if the detected name matches agains the current server name
							if($match[0] == '' || stripos($_SERVER['SERVER_NAME'], $match[0]) !== FALSE) {
								// Add this block as prefered block
								array_unshift($config_blocks, $match[1]);
								break;
							}
						}
					}

					// Loop through each block
					foreach($config_blocks as $config_block) if(count($variables) > 0) {
						// Search for each variable and unset it if found
						foreach($variables as $varname => $variable) if(count($variables) > 0) {
							// Is this a define or a simple variable?
							if($variable[0] == '$') {
								$regex_variable = '/' . preg_quote($variable) . '\s*=\s*[\'"](([^\'"]|\\\\\'|\\\\")*)[\'"]\s*;/';
							} else {
								$regex_variable = '/[dD][eE][fF][iI][nN][eE]\s*\(\s*[\'"]' . preg_quote($variable) . '[\'"]\s*,\s*[\'"](([^\'"]|\\\\\'|\\\\")*)[\'"]\s*\)\s*;/';
							}

							// Find the variable
							if(preg_match($regex_variable, $config_block, $matches)) {
								$detected[$varname] = $matches[1];
								unset($variables[$varname]);
							}
						}
					}
				}
			}

			// Special case: extract the port number from the hostname
			if(isset($detected['db_host']) && preg_match('/^(.*):(\d+)$/', $detected['db_host'], $matches)) {
				$detected['db_host'] = $matches[1];
				$detected['db_port'] = $matches[2];
			}
			if(isset($detected['db_host']) && !isset($detected['db_port'])) $detected['db_port'] = NULL;

			// Return the results
			return $detected;
		}

		/**
		 * Provides auto-complete hints for a given field.
		 *
		 * @param 	string 	$id 		The id of the field.
		 * @param 	string 	$term 		The currently typed term.
		 * @param 	array 	$arguments 	Other arguments currently filled in the form.
		 * @return 	array 				The hints, in order of likelyhood.
		 */
		public static function autoComplete($id, $term, $arguments) {
			switch($id) {
				case 'db_name':
					try {
						// Check if we can connect to the database with the given arguments
						$pdo = self::getPDO(@$arguments['db_host'], @$arguments['db_port'], @$arguments['db_user'], @$arguments['db_pass'], NULL, NULL);

						// Fetch a list of databases
						$result = $pdo->query('SHOW DATABASES;', PDO::FETCH_COLUMN, 0);

						// Filter matching databases
						$databases = array();
						foreach($result as $r) {
							if(strtolower(substr($r, 0, strlen($term))) == strtolower($term)) {
								$databases[] = $r;
							}
						}

						// Return result
						return $databases;
					} catch(Exception $e) {
						// Error: return nothing
						return array();
					}
					break;

				case 'db_char':
					try {
						// Check if we can connect to the database with the given arguments
						$pdo = self::getPDO(@$arguments['db_host'], @$arguments['db_port'], @$arguments['db_user'], @$arguments['db_pass'], NULL, NULL);

						// Fetch a list of databases
						$result = $pdo->query('SHOW CHARACTER SET;', PDO::FETCH_COLUMN, 0);

						// Filter matching databases
						$charsets = array();
						foreach($result as $r) {
							if(strtolower(substr($r, 0, strlen($term))) == strtolower($term)) {
								$charsets[] = $r;
							}
						}

						// Return result
						return $charsets;
					} catch(Exception $e) {
						// Error: return nothing
						return array();
					}
					break;

				default:
					// Unknown field, return nothing
					return array();
			}
		}

		/* Methods */
		/**
		 * Constructor: resets the step for every new instance.
		 */
		public function __construct() {
			$this->resetStep();
		}

		/**
		 * Validates the AJAX requests and returns a response for the GUI.
		 *
		 * @param 	integer 	$step		The step to validate.
		 * @param 	array 		$arguments	The arguments for validating this step.
		 *
		 * @return 	array 					The response to send to the GUI.
		 */
		public function completeStep($step, $arguments) {
			if($step > $this->maxStep + 1) return array(
				'valid' => FALSE,
				'error' => 'First complete step ' . ($this->maxStep + 1) . '!'
			);

			switch($step) {
				case 1:
					// Validate the database connection information
					if(!isset($arguments['db_host']) || empty($arguments['db_host'])) {
						return array(
							'valid' => FALSE,
							'error' => 'Please enter a hostname!',
						);
					}
					if(!isset($arguments['db_name']) || empty($arguments['db_name'])) {
						return array(
							'valid' => FALSE,
							'error' => 'Please enter a database name!',
						);
					}
					if(!isset($arguments['db_char']) || empty($arguments['db_char'])) {
						return array(
							'valid' => FALSE,
							'error' => 'Please enter a character set!',
						);
					}

					// Try to connect
					try {
						$pdo = self::getPDO(@$arguments['db_host'], @$arguments['db_port'], @$arguments['db_user'], @$arguments['db_pass'], @$arguments['db_name'], @$arguments['db_char']);
						$pdo->query('SHOW TABLES;');
					} catch(Exception $e) {
						return array(
							'valid' => FALSE,
							'error' => $e->getMessage(),
						);
					}

					// Save maximum step
					$this->maxStep = $step;

					// Save options
					$this->options['db_host'] = @$arguments['db_host'];
					$this->options['db_port'] = @$arguments['db_port'];
					$this->options['db_user'] = @$arguments['db_user'];
					$this->options['db_pass'] = @$arguments['db_pass'];
					$this->options['db_name'] = @$arguments['db_name'];
					$this->options['db_char'] = @$arguments['db_char'];

					// Return data for the GUI
					return array(
						'valid' => TRUE,
						'data' => array(
							'db_host' => @$arguments['db_host'],
							'db_port' => @$arguments['db_port'],
							'db_user' => @$arguments['db_user'],
							'db_pass' => @$arguments['db_pass'],
							'db_name' => @$arguments['db_name'],
							'db_char' => @$arguments['db_char'],
						),
					);

				case 2:
					// Check the search- and replace-values
					if(!is_array(@$arguments['search']) || count(@$arguments['search']) == 0) {
						return array(
							'valid' => FALSE,
							'error' => 'Missing search values!',
						);
					}
					if(!is_array(@$arguments['replace']) || count(@$arguments['replace']) == 0 || count(@$arguments['search']) != count(@$arguments['replace'])) {
						return array(
							'valid' => FALSE,
							'error' => 'Missing replace values!',
						);
					}

					// Clean indices
					$arguments['search'] = array_values(@$arguments['search']);
					$arguments['replace'] = array_values(@$arguments['replace']);

					// Parse escaped values
					$escapedvalues = isset($arguments['escapedvalues']) && strtolower($arguments['escapedvalues']) == 'on';
					if($escapedvalues) {
						for($i = 0; $i < count($arguments['search']); $i++) {
							$arguments['search'][$i] = stripcslashes($arguments['search'][$i]);
							$arguments['replace'][$i] = stripcslashes($arguments['replace'][$i]);
						}
					}

					// Remove all identical values
					for($i = 0; $i < count($arguments['search']); $i++) {
						if(empty($arguments['search'][$i])) return array(
							'valid' => FALSE,
							'error' => 'Search-value cannot be empty!',
						);
						if($arguments['search'][$i] === $arguments['replace'][$i]) {
							array_splice($arguments['search'], $i, 1);
							array_splice($arguments['replace'], $i, 1);
							$i--;
						}
					}

					// Check the length again
					if(count($arguments['search']) == 0) return array(
						'valid' => FALSE,
						'error' => 'All given search- and replace-values are identical!',
					);

					// Save maximum step
					$this->maxStep = $step;

					// Save options
					$this->options['search'] = $arguments['search'];
					$this->options['replace'] = $arguments['replace'];

					$this->options['escapedvalues'] = $escapedvalues;
					$this->options['dbsr_caseinsensitive'] = isset($arguments['dbsr_caseinsensitive']) && strtolower($arguments['dbsr_caseinsensitive']) == 'on';
					$this->options['dbsr_extensivesearch'] = isset($arguments['dbsr_extensivesearch']) && strtolower($arguments['dbsr_extensivesearch']) == 'on';

					// Return data for the GUI
					$values = array();
					foreach(array(
						'values_raw' 		=> 	self::FORMAT_STRING_PLAINHTML,
						'values_escaped' 	=> 	self::FORMAT_STRING_PHPESCAPE,
						'values_hex' 		=> 	self::FORMAT_STRING_HEXEDITOR,
					) as $name => $type) {
						$values[$name] = '';
						for($i = 0; $i < count($arguments['search']); $i++) {
							$values[$name] .= '<tr><td><code>';
							$values[$name] .= self::formatString($arguments['search'][$i], $type);
							$values[$name] .= '</code></td><td><code>';
							$values[$name] .= self::formatString($arguments['replace'][$i], $type);
							$values[$name] .= '</code></td></tr>';
						}
					}

					// Determine suggestions
					$suggestions = $this->getSuggestions();
					if(count($suggestions) > 0) {
						$values['suggestions'] = '<p>' . implode('</p><p>', $suggestions) . '</p>';
					} else {
						$values['suggestions'] = '';
					}

					return array(
						'valid' => TRUE,
						'data' => array(
							'escapedvalues' 		=> 	$this->options['escapedvalues'],
							'dbsr_caseinsensitive' 	=> 	$this->options['dbsr_caseinsensitive'],
							'dbsr_extensivesearch' 	=> 	$this->options['dbsr_extensivesearch'],
						),
						'html' => $values,
					);

				case 3:
					if(!isset($arguments['confirmed']) || strtolower($arguments['confirmed']) != 'on') return array(
						'valid' => FALSE,
						'error' => 'Please confirm the data stated above is correct!',
					);

					// Run DBSR
					try {
						// Build a PDO instance
						$pdo = self::getPDO($this->options['db_host'], $this->options['db_port'], $this->options['db_user'], $this->options['db_pass'], $this->options['db_name'], $this->options['db_char']);

						// Build a DBSR instance
						$dbsr = new DBSR($pdo);

						// Set some DBSR options
						$dbsr->setOption(DBSR::OPTION_CASE_INSENSITIVE, $this->options['dbsr_caseinsensitive']);
						$dbsr->setOption(DBSR::OPTION_EXTENSIVE_SEARCH, $this->options['dbsr_extensivesearch']);

						// Set the search- and replace-values
						$dbsr->setValues($this->options['search'], $this->options['replace']);

						// Reset the maximum step
						$this->resetStep();

						// Execute DBSR
						$result = $dbsr->exec();

						// Return the result
						return array(
							'valid' => TRUE,
							'data' => array(
								'result' => $result,
							),
						);
					} catch(Exception $e) {
						// Return the error
						return array(
							'valid' => TRUE,
							'error' => $e->getMessage(),
						);
					}

				default:
					return array(
						'valid' => FALSE,
						'error' => 'Unknown step!',
					);
			}
		}

		/**
		 * Resets the maximum step.
		 */
		public function resetStep() {
			$this->maxStep = 0;
		}

		/**
		 * Provides simple suggestions for common mistakes based on the search- and replace-values.
		 */
		protected function getSuggestions() {
			// Array with all our messages
			$messages = array();

			// Build a PDO instance
			$pdo = self::getPDO($this->options['db_host'], $this->options['db_port'], $this->options['db_user'], $this->options['db_pass'], $this->options['db_name'], $this->options['db_char']);

			// Try to determine the WP prefix
			$config = self::detectConfig();
			$wp_prefix = !empty($config['wp_prefix']) ? $config['wp_prefix'] : 'wp_';

			// Define the regex for matching domain names
			$domain_regex = '/^https?:\\/\\/([a-z0-9](?:[-a-z0-9]*[a-z0-9])?(?:\\.[a-z0-9](?:[-a-z0-9]*[a-z0-9])?)*)\\/?$/iS';

			// Switches to prevent double messages
			$domain = FALSE;
			$specialchars = FALSE;
			$newlines = FALSE;

			// Get some of the server info to use a spelling probes
			$spelling_probes = array(
				$_SERVER['SERVER_NAME'], 	// current server name
				dirname(__FILE__), 			// current directory
			);

			// Find WP siteurl
			try {
				$result = $pdo->query('SELECT `option_value` FROM `' . $wp_prefix . 'options` WHERE `option_name` = \'siteurl\'', PDO::FETCH_COLUMN, 0)->fetch();
				if(!empty($result)) {
					// Save the domain name
					$result = preg_replace($domain_regex, '$1', $result);
					if(!in_array($result, $spelling_probes)) {
						$spelling_probes[] = $result;
					}

					// WWW-less domain name
					$result = preg_replace('/^www\\.(.+)$/i', '$1', $result);
					if(!in_array($result, $spelling_probes)) {
						$spelling_probes[] = $result;
					}
				}
			} catch(PDOException $e) {}

			// Find WP path
			try {
				$result = $pdo->query('SELECT `option_value` FROM `' . $wp_prefix . 'options` WHERE `option_name` = \'recently_edited\'', PDO::FETCH_COLUMN, 0)->fetch();
				if(!empty($result)) {
					$result = preg_replace('/^(\\/.*)\\/wp-content\\/.*$/i', '$1', preg_replace('/^.*s:\d+:"([^"]+)";.*$/i', '$1', $result));
					if(strpos($result, '"') === FALSE && !in_array($result, $spelling_probes)) {
						$spelling_probes[] = $result;
					}
				}
			} catch(PDOException $e) {}

			// Loop over all values
			for($i = 0; $i < count($this->options['search']); $i++) {
				if(!$domain && preg_match($domain_regex, $this->options['search'][$i]) && preg_match($domain_regex, $this->options['replace'][$i])) {
					// Domain name
					$domain = TRUE;
					$messages[] = 'It seems you\'re going to replace a domain name.<br />Be aware that it is recommended to omit any pre- and suffixes (such as <code>http://</code> or a trailing slash) to ensure <b>all</b> occurences of the domain name will be replaced.';
				} else {
					// Spelling
					foreach($spelling_probes as $probe) {
						if($this->options['dbsr_caseinsensitive']) {
							if(strtolower($this->options['search'][$i]) != strtolower($probe) && self::levenshtein(strtolower($this->options['search'][$i]), strtolower($probe)) < 4) {
								$messages[] = 'I suspect you might have made a typo in the ' . ($i + 1) . 'th search-value. Did you mean "<code>' . htmlspecialchars($probe) . '</code>"?';
							}
							if(strtolower($this->options['replace'][$i]) != strtolower($probe) && self::levenshtein(strtolower($this->options['replace'][$i]), strtolower($probe)) < 4) {
								$messages[] = 'I suspect you might have made a typo in the ' . ($i + 1) . 'th replace-value. Did you mean "<code>' . htmlspecialchars($probe) . '</code>"?';
							}
						} else {
							if($this->options['search'][$i] != $probe && self::levenshtein($this->options['search'][$i], $probe) < 4) {
								$messages[] = 'I suspect you might have made a typo in the ' . ($i + 1) . 'th search-value. Did you mean "<code>' . htmlspecialchars($probe) . '</code>"?';
							}
							if($this->options['replace'][$i] != $probe && self::levenshtein($this->options['replace'][$i], $probe) < 4) {
								$messages[] = 'I suspect you might have made a typo in the ' . ($i + 1) . 'th replace-value. Did you mean "<code>' . htmlspecialchars($probe) . '</code>"?';
							}
						}
					}
				}

				// Non-ASCII characters
				for($j = 0; $j < strlen($this->options['search'][$i]) && !$specialchars; $j++) {
					$ord = ord($this->options['search'][$i][$j]);
					if($ord < 9 || ($ord > 10 && $ord < 13) || ($ord > 13 && $ord < 32) || $ord >= 127) {
						$messages[] = 'There are some non-ASCII characters in your search value(s).<br />Be aware that this script does not provide any transliteration support, thus leaving character encoding entirely up to your browser and the database. Be sure to set the correct charset!';
						$specialchars = TRUE;
					}
				}
				for($j = 0; $j < strlen($this->options['replace'][$i]) && !$specialchars; $j++) {
					$ord = ord($this->options['replace'][$i][$j]);
					if($ord < 9 || ($ord > 10 && $ord < 13) || ($ord > 13 && $ord < 32) || $ord >= 127) {
						$messages[] = 'There are some non-ASCII characters in your replace value(s).<br />Be aware that this script does not provide any transliteration support, thus leaving character encoding entirely up to your browser and the database. Be sure to set the correct charset!';
						$specialchars = TRUE;
					}
				}

				// Newlines
				if(!$newlines && !$this->options['escapedvalues']) {
					if(strpos($this->options['search'][$i], "\n") !== FALSE) {
						$newlines = TRUE;
						$messages[] = 'You\'ve used ' . (strpos($_SESSION['search'][$i], "\r\n") !== FALSE ? 'Windows-style ("<code>\r\n</code>")' : 'Unix-style ("<code>\n</code>")') . ' line endings. If this is not what you want, go back and change it.';
					}
					if(!$newlines && strpos($this->options['replace'][$i], "\n") !== FALSE) {
						$newlines = TRUE;
						$messages[] = 'You\'ve used ' . (strpos($_SESSION['replace'][$i], "\r\n") !== FALSE ? 'Windows-style ("<code>\r\n</code>")' : 'Unix-style ("<code>\n</code>")') . ' line endings. If this is not what you want, go back and change it.';
					}
				}
			}

			// Return the messages
			return $messages;
		}
	}
