<?php 

require_once('astroboa-php-mvc/controller/Controller.php');

/*
 * Extend Controller class in order to inherit all the functionality 
 * provided by the Astroboa PHP MVC framework. 
 */
class BlogpostingsController extends Controller {
	
	/*
	 * This method is executed by the dispatcher in order to serve
	 * requests which follow the pattern '/blogpostings/some-blog-posting-name' .
	 * The route is defined in the index.php
	 * 
	 * $route = new Route('/blogpostings/:blogpostingName');
	 * $route->setMapClass('Blogpostings')->setMapMethod('showBlogposting');
	 * $route->addDynamicElement(':blogpostingName', ':blogpostingName');
	 * $router->addRoute('blogposting', $route);
	 */
	
	public function showBlogposting($args) {
		
		$blogpostingName = null;

		/*
		 * Create a variable which will hold the base Astroboa Rest API path which is 
		 * used mainly when constructing the URLs which serve binary content, 
		 * such as images or files and make it available to the Smarty
		 * template engine.
		 */
		$this->smarty->assign('resourceApiCommonPath', $this->getResourceApiCommonPath());
		
		if (!empty($args[':blogpostingName'])) {
			$blogpostingName = $args[':blogpostingName'];
		}
		else {
			$this->smarty->display('404.tpl');
			return;
		}
		
		/*
		 * Use this method which is inherited by the Controller class 
		 * to retrieve the resource which represents the blog posting. 
		 */
		$blogposting = $this->getObject($blogpostingName);
		
		if ($blogposting == null) {
			$this->smarty->display('404.tpl');
			return;
		}
		
		
		/*
		 * Create a variable which will hold the resource and its properties
		 * and make it available to the Smarty template engine.
		 */
		$this->smarty->assign('blogposting', $blogposting);
		
		/*
		 * Pass the control to the Smarty template engine by calling 
		 * the template which will be used to generate the html page which 
		 * displays the requested blog posting.
		 */
		$this->smarty->display('blogposting.tpl');
		
	}
	
	/*
	* This method is executed by the dispatcher in order to serve
	* the request '/blogpostings'.
	* The route is defined in the index.php
	*
	* $route = new Route('/blogpostings');
	* $route->setMapClass('Blogpostings')->setMapMethod('showBlogpostingCollection');
	* $router->addRoute('blogpostings', $route);
	*/
	
	public function showBlogpostingCollection($args) {
	
		/*
		* Create a variable which will hold the base Astroboa Rest API path which is
		* used mainly when constructing the URLs which serve binary content,
		* such as images or files and make it available to the Smarty
		* template engine.
		*/
		$this->smarty->assign('resourceApiCommonPath', $this->getResourceApiCommonPath());

		/*
		 * Set the default number of blog postings to return
		 * Users may control this with the request parameter 'limit' 
		 */
		$limit = 30;
		if (isset($_REQUEST['limit'])) {
			$limit = $_REQUEST['limit'];
		}
			
		/*
		 * Set the number of the page to be displayed
		 * Users may control this with the request parameter 'page'
		 */
		$page = 1;
		if (isset($_REQUEST['page'])) {
			$page = $_REQUEST['page'];
		}
		
		/*
		 * Calculate offset
		 */
		$offset = ($page - 1) * $limit;
			
		/* 
		 * Create query
		 */
		$resources = null;
		
		/*
		 * Retrieve all resources whose content type is blogpostingObject 
		 */
		$query = 'contentTypeName="blogpostingObject"';
		
		/*
		 * Instruct Astroboa to return the values of the following properties for each blogposting
		 * that matches the query criteria. This string will be the value of the query parameter
		 * 'projectionPaths'. 
		 * 
		 * In our case we want blogposting title, its publisher, its publication date and its body. 
		 */
		$projectionPaths = 'profile.title,webPublication.webPublicationStartDate,body,profile.publisher';
		
		/*
		 * Specify the property whose value will be used to order the results and the ordering as well 
		 */
		$orderBy = 'webPublication.webPublicationStartDate desc';
		
		/*
		 * Use method provided by the Controller to in order to query Astroboa repository 
		 */
		$response = $this->astroboaClient->getObjectCollection($query, $projectionPaths, $offset, $limit, $orderBy);
		if ($response->ok()) {
			$responseBody = $response->getResponseBodyAsArray();
			if ($responseBody['totalResourceCount'] > 0) {
				$resources = $responseBody['resourceCollection']['resource'];
				/*
				 * Use this method to automatically generate all necessary variables for 
				 * creating a pagination control in the page. Use this only when
				 * the resulted page supports pagination.
				 */
				$this->assignPageScrollerTemplateVariables($responseBody, $page, $limit);
			}
		}
		else {
			$responseInfo = $response->getResponseInfo();
			error_log('An error response returned from query ' . $query . 'The error code is: ' . $responseInfo['http_code'] . '. A Null parent section will be returned' . $this->getResourceApiCommonPath());
		}

		/*
		 * Set offset and results to the Smarty Template Engine
		 */
		$this->smarty->assign('offset', $offset);
		$this->smarty->assign('resources', $resources);
			
		/*
  		 * Pass the control to the Smarty template engine by calling
		 * the template which will be used to generate the html page which
		 * displays the requested blog posting.
		*/
		$this->smarty->display('blogpostings.tpl');
	
	}
	
}

?>