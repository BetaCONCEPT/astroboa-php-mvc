<?php 
/**
 * Copyright (C) 2005-2011 BetaCONCEPT LP.
 *
 * This file is part of Astroboa.
 *
 * Astroboa is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Astroboa is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Astroboa.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * * @author Gregory Chomatas (gchomatas@betaconcept.com)
 * 
 */

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Util.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . 'MessageBundle.php');

class Controller {
	
	const VIEWED_OBJECTS_EXCHANGE_NAME = 'ViewedObjects.exchange';
	const VIEWED_OBJECTS_QUEUE_NAME = 'ViewedObjects';
	
	protected $astroboaClient;
	protected $secureAstroboaClient;
	protected $smarty;
	protected $memoryCache;
	protected $astroboaConfiguration;
	protected $messages;
	
	public function __construct() {
		// for the astroboa client classes to work properly we need some parameters to be configured in an ini file
		// The full path to this ini file should reside in global variable "ASTROBOA_CLIENT_CONFIGURATION_INI_FILE"
		// so check that the global variable "ASTROBOA_CLIENT_CONFIGURATION_INI_FILE" is defined and try to load the configuration from the file
		if (!defined('ASTROBOA_CLIENT_CONFIGURATION_INI_FILE')) {
			throw new Exception('Please Initialize the global variable "ASTROBOA_CLIENT_CONFIGURATION_INI_FILE" with the full path to the astroboa.ini file. A sample file exists in the root folder of astroboa client lib. Copy it inside your site document root, change it according to your needs and initialize the "ASTROBOA_CLIENT_CONFIGURATION_INI_FILE" global variable to the full path to the file, eg. define("ASTROBOA_CLIENT_CONFIGURATION_INI_FILE", "/var/www/html/mysite/astroboa.ini"); ');
		}
		
		$astroboaClientConfigurationFile = constant('ASTROBOA_CLIENT_CONFIGURATION_INI_FILE');
		
		// get a connection to memory cache
		$this->memoryCache = Util::getMemoryCache();
		
		// the configuration is stored to the cache so we have intialized the cache above
		$this->astroboaConfiguration = Util::getAstroboaConfiguration($astroboaClientConfigurationFile, $this->memoryCache);
		
		if (!$this->astroboaConfiguration) {
			throw new Exception('Astroboa client configuration file (astroboa.ini) could not be loaded. A sample file exists in the root folder of astroboa client lib. Copy it inside your site document root, change it according to your needs and initialize the "ASTROBOA_CLIENT_CONFIGURATION_INI_FILE" global variable to the full path to the file, eg. define("ASTROBOA_CLIENT_CONFIGURATION_INI_FILE", "/var/www/html/mysite/astroboa.ini"); ');
		}
		
		// Start the session
		Util::sessionStart($this->astroboaConfiguration, $this->memoryCache);
		
		$this->astroboaClient = Util::getAstroboaClient($this->astroboaConfiguration);
		$this->secureAstroboaClient = Util::getSecureAstroboaClient($this->astroboaConfiguration);
		
		//initialize Message Bunlde
		$this->messages = new MessageBundle($this->astroboaConfiguration);
		
		// initialize smarty
		$this->smarty = Util::initSmarty($this->astroboaConfiguration);
		
		$this->smarty->assign('facebookLoginUrl', Util::getFacebookLoginUrl($this->astroboaConfiguration));
		//$this->smarty->assign('linkedinLoginUrl', Util::getLinkedinLoginUrl($this->astroboaConfiguration));
		
		$this->smarty->assign('messages', $this->messages);
		
	}
	
