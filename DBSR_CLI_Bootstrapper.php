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
	 * Bootstrapper for the DBSR CLI.
	 *
	 * @author Daniël van de Giessen
	 * @package DBSR
	 */

	// Initialization
	Bootstrapper::initialize();

	// If it seems we're running from a webserver
	if(PHP_SAPI != 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
		// Build a argument array
		$_SERVER['argv'] = array(basename($_SERVER['SCRIPT_FILENAME']));
		if(isset($_GET['args']) && strlen(trim($_GET['args'])) > 0) {
			$_SERVER['argv'] = array_merge($_SERVER['argv'], explode(' ', trim($_GET['args'])));
		}

		// Don't output HTML in any of the internal functions
		@ini_set('html_errors', 0);

		/** Output buffer callback function with a simple CLI webinterface */
		function DBSR_CLI_output($output) {
			header('Content-Type: text/html; charset=UTF-8');
			return 	'<!DOCTYPE html>' . "\n" .
					'<html lang="en">' . "\n" .
					'<head>' . "\n" .
					"\t" . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />' . "\n" .
    				"\t" . '<title>DBSR CLI</title>' . "\n" .
					'</head>' . "\n" .
					'<body>' . "\n" .
    				"\t" . '<form action="' . @$_SERVER['argv'][0] . '" method="get">' . "\n" .
    				"\t\t" . '<p>' . htmlspecialchars(@$_SERVER['argv'][0]) . ' <input type="text" name="args" value="' . htmlspecialchars(@$_GET['args']) . '" size="100" autofocus="autofocus"/></p>' . "\n" .
    				"\t" . '</form>' . "\n" .
    				"\t" . '<pre>' . htmlspecialchars($output) . '</pre>' . "\n" .
    				'</body>' . "\n" .
    				'</html>';
		}

		// Start the CLI output bufferer
		ob_start('DBSR_CLI_output');
	}

	// Create a new DBSR_CLI instance
	$cli = new DBSR_CLI();

	// Parse the arguments passed to the script
	$cli->parseArguments($_SERVER['argv']);

	// Execute the actual search- and replace-action
	$cli->exec();

	// There's no continuing after including this file
	die();
