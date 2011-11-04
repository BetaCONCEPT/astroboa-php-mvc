<?php 

class RouterError {
	
	public static function show($errorCode, $url) {
		echo '<pre>' . $errorCode . ' for: ' . $url . '</pre>';
	}
}



?>