	/**
	 * 
	 * Method responsible to retrieve a resource using the provided system name or id.
	 * 
	 * If the cache contains the resource, then that resource is returned.
	 * Otherwise, the resource is retrieved from the Astroboa repository. 
	 * If found, it is stored in the cache in order to be available on future requests.
	 * 
	 * Users control the amount of time the resource stays in the cache as well as whether
	 * to keep track that the resource has been viewed. 
	 * 
	 * In the latter case, Astroboa PHP MVC is expecting to be able to connect to a messaging server, 
	 * whose settings are provided in the astroboa.ini and to post a message a specific queue named 
	 * after the  value of the VIEWED_OBJECTS_QUEUE_NAME variable. The message is nothing more than the
	 * resource's identifier.
	 *
	 * It is not the responsibility of this controller how to process the message that is sent to the queue.
	 * If the developer activates this feature, she must be able to implement another application whose
	 * responsibility is to process the messages of this queue.
	 * 
	 * @param unknown_type $objectIdOrName Resource identifier or system name
	 * @param unknown_type $cacheDefaultExpirationInSeconds Number of seconds, the resource is kept in the cache
	 * @param unknown_type $notifyObjectHasBeenViewed True to keep track that the resource has been viewed, false otherwise.
	 * @return mixed|NULL
	 */
	protected function getObject($objectIdOrName, $cacheDefaultExpirationInSeconds = null, $notifyObjectHasBeenViewed = false) {
		
		// first look in cache
		$object = $this->memoryCache->get($objectIdOrName);
		
		if ($object == null) {
			// try the repository
			$request = $this->astroboaClient->getObjectByIdOrName($objectIdOrName);
			
			if ($request->ok()) {
				$object = $request->getResponseBodyAsArray();
				if ($cacheDefaultExpirationInSeconds == null) {
						if (!empty($this->astroboaConfiguration['cache']['CACHE_DEFAULT_EXPIRATION_IN_SECONDS_OBJECT'])) {
							$cacheDefaultExpirationInSeconds = $this->astroboaConfiguration['cache']['CACHE_DEFAULT_EXPIRATION_IN_SECONDS_OBJECT'];
						}
						else {
							$cacheDefaultExpirationInSeconds = 0;
							error_log('No cache expiration time was provided and the "CACHE_DEFAULT_EXPIRATION_IN_SECONDS_OBJECT" has not been set in configuration file (astroboa.ini). The object will be cached without expiration');
						}
				}
				
				$this->memoryCache->set($objectIdOrName, $object, $cacheDefaultExpirationInSeconds);
				
				if ($object != null && $notifyObjectHasBeenViewed && ! Util::user_agent_is_a_spider()){
					$this->publishObjectIdToTheViewedObjectsQueue($object['cmsIdentifier']);
				}
				
				return $object;
			}
			else if ($request->notFound()){
				error_log('object: ' . $objectIdOrName . ' does not exist in cache or in repository');
				return null;
			}
			else {
				$responseInfo = $request->getResponseInfo();
				error_log('An error occured while retreiving object: ' . $objectIdOrName . ' from repository. The error code is: ' . $responseInfo['http_code']);
				return null;
			}
		}
		
		if ($object != null && $notifyObjectHasBeenViewed && ! Util::user_agent_is_a_spider()){
			$this->publishObjectIdToTheViewedObjectsQueue($object['cmsIdentifier']);
		}
		
		return $object;
	}
	
	protected function getTopic($topicIdOrName, $cacheDefaultExpirationInSeconds = null) {
		
		// first look in cache
		$topic = $this->memoryCache->get($topicIdOrName);
		
		if ($topic == null) {
			// try the repository
			$request = $this->astroboaClient->getTopicByIdOrName($topicIdOrName);
			
			if ($request->ok()) {
				$topic = $request->getResponseBodyAsArray();
				if ($cacheDefaultExpirationInSeconds == null) {
						if (!empty($this->astroboaConfiguration['cache']['CACHE_DEFAULT_EXPIRATION_IN_SECONDS_TOPIC'])) {
							$cacheDefaultExpirationInSeconds = $this->astroboaConfiguration['cache']['CACHE_DEFAULT_EXPIRATION_IN_SECONDS_TOPIC'];
						}
						else {
							$cacheDefaultExpirationInSeconds = 0;
							error_log('No cache expiration time was provided and the "CACHE_DEFAULT_EXPIRATION_IN_SECONDS_TOPIC" has not been set in configuration file (astroboa.ini). The topic will be cached without expiration');
						}
				}
				
				$this->memoryCache->set($topicIdOrName, $topic, $cacheDefaultExpirationInSeconds);
				return $topic;
			}
			else if ($request->notFound()){
				error_log('topic: ' . $topicIdOrName . ' does not exist in cache or in repository');
				return null;
			}
			else {
				$responseInfo = $request->getResponseInfo();
				error_log('An error occured while retreiving topic: ' . $topicIdOrName . ' from repository. The error code is: ' . $responseInfo['http_code']);
				return null;
			}
		}
		
		return $topic;
	}
	
