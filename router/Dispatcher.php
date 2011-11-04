<?php
/**
 * @author Rob Apodaca <rob.apodaca@gmail.com>
 * @copyright Copyright (c) 2009, Rob Apodaca
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link http://robap.github.com/php-router/
 * 
 * Adapted by:
 * @author Gregory Chomatas (gchomatas@betaconcept.com)
 * 
 *
 */
class Dispatcher
{
    /**
     * The suffix used to append to the class name
     * @var string
     */
    private $suffix;

    /**
     * An array of the the paths to look for classes (or controllers)
     * @var array
     */
    private $classPath;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->setSuffix('');
        $this->setClassPath(array());
    }

    /**
     * Attempts to dispatch the supplied Route object. Returns false if it fails
     * @param Route $route
     * @throws classFileNotFoundException
     * @throws badClassNameException
     * @throws classNameNotFoundException
     * @throws classMethodNotFoundException
     * @throws classNotSpecifiedException
     * @throws methodNotSpecifiedException
     * @return mixed - result of controller method or FALSE on error
     */
    public function dispatch( Route $route )
    {
        $class      = trim($route->getMapClass());
        $method     = trim($route->getMapMethod());
        $arguments  = $route->getMapArguments();

        if( '' === $class )
            throw new classNotSpecifiedException('Class Name not specified');

        if( '' === $method )
            throw new methodNotSpecifiedException('Method Name not specified');

        //Because the class could have been matched as a dynamic element,
        // it would mean that the value in $class is untrusted. Therefore,
        // it may only contain alphanumeric characters. Anything not matching
        // the regexp is considered potentially harmful.
        $class = str_replace('\\', '', $class);
        preg_match('/^[a-zA-Z0-9_]+$/', $class, $matches);
        if( count($matches) !== 1 )
            throw new badClassNameException('Disallowed characters in class name ' . $class);

        //At this point, we are relatively assured that the file name is safe
        // to check for it's existence and require in.
        if (empty($this->classPath)) {
        	$file_name = $class . $this->suffix;
        	error_log('Looking for class file: ' . $file_name);
        	if( FALSE === file_exists($file_name) ) {
        		throw new classFileNotFoundException('Class file: "' . $file_name . '" not found');
        	}
        	else {
        		error_log('Found class file: ' . $file_name);
        		require_once($file_name);
        	}
        }
		else {
			foreach ($this->classPath as $singlePath) {
				$file_name = $singlePath . $class . $this->suffix;
				error_log('Looking for class file: ' . $file_name);
				$found = false;
		        if( TRUE === file_exists($file_name) ) {
		        	error_log('Found class file: ' . $file_name);
		        	require_once($file_name);
		        	$found = true;
		        	break;
				}
			}
			if (!$found) {
				throw new classFileNotFoundException('Class file: "' . $file_name . '" not found');
			}
		}
		
		//Apply the suffix to the class name
		$class = $class . str_replace('.php', '', $this->suffix);
		
        //Check for the class class
        if( FALSE === class_exists($class) )
            throw new classNameNotFoundException('class not found ' . $class);

        //Check for the method
        if( FALSE === method_exists($class, $method))
            throw new classMethodNotFoundException('method not found ' . $method);

        //All above checks should have confirmed that the class can be instatiated
        // and the method can be called
        $obj = new $class;
        return call_user_func(array($obj, $method), $arguments);
    }

    /**
     * Sets a suffix to append to the class name being dispatched
     * @param string $suffix
     * @return Dispatcher
     */
    public function setSuffix( $suffix )
    {
        $this->suffix = $suffix . $this->getFileExtension();

        return $this;
    }

    /**
     * Set the path where dispatch classes (controllers) reside
     * @param array $pathArray
     * @return Dispatcher
     */
    public function setClassPath( $pathArray )
    {
        foreach ($pathArray as $i => $path) {
    		preg_replace('/\/$/', '', $pathArray[$i]) . '/';
    	}
    	$this->classPath = $pathArray;
    	
        return $this;
    }

    private function getFileExtension()
    {
        return '.php';
    }
}

class badClassNameException extends Exception{}
class classFileNotFoundException extends Exception{}
class classNameNotFoundException extends Exception{}
class classMethodNotFoundException extends Exception{}
class classNotSpecifiedException extends Exception{}
class methodNotSpecifiedException extends Exception{}

