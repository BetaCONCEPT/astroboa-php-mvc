<?php 

require_once('astroboa-php-mvc/controller/Controller.php');

class NewsController extends Controller {
	
	public function showArticle($args) {
		$articleName = null;
		
		$this->smarty->assign('resourceApiCommonPath', $this->getResourceApiCommonPath());
		
		if (!empty($args[':articleName'])) {
			$articleName = $args[':articleName'];
		
		}
		else {
			$this->smarty->display('home.tpl');
			return;
		}
		
		
		$article = $this->getObject($articleName);
		
		$this->smarty->assign('article', $article);
		
		$this->smarty->display('article.tpl');
		
	}
	
}

?>