	protected function findScrollerBoundaryPages($currentPage, $limit, $resultCount, &$scrollerStartPage, &$scrollerEndPage, &$lastPage) {
		$maxPagesInPageScroller = $this->astroboaConfiguration['template']['MAX_PAGES_IN_PAGE_SCROLLER'];
		
		if ($resultCount % $limit == 0) {
			$lastPage = intval($resultCount / $limit);
		} else {
			$lastPage = intval($resultCount / $limit) + 1;
		}
		
		$pagesToPreceedeOrFollow = intval(($maxPagesInPageScroller -1) / 2);
		$pagesToPreceed = 0;
		$pagesToFollow = 0;
		if (($maxPagesInPageScroller - 1) % 2 == 0) {
			$pagesToPreceed = $pagesToPreceedeOrFollow;
			$pagesToFollow = $pagesToPreceedeOrFollow;
		}
		else {
			$pagesToPreceed = $pagesToPreceedeOrFollow;
			$pagesToFollow = $pagesToPreceedeOrFollow + 1;
		}
		
		$scrollerStartPage = $currentPage - $pagesToPreceed;
		if ($scrollerStartPage < 1) {
			$scrollerStartPage = 1;
		}
		
		$scrollerEndPage = $currentPage + $pagesToFollow;
		
		if ($scrollerEndPage < $maxPagesInPageScroller) {
			$scrollerEndPage = $maxPagesInPageScroller;
		}
		
		if ($scrollerEndPage > $lastPage) {
			$scrollerEndPage = $lastPage;
		}
	}
	
	// find how many results are shown in each page
	protected function getLimit($defaultLowerLimit, $defaultUpperLimit, $cookieNameForUserSelectedLimit) {
		$limitAsInt = $defaultLowerLimit;
		
		if (empty($_GET['limit'])) {
			
			if (!empty($_COOKIE[$cookieNameForUserSelectedLimit])) {
				$limitAsInt = (int) $_COOKIE[$cookieNameForUserSelectedLimit];
			}
			
		}
		else { // limit is specified in URL
			$limitAsInt = (int) $_GET['limit'];	
		}
		
		// check if limit is within range
		if ($limitAsInt < $defaultLowerLimit || $limitAsInt > $defaultUpperLimit) {
			$limitAsInt = $defaultLowerLimit;
		}

		// set cookie
		$date_of_expiry = time() + 3600 + 2 * 60 * 60;
		setcookie($cookieNameForUserSelectedLimit, $limitAsInt, $date_of_expiry);
			
		return $limitAsInt;
	}
	
	protected function getOffset($limit, &$resultPageAsInt) {
		$resultPageAsInt = 1;
		
		$offset = 0;
		if (!empty($_GET['page'])) {
			$resultPageAsInt = (int) $_GET['page'];
			
			if ($resultPageAsInt < 0 && $resultPageAsInt > 100000) {
				$resultPageAsInt = 1;
				$offset=0;
			}
			else {
				$offset = ($resultPageAsInt -1) * $limit; 
			}
		}
		
		return $offset;
	}
	
	protected function assignPageScrollerTemplateVariables($result, $resultPageAsInt, $limit) {
		$resultCount = $result['totalResourceCount'];
		$scrollerStartPage = 0; 
		$scrollerEndPage = 0; 
		$lastPage = 0;
		
		$this->findScrollerBoundaryPages($resultPageAsInt, $limit, $resultCount, $scrollerStartPage, $scrollerEndPage, $lastPage);
		
		$this->smarty->assign('limit', $limit);
		$this->smarty->assign('resultCount', $resultCount);
		$this->smarty->assign('scrollerStartPage', $scrollerStartPage);
		$this->smarty->assign('scrollerEndPage', $scrollerEndPage);
		$this->smarty->assign('scrollerCurrentPage', $resultPageAsInt);
		$this->smarty->assign('lastPage', $lastPage);	
	}
	
