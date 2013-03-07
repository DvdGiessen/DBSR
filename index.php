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
	 * Basic index file for easy usage of the development version.
	 *
	 * @author Daniël van de Giessen
	 * @package DBSR
	 */

	// Turn up error reporting so parse errors in the bootstrappers will be shown
	error_reporting(E_ALL);

	// Set up debugging constant
	define('DEBUG', TRUE);

	// Load up bootstrapper so initialization can be run
	require_once 'Bootstrapper.php';

	// For development: Fast switch between CLI and GUI
	if(PHP_SAPI == 'cli' || isset($_GET['args'])) {
		require_once 'DBSR_CLI_Bootstrapper.php';
	} else {
		require_once 'DBSR_GUI_Bootstrapper.php';
	}

