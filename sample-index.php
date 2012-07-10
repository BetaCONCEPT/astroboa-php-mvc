<?php

error_reporting(E_ALL | E_STRICT);

// A global to hold the deployment environment
// The environment can be: "development" or "staging" or "production"
// It can be used to alter the controller or template behaviour if the app is deployed in different environments
// IT IS REQUIRED by astroboa-php-mvc library. It is used to disable caching with memcached if the environment is 'staging' or 'development'
$env = "development";

require('astroboa-php-mvc/router/php-router.php');

// do not put session_start() in this file
// session wil start after the routing during the controller initialization. 
// We will use the special utility function Util::sessionStart() that takes care of
// session expiration, re-initialization and remember me

// Initialize the global variable ASTROBOA_CLIENT_CONFIGURATION_INI_FILE with the full path to the file that holds the astroboa client configuration parameters.
// usually named astroboa.ini and resides inside the document root of the site.
// To make the site code relocatable it better not to hardcode the path but instead find the document root of this site and append the relative path to the astroboa.ini file
define("ASTROBOA_CLIENT_CONFIGURATION_INI_FILE", getSiteRootPath() . '/astroboa.ini');

$router = new Router;

$dispatcher = new Dispatcher;

//All are controllers are suffixed by the word 'Controller' eg. SectionController
// when we specify the route from a URL to the controller we specify the controller class without the suffix, 
// e.g if thr controller is named 'SectionController' we specify $route->setMapClass('Controller')
$dispatcher->setSuffix('Controller');

// Here we give all the paths to the directories where are Controllers reside
// Usually we give the path where the astroboa-php-mvc basic controllers reside and one path for our local controllers (those that will be created for our project and usually extend the basic controllers)
// The directory that we use here is the PHP include path for Ubuntu assuming that PHP has been installed as a package and that we have decided to put there the 
// astroboa-php-mvn as well as the astroboa-php-client in order to be available for all our php projects.  
$dispatcher->setClassPath(array('controller/', '/usr/share/php/astroboa-php-mvc/controller/'));

// assuming that our site shows news articles and that we have modelled a news article in astroboa
// this route specifies that the URL to see an article is '/news/:articleName' and that when this url arrives here
// it will be routed to a controller named 'NewsController' (remember the default suffix) and the method 'showArticle' will be called.
// The method will use the article name to retrieve the article from astroboa and then it will call a smarty template to
// show the article. Pretty simple isn't it?
$route = new Route('/news/:articleName');
$route->setMapClass('News')->setMapMethod('showArticle');
$route->addDynamicElement(':articleName', ':articleName');
$router->addRoute('news', $route);


// this route is handling the full text search queries of our site
$route = new Route('/search');
$route->setMapClass('TextSearch')->setMapMethod('show');
$router->addRoute( 'search', $route );


// The following are default routes for authentication / registration / commenting
// The routes dispatch to the provided controllers that reside in astroboa-php-mvc/controller/
// These controllers do all the work for registering and loging users as well as letting them add comments to 
// your modeled entities i.e. to news articles. 
$route = new Route('/login');
$route->setMapClass('Identity')->setMapMethod('login');
$router->addRoute( 'login', $route );

$route = new Route('/registration-form');
$route->setMapClass('Identity')->setMapMethod('showRegistrationForm');
$router->addRoute('registration-form', $route);

$route = new Route('/register');
$route->setMapClass('Identity')->setMapMethod('register');
$router->addRoute( 'register', $route );

$route = new Route('/captcha-show');
$route->setMapClass('Captcha')->setMapMethod('show');
$router->addRoute( 'captcha-show', $route );

$route = new Route('/captcha-check');
$route->setMapClass('Captcha')->setMapMethod('check');
$router->addRoute( 'captcha-check', $route );

$route = new Route('/email-available');
$route->setMapClass('Identity')->setMapMethod('checkIfEmailIsAvailable');
$router->addRoute( 'email-available', $route );

$route = new Route('/email-exists');
$route->setMapClass('Identity')->setMapMethod('checkIfEmailExists');
$router->addRoute( 'email-exists', $route );

$route = new Route('/confirm-registration');
$route->setMapClass('Identity')->setMapMethod('confirmRegistration');
$router->addRoute( 'confirm-registration', $route );

$route = new Route('/send-password-reset-email');
$route->setMapClass('Identity')->setMapMethod('sendPasswordResetEmail');
$router->addRoute( 'send-password-reset-email', $route );

$route = new Route('/password-reset-form');
$route->setMapClass('Identity')->setMapMethod('showPasswordResetForm');
$router->addRoute( 'password-reset-form', $route );

$route = new Route('/reset-password');
$route->setMapClass('Identity')->setMapMethod('resetPassword');
$router->addRoute( 'reset-password', $route );

$route = new Route('/auth/facebook');
$route->setMapClass('Facebook')->setMapMethod('login');
$router->addRoute('facebook', $route);

$route = new Route('/auth/linkedin');
$route->setMapClass('Linkedin')->setMapMethod('login');
$router->addRoute('linkedin', $route);

$route = new Route('/auth/logout');
$route->setMapClass('Identity')->setMapMethod('logout');
$router->addRoute('logout', $route);

$route = new Route('/auth/message');
$route->setMapClass('Identity')->setMapMethod('message');
$router->addRoute('message', $route);

// Routes for comments
$route = new Route('/submit-comment');
$route->setMapClass('Comments')->setMapMethod('submit');
$router->addRoute('submit-comment', $route);



// this is a default catch all rule if nothing else matches
// It routes to a 'NotFoundController' uses the method 'showMessage' to 
// display a message to the user and possibly direct her back to home page
$route = new Route('/');
$route->setMapClass('NotFound')->setMapMethod('showMessage');
$router->addRoute( 'home', $route );


$url = false;
if ($_SERVER['QUERY_STRING'] != null) {
	$url = substr(str_replace( $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI'] ),0, -1);
	$url = urldecode($url);
}
else {
	$url = urldecode($_SERVER['REQUEST_URI']);
}
//$url = urldecode($_SERVER['PATH_INFO']);

try {
	$found_route = $router->findRoute($url);
	$dispatcher->dispatch( $found_route );
} 
catch ( RouteNotFoundException $e ) {
	
	RouterError::show('404', $url);
} 
catch ( badClassNameException $e ) {
	RouterError::show('400', $url);
} 
catch ( classFileNotFoundException $e ) {
	echo $e;
	RouterError::show('500', $url);
} 
catch ( classNameNotFoundException $e ) {
	RouterError::show('500', $url);
} 
catch ( classMethodNotFoundException $e ) {
	RouterError::show('500', $url);
} 
catch ( classNotSpecifiedException $e ) {
	RouterError::show('500', $url);
} 
catch ( methodNotSpecifiedException $e ) {
	RouterError::show('500', $url);
}

// get the absolute path (document root in apache teminology) under which the site resides
// this is useful if we want to read a config file that resides in a specific directory inside the document root of the site
// We do not use the "DOCUMENT_ROOT" environment variable because it works only for apache and only if the user has specified it in apache config
function getSiteRootPath() {
	$scriptRelativePath = getenv("SCRIPT_NAME");
	
	$scriptAbsolutePath = __FILE__;

 	// a fix for Windows slashes
 	$scriptAbsolutePath = str_replace("\\","/",$scriptAbsolutePath);
 	$documentRoot = substr($scriptAbsolutePath,0,strpos($scriptAbsolutePath,$scriptRelativePath));
 	
 	return $documentRoot;
}

?>