	protected function assignUrlQueryTemplateVariable($urlQueryWithoutPaginationParams) {
		if ($urlQueryWithoutPaginationParams == '') {
			//since there are no params we only add a '?' so that pagination params can be appended by the template code
			$this->smarty->assign('urlQuery', '?');
		}
		else {
			// there is a possibility that the the urlQuery starts with '&' instead of '?' so lets put '?' at string position 0
			// we also add a '&' at the end so that pagination params can be appended by the template code
			$this->smarty->assign('urlQuery', substr_replace($urlQueryWithoutPaginationParams, '?', 0, 1) . '&');
		}
	}
	
	protected function getResourceApiCommonPath() {
		return "http://" . $this->astroboaConfiguration['repository']['EXTERNAL_REPOSITORY_ADDRESS'] . "/resource-api/" . $this->astroboaConfiguration['repository']['REPOSITORY_NAME'];
	}
	
	/**
	 * 
	 * This method is responsible to publish to an underlying queue
	 * the provided identifier. 
	 * 
	 * A mechanism should be provided on the other end of the queue
	 * in order to process the contents of this queue. For example, 
	 * this mechanism may well increase the value of the 
	 * property 'statistic.viewCounter' of the provided
	 * object.
	 *  
	 * @param $objectId
	 */
	protected function publishObjectIdToTheViewedObjectsQueue($objectId){

		error_log('Object ' . $objectId . ' has been viewed');
		
		if (empty($objectId)){
			return ;
		}
		
		$connection = null;
		$channel = null;
		
		try{
			//Get the connection to the Messaging Server
			$connection = Util::getConnectionToMessageServer($this->astroboaConfiguration);
			
			if ($connection == null){
				if ($this->astroboaConfiguration['messaging-server']['MESSAGING_SERVER_ENABLE'] == '1'){ //1 stnds for true or on as well
					error_log('The use of the messaging server is enabled but no connection to the messaging server is available');
				}
				else{
					//The use of the messaging server is disabled therefore do nothing
					return;
				}
			}
			
			//Create a channel			
			$channel = $connection->channel();
			
			/*
				name: $queue
				passive: false // we want to declare the queue, not just obtain information about it
				durable: true // the queue will survive server restarts
				exclusive: false // the queue can be accessed in other channels
				auto_delete: false //the queue won't be deleted once the channel is closed.
			*/
			$channel->queue_declare(self::VIEWED_OBJECTS_QUEUE_NAME, false, true, false, false);
				
			
			/*
			    name: VIEW_COUNTER_EXCHANGE_NAME
			    type: direct
			    passive: false // we want to declare the exchange, not just obtain information about it
			    durable: true // the exchange will survive server restarts
			    auto_delete: false //the exchange won't be deleted once the channel is closed.
			*/
			$channel->exchange_declare(self::VIEWED_OBJECTS_EXCHANGE_NAME, 'direct', false, true, false);

			$channel->queue_bind(self::VIEWED_OBJECTS_QUEUE_NAME, self::VIEWED_OBJECTS_EXCHANGE_NAME);
			
			$msg = new AMQPMessage($objectId, array('content_type' => 'text/plain', 'delivery-mode' => 2));
			
			$channel->basic_publish($msg, self::VIEWED_OBJECTS_EXCHANGE_NAME);
			
			$channel->close();

			$connection->close();
			
			error_log('Succesfully published resource ' . $objectId);
			
		}
		catch(Exception $e){
			error_log('Unable to inform queue "' .self::VIEWED_OBJECTS_QUEUE_NAME . '" that the object with id ' . $objectId . ' has been viewed' . $e);
			
			if ($channel != null){
				$channel->close();
			}
			
			if ($connection != null){
				$connection->close();
			}
			
			return ;
		}
	}
	
	/**
	 * Return the URL of the server hosting the application.
	 * This URL is the external repository address defined in the configuration 
	 */
	protected function getServerURL(){
		return "http://" . $this->astroboaConfiguration['repository']['EXTERNAL_REPOSITORY_ADDRESS'];
	}
}

?>