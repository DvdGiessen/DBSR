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
	 * Simple compilescript for DBSR.
	 *
	 * Compared to the rest of DBSR this code is a mess,
	 * but since it's only used for compiling I never
	 * spend to much time on refactoring this. Feel free
	 * to do so. ;)
	 *
	 * @author Daniël van de Giessen
	 * @package DBSR
	 */

	// Minimize output (optimalisations like whitespace removal and file combination, reducing total size and increasing speed)
	$minimize_php 	= TRUE;
	$minimize_html 	= TRUE;
	$minimize_js 	= TRUE;
	$minimize_css 	= TRUE;
	$minimize_svg 	= TRUE;

	// Compress entire PHP output file using a compression algorithm
	// Valid options: none, gzip
	// Note that gzip will void cross-platform compatibility!
	$compress = 'none';

	// Files to compile
	$compile_sets = array(
		'DBSearchReplace-GUI.php' => array(
			'DBSR.php',
			'DBSR_GUI_Resources',
			'DBSR_GUI.php',
			'Bootstrapper.php',
			'DBSR_GUI_Bootstrapper.php'
		),
		'DBSearchReplace-CLI.php' => array(
			'DBSR.php',
			'DBSR_CLI.php',
			'Bootstrapper.php',
			'DBSR_CLI_Bootstrapper.php'
		),
	);

	// Stop throwing irritating errors, we're simply compiling!
	error_reporting(0);

	// Uses UglifyJS... if you don't have it, get the latest version from GitHub <https://github.com/mishoo/UglifyJS2>
	function jsCompiler($code) {
		// Start uglifyjs
		$process = proc_open('uglifyjs - -c unsafe=true', array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		), $pipes);

		// Check if uglifyjs could be found / started
		if(is_resource($process)) {
			// Write the code to STDIN for uglifyjs
			fwrite($pipes[0], $code, strlen($code));

			// Close the pipe so uglifyjs knows it's time to start
			fclose($pipes[0]);

			// Read the compressed output from STDOUT
			$code_compressed = stream_get_contents($pipes[1]);

			// Close STDOUT to prevent deadlocks
			fclose($pipes[1]);

			// Read any errors from STDERR
			$errors = stream_get_contents($pipes[2]);

			// Close STDERR to prevent deadlocks
			fclose($pipes[2]);

			// Close the process handle
			$return_value = proc_close($process);

			// Check if the code compression completed succesfully
			if($return_value > 0) {
				// Something went wrong, return the old code
				echo 'Warning: UglifyJS returned a non-zero value!', PHP_EOL;
				if(!empty($errors)) echo $errors, PHP_EOL;
				return $code;
			} else {
				// Return the compressed code
				return $code_compressed;
			}
		} else {
			// No uglifyjs, so just return the raw code
			echo 'Warning: failed to run UglifyJS!', PHP_EOL;
			return $code;
		}
	}

	// Process dir function
	function add_resources($dir, $prefix, &$source) {
		global $minimize_php, $minimize_html, $minimize_js, $minimize_css, $minimize_svg;

		// Record any winnings by compression
		$compression_winnings = 0;

		// Load up all resources in this directory
		$resources = glob($dir . DIRECTORY_SEPARATOR . '*');

		// Special case: CSS/JS compression
		if((preg_match('#/?js$#', $dir) && $minimize_js) || (preg_match('#/?css$#', $dir) && $minimize_css)) {
			if(preg_match('#/?js$#', $dir)) {
				// JS order
				$order = array(
					'jquery',
					'jquery-ui',
					'jquery-liteaccordion',
					'jquery-blockui',
					'script',
				);
			} else {
				// CSS order
				$order = array(
					'SourceSansPro',
					'SourceCodePro',
					'reset',
					'jquery-ui',
					'jquery-liteaccordion',
					'style',
				);
			}

			// Webfonts CSS special case
			if(preg_match('#/?css$#', $dir)) {
				$resources = array_merge($resources, glob(str_replace('css', 'webfonts', $dir) . DIRECTORY_SEPARATOR . '*.css'));
			}

			// Collect resource content
			$resource_content = '';
			foreach($order as $o) {
				foreach($resources as $key => $resource) {
					if(strpos($resource, $o) !== FALSE) {
						if($resource_file_content = file_get_contents($resource)) {
							$resource_content .= "\n" . $resource_file_content;
						} else {
							die('Unable to load file ' . basename($resource));
						}
						unset($resources[$key]);
					}
				}
			}
			foreach($resources as $key => $resource) {
				if(!is_file($resource)) continue;
				if(is_readable($resource) && ($resource_file_content = file_get_contents($resource))) {
					$resource_content .= "\n" . $resource_file_content;
				} else {
					die('Unable to load file ' . basename($resource));
				}
				unset($resources[$key]);
			}

			// Save length to calculate winnings
			$resource_content_length = strlen($resource_content);

			// Compile / minimize
			if(preg_match('#/?js$#', $dir)) {
				// JS using Closure Compiler
				$resource_content = jsCompiler($resource_content);
			} else {
				// CSS using regexes
				$resource_content = preg_replace('#/\*.*?\*/#s', '', $resource_content);
				$resource_content = preg_replace('/[\r\n]+/', '', $resource_content);
				$resource_content = preg_replace('/\s*([:,])\s*/', '$1', $resource_content);
				$resource_content = preg_replace('/\s*{\s*/', '{', $resource_content);
				$resource_content = preg_replace('/\s*;?\s*}\s*/', '}', $resource_content);
				$resource_content = preg_replace('/\s+/', ' ', $resource_content);
				$resource_content = trim($resource_content);
			}

			// Calculate improvement by compression / minimization
			$compression_winnings += $resource_content_length - strlen($resource_content);

			// Write to source
			if(preg_match('/[^\w\s[:print:]]/', $resource_content)) {
				$resource_content = 'base64: ' . base64_encode($resource_content);
			} else {
				$resource_content = 'normal:' . $resource_content;
			}
			if(preg_match('#/?js$#', $dir)) {
				$source .= 'public static $js_scriptjs = ' . var_export($resource_content, TRUE) . ';' . "\n";
			} else {
				$source .= 'public static $css_stylecss = ' . var_export($resource_content, TRUE) . ';' . "\n";
			}
		} elseif(preg_match('#/?webfonts$#', $dir) && $minimize_css) {
			foreach($resources as $key => $resource) {
				if(preg_match('#\.css$#', $resource)) {
					unset($resources[$key]);
				}
			}
		}

		// Loop through dir
		foreach($resources as $resource) {
			if(is_readable($resource) && is_file($resource)) {
				if(!($resource_content = file_get_contents($resource))) die('Unable to load file ' . basename($resource));

				// Save length to calculate winnings
				$resource_content_length = strlen($resource_content);

				// Special case: template file
				if(basename($resource) == 'template.html') {
					// Remove all JS tags to account for compression
					if($minimize_js) $resource_content = preg_replace('#(\s*<script[^>]+></script>)+(\s+<script[^>]+script.js[^>]+></script>)#', '$2', $resource_content);

					// Remove all LINK tags to account for CSS compression
					if($minimize_css) $resource_content = preg_replace('#(\s*<link[^>]*rel="stylesheet"[^>]*/>)+(\s+<link[^>]+style.css[^>]+/>)#', '$2', $resource_content);

					// Remove useless HTML whitespace
					if($minimize_html) {
						$resource_content = preg_replace('/\s+/', ' ', $resource_content);
						$resource_content = str_replace('> <', '><', $resource_content);
					}
				}

				// Special case: SVG font files
				if(substr(basename($resource), -4) == '.svg' && $minimize_svg) {
					$resource_content = preg_replace('/\s+/', ' ', $resource_content);
					$resource_content = str_replace('> <', '><', $resource_content);
				}

				// Calculate improvement by compression / minimization
				$compression_winnings += $resource_content_length - strlen($resource_content);

				if(preg_match('/[^\w\s[:print:]]/', $resource_content)) {
					$resource_content = 'base64: ' . base64_encode($resource_content);
				} else {
					$resource_content = 'normal:' . $resource_content;
				}
				$source .= 'public static $' . preg_replace('/[^\w]/', '', preg_replace('#[/\\\\]#', '_', ($prefix == '' ? '' : ($prefix . '/')) . basename($resource))) . ' = ' . var_export($resource_content, TRUE) . ';' . "\n";
			} elseif(is_readable($resource) && is_dir($resource)) {
				$compression_winnings += add_resources($resource, ($prefix == '' ? '' : ($prefix . '/')) . basename($resource), $source);
			} else {
				die('Unable to load file ' . basename($resource));
			}
		}
		return $compression_winnings;
	}

	function normalizeFileSize($size) {
		$filesizename = array(' bytes', ' KiB', ' MiB', ' GiB', ' TiB', ' PiB', ' EiB', ' ZiB', ' YiB');
		return $size ? round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $filesizename[$i] : '0 bytes';
	}

	// Process each file set
	foreach($compile_sets as $name => $files) {
		$compression_winnings = 0;
		$source = '<?php';
		foreach($files as $key => $file) {
			if(is_readable($file) && is_dir($file)) {
				$source .= ' class ' . $file . ' { ';
				$compression_winnings += add_resources($file, '', $source);
				$source .= '
					/**
					 * Get the resource file content.
					 * @param 	string 	$resource 	The filename of the resource.
					 * @return 	mixed 				The content of the file as string, or FALSE if unsuccessful.
					 */
					public static function getResource($resource) {
						// Clean the resource name
						$resource = preg_replace(\'/[^\w]/\', \'\', preg_replace(\'#[/\\\\\\\\]#\', \'_\', $resource));

						// Look it up and return it
						if(isset(self::$$resource)) {
							// Check for base64 encoding
							switch(substr(self::$$resource, 0, 6)) {
								case \'base64\':
									return base64_decode(substr(self::$$resource, 7));

								default:
								case \'normal\':
									return substr(self::$$resource, 7);
							}
						} else {
							return FALSE;
						}
					}';
				$source .= '}';
			} elseif(is_readable($file) && is_file($file)) {
				$source .= substr(file_get_contents($file), 5);
			} else {
				die('Unable to load file ' . $file);
			}
		}

		if($minimize_php) {
			$compressed = '';

			$tokens = token_get_all($source);
			$previous_token_keyword = FALSE;
			$previous_token_char = FALSE;
			foreach($tokens as $token) {
				if(is_array($token)) {
					switch($token[0]) {
						case T_DOC_COMMENT:
						case T_COMMENT:
						case T_WHITESPACE:
							continue 2;
					}
					$current_token_keyword = FALSE;
					switch($token[0]) {
						case T_ABSTRACT:
						case T_AS:
						case T_BREAK:
						case T_CASE:
						case T_CLASS:
						case T_CLONE:
						case T_CONST:
						case T_CONTINUE:
						case T_ECHO:
						case T_ELSE:
						case T_ELSEIF:
						case T_EXTENDS:
						case T_FINAL:
						case T_FUNCTION:
						case T_GLOBAL:
						case T_GOTO:
						case T_IMPLEMENTS:
						case T_INCLUDE:
						case T_INCLUDE_ONCE:
						case T_INSTANCEOF:
						case T_INSTEADOF:
						case T_INTERFACE:
						case T_LOGICAL_AND:
						case T_LOGICAL_OR:
						case T_LOGICAL_XOR:
						case T_NAMESPACE:
						case T_NEW:
						case T_PRIVATE:
						case T_PUBLIC:
						case T_PROTECTED:
						case T_REQUIRE:
						case T_REQUIRE_ONCE:
						case T_RETURN:
						case T_STATIC:
						case T_THROW:
						case T_TRAIT:
						case T_USE:
						case T_VAR:
						case T_RETURN:
							$current_token_keyword = TRUE;
							break;
					}
					$compressed .= (!$previous_token_char && ($previous_token_keyword || $current_token_keyword) ? ' ' : '') . $token[1];

					$previous_token_keyword = $current_token_keyword;
					$previous_token_char = FALSE;
				} else {
					$compressed .= $token;
					$previous_token_keyword = FALSE;
					$previous_token_char = TRUE;
				}
			}

			$compressed = trim(substr($compressed, 5));

			switch($compress) {
				case 'gzip':
					$compressed = 'eval(gzuncompress(base64_decode(\'' . base64_encode(gzcompress($compressed, 9)) . '\')));';
					break;
			}

			// Re-insert the GPL into the compressed
			$compressed = '<?php' . "\n" .
				'/* This file is part of DBSR.' . "\n" .
				' *' . "\n" .
				' * DBSR is free software: you can redistribute it and/or modify' . "\n" .
				' * it under the terms of the GNU General Public License as published by' . "\n" .
				' * the Free Software Foundation, either version 3 of the License, or' . "\n" .
				' * (at your option) any later version.' . "\n" .
				' *' . "\n" .
				' * DBSR is distributed in the hope that it will be useful,' . "\n" .
				' * but WITHOUT ANY WARRANTY; without even the implied warranty of' . "\n" .
				' * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the' . "\n" .
				' * GNU General Public License for more details.' . "\n" .
				' *' . "\n" .
				' * You should have received a copy of the GNU General Public License' . "\n" .
				' * along with DBSR.  If not, see <http://www.gnu.org/licenses/>.' . "\n" .
				' */' . "\n" .
				$compressed;

			file_put_contents('compiled' . DIRECTORY_SEPARATOR . $name, $compressed);
			echo 'Compiled file ', $name, ', total size is ' . normalizeFileSize(strlen($compressed)) . ' (including ', round(100 * (1 - (strlen($compressed) / (strlen($source) + $compression_winnings))), 1), '% reduction by compression)' . (PHP_SAPI == 'cli' ? '' : '<br />') . PHP_EOL;
		} else {
			file_put_contents('compiled' . DIRECTORY_SEPARATOR . $name, $source);
			echo 'Compiled file ', $name, ', total size is ' . normalizeFileSize(strlen($source)) . (PHP_SAPI == 'cli' ? '' : '<br />') . PHP_EOL;
		}
	}

