<?php
/**
 * Bootstrapper for the DBSR GUI.
 */

// Initialization
Bootstrapper::initialize();
Bootstrapper::sessionStart();

// Set our exception handler
function DBSR_GUI_ExceptionHandler($e)
{
    // Check if the current request is an AJAX-request
    if (isset($_GET['ajax'])) {
        // Send the error as JSON
        header('Content-Type: application/json');
        exit(json_encode(array(
            'error'      => $e->getMessage(),
            'errorCode'  => $e->getCode(),
            'errorFile'  => $e->getFile(),
            'errorLine'  => $e->getLine(),
            'errorTrace' => $e->getTrace(),
        )));
    } else {
        // Rethrow
        throw $e;
    }
}
set_exception_handler('DBSR_GUI_ExceptionHandler');

// Check if we're reloading the page?
if (!isset($_GET['ajax']) && !isset($_GET['resource'])) {
    Bootstrapper::sessionDestroy();
    Bootstrapper::sessionStart();
}

// Save a DBSR_GUI instance in the session
if (!isset($_SESSION['DBSR_GUI'])) {
    $_SESSION['DBSR_GUI'] = new DBSR_GUI();
}
$dbsr_gui = $_SESSION['DBSR_GUI'];

// Check if this is a AJAX-request
if (isset($_GET['ajax'])) {
    // Build a JSON-response
    header('Content-Type: application/json');

    // Check for JSON extension
    if (!extension_loaded('json')) {
        exit('{"error":"The PHP JSON extension is not available!"}');
    }

    // Initialization
    if (isset($_GET['initialize'])) {
        exit(json_encode(array(
            'data' => DBSR_GUI::detectConfig() + array(
                'DBSR_version' => DBSR::VERSION,
            ),
            'selfdestruct' => class_exists('DBSR_GUI_Resources', false) && realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME']) && is_writable(__FILE__),
        )));
    }

    // Autocomplete
    if (isset($_GET['autocomplete'])) {
        exit(json_encode(DBSR_GUI::autoComplete($_POST['id'], $_POST['term'], $_POST)));
    }

    // Step
    if (isset($_GET['step'])) {
        exit(json_encode($dbsr_gui->completeStep((int) $_POST['step'], $_POST)));
    }

    // Selfdestruct
    if (isset($_GET['selfdestruct'])) {
        exit(json_encode(class_exists('DBSR_GUI_Resources', false) && realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME']) && @unlink(__FILE__)));
    }

    // No autocomplete or step?
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(array('error' => 'Unknown action!')));
} else {
    // If no specific resource is requested, serve the template
    if (!isset($_GET['resource'])) {
        $_GET['resource'] = 'template.html';
    }

    // Get the resource
    if ($resource = DBSR_GUI::getResource($_GET['resource'])) {
        // Set the correct headers
        switch (strtolower(preg_replace('/^.*\.(\w+)$/', '$1', $_GET['resource']))) {
            case 'html':
                header('Content-Type: text/html; charset=UTF-8');
                // Internet Explorer has always held a special place in our code
                // Try using Chrome Frame for IE8 and lower, and else at least disable the compatibility view
                if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
                    header('X-UA-Compatible: IE=edge,chrome=IE8');
                }
                break;

            case 'css':
                header('Content-Type: text/css');
                break;

            case 'js':
                header('Content-Type: text/javascript');
                break;

            case 'png':
                header('Content-Type: image/png');
                break;

            case 'woff':
                header('Content-Type: application/x-font-woff');
                break;

            case 'otf':
                header('Content-Type: application/x-font-otf');
                break;

            case 'eot':
                header('Content-Type: application/x-font-eot');
                break;

            case 'ttf':
                header('Content-Type: application/x-font-ttf');
                break;

            case 'svg':
                header('Content-Type: image/svg+xml');
                break;

            case 'ico':
                header('Content-Type: image/vnd.microsoft.icon');
                break;
        }
        header('Content-Disposition: inline; filename=' . basename($_GET['resource']));

        // Compress output (zlib takes care of client/server headers automatically)
        if (extension_loaded('zlib') && ini_get('output_handler') != 'ob_gzhandler') {
            @ini_set('zlib.output_compression', true);
        }

        // Set expires header (only when running a compressed version)
        if (class_exists('DBSR_GUI_Resources', false)) {
            header('Expires: ' . gmdate('D, d M Y H:i:s \\G\\M\\T', time() + 7 * 24 * 60 * 60));
        }

        // Output the resource
        exit($resource);
    } else {
        // Not found
        header('HTTP/1.1 404 Not Found');
        exit;
    }
}
