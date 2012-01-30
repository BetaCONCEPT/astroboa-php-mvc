<?php

require_once('astroboa-php-mvc/controller/Controller.php');

class NotFoundController extends Controller {

	public function showMessage($args) {
		
		$this->smarty->display('404.tpl');
		
	}
	
}

?>
