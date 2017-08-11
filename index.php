<?php
/**
 * Basic index file for easy usage of the development version.
 * Note that you shouldn't use this file in production, use a
 * compiled file instead!
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
