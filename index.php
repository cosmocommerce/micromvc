<?php
/**
 * INDEX BOOTSTRAP
 *
 * This is the starting point (index) for the system. Here we check cached files
 * and then precede to load the system and finally run the controller.
 *
 * @package		MicroMVC
 * @author		David Pennington
 * @copyright	Copyright (c) 2009 MicroMVC
 * @license		http://www.gnu.org/licenses/gpl-3.0.html
 * @link		http://micromvc.com
 * @version		1.0.1 <5/31/2009>
 ********************************** 80 Columns *********************************
 */

//Log current time so we can tell how long it takes to run this script
define('START_TIME', microtime(true));

//Log starting memory useage
define('START_MEMORY_USAGE', memory_get_usage());

//Set the unique name of the current page (for the cache)
$var = preg_replace("/([^a-z0-9_\-\.]+)/i", '_', $_SERVER["REQUEST_URI"]);
define('PAGE_NAME', ($var ? $var : 'index'));

//Define the OS file path separator
define('DS', DIRECTORY_SEPARATOR);

//Define the base file system path to MicroMVC
define('SYSTEM_PATH', realpath(dirname(__FILE__)). DS);

//Include the common file to continue loading
require_once(SYSTEM_PATH. 'functions/common.php');

//Define the base file system path to MicroMVC
define('MODULE_PATH', SYSTEM_PATH. 'modules/');

//Discover the current domain for the whole script
define('DOMAIN', current_domain());

//Discover whether this is an AJAX request or not
define('AJAX_REQUEST', is_ajax_request());

//Define the file system path to the current site
define('SITE_PATH', SYSTEM_PATH. DS. DOMAIN. DS);

//The file system path of the site's upload folder
define('UPLOAD_PATH', SITE_PATH. 'uploads/');

//The file system path of the site's cache folder
define('CACHE_PATH', SITE_PATH. 'cache/');

//Override the PHP error handler
set_error_handler('mvc_error_handler');

//Require the config file for this site name
require(SITE_PATH. 'config/config.php');

//Require the config file for the hooks
require(SITE_PATH. 'config/hooks.php');

//Load the caching class
$cache = load_class('cache', 'libraries', NULL, 2);

//Load the hooks class
$hooks = load_class('hooks', 'libraries', $hooks, 2);

//Call first hook
$hooks->call('system_startup');

/**
 * Check for cached version -die if found
 */
if($output = $cache->fetch(md5(PAGE_NAME. AJAX_REQUEST), null, null)) {

	print $output;

	//If debuging is enabled
	if(DEBUG_MODE) {
		$time = round((microtime(true) - START_TIME), 5);
		$memory = round((memory_get_usage() - START_MEMORY_USAGE) / 1024);

		die('<!-- Rendered in '. $time. ' seconds using '. $memory. ' kb of memory -->');
	}

}


/**
 * strip the slashes that have been added to our POST/GET data!
 */
if (ini_get('magic_quotes_gpc')) {

	function array_clean(&$value) {
		$value = stripslashes($value);
	}
	//php 5+ only
	array_walk_recursive($_GET, 'array_clean');
	array_walk_recursive($_POST, 'array_clean');
	array_walk_recursive($_COOKIE, 'array_clean');
}


//Include the core file
load_file('core', 'libraries', 2);

//Include the base file
load_file('base', 'libraries', 2);


/**
 * Get the controller from the URI
 */
$routes = load_class('routes', 'libraries', NULL, 2);

//Set default controller/method if none is set in URL
$routes->set_defaults(
	$config['default_controller'],
	$config['default_method'],
	$config['permitted_uri_chars']
);

//Parse the URI
$routes->parse();

//Fetch the controller/method
$controller	= $routes->fetch(0);
$method		= $routes->fetch(1);


/**
 * START-UP THE SYSTEM!
 */

//If the file doesn't exist - default to the core class
if( ! load_class($controller, 'controllers', NULL, 1, FALSE)) {
	$method		= 'requrst_error';
	$controller = 'core';
}

//Make sure someone isn't trying to access core/private functions
if(($method !== 'request_error' && method_exists('core', $method))
	//And make sure this method exists (and is public)
	|| !in_array($method, get_class_methods($controller))) {

	//Trigger a 404 not found error
	$method = 'request_error';
}

//Create a new instance of that controller and pass the $config
$controller = load_class($controller, NULL, $config);

//Call the startup hook
$controller->hooks->call('post_constructor');

// Call the requested method.
// Any URI segments present (besides the class/function)
// will be passed to the method for convenience
call_user_func_array(array(&$controller, $method), array_slice($routes->fetch(true), 2));

//Call the post-controller hook
$controller->hooks->call('post_method');

// And we're done!
$controller->render();

//Call the finish hook
$controller->hooks->call('system_shutdown');
