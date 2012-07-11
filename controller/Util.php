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
 * * @author Savvas Triantafyllou (striantafyllou@betaconcept.com)
 */

require_once('astroboa-php-client/AstroboaClient.php');
require_once('smarty' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'Smarty.class.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mail' . DIRECTORY_SEPARATOR . 'class.phpmailer.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'social' . DIRECTORY_SEPARATOR . 'facebook.php');

if (!empty($GLOBALS['env']) && $GLOBALS['env'] == 'production') {
	require_once('php-amqplib/amqp.inc');
}

class Util {
	
	const CACHE_KEY_ASTROBOA_CONFIGURATION = "astroboaConfiguration";
	// the astroboa client configuration is saved in cache and expires every 10 minutes 
	const CACHE_DEFAULT_EXPIRATION_IN_SECONDS_ASTROBOA_CONFIGURATION = 600;
	
	/**
	 * Reads and caches the astroboa.ini configuration file where all site configuration parameters reside
	 * You are strongly adviced to put astroboa.ini outside your document root for security reasons (it contains passwords and other sensitive information).
	 * If you decide to put astroboa.ini inside your application add appropriate mod_rewrite rules to prevent its access from browsers.
	 * You may create a folder outsite your document root and put there the astroboa.ini as well as your smarty templates.
	 * 
	 * The method returns an array of the configuration settings on success or false on failure
	 *  
	 * @param string $fullPathToAstroboaConfigurationFile
	 * @param object $cache The cache object provides access to memcached in order to cache the configuration and speed up subsequent method calls
	 * @return multitype:|boolean
	 */
	public static function getAstroboaConfiguration($fullPathToAstroboaConfigurationFile, $cache) {
		$astroboaConfiguration = null;
		
		if (!empty($cache)) {
			// The cache key is prefixed with the configuration file path as a Name Space to allow for seperate configuration per site
			$astroboaConfigurationCacheKey = $fullPathToAstroboaConfigurationFile . ':' . self::CACHE_KEY_ASTROBOA_CONFIGURATION;
			$astroboaConfiguration = $cache->get($astroboaConfigurationCacheKey);
		}
		
		if ($astroboaConfiguration == null) {
			$astroboaConfiguration = parse_ini_file($fullPathToAstroboaConfigurationFile, true);
			
			if (is_array($astroboaConfiguration)) {
				if (!empty($cache)) {
					$cache->set($astroboaConfigurationCacheKey, $astroboaConfiguration, self::CACHE_DEFAULT_EXPIRATION_IN_SECONDS_ASTROBOA_CONFIGURATION);
				}
				return $astroboaConfiguration;
			}
			else {
				error_log('Could not locate astroboa client configuration in cache or in configuration file. Please check that the specified path for astroboa.ini exists.');
				return false;
			}
		}
		
		return $astroboaConfiguration;
	}
	
	/**
	 * Initializes smarty template engine
	 * 
	 * @param array $astroboaConfiguration An array with all site settings as configured in astroboa.ini
	 * @return Smarty The smarty object that is used to handle the template engine
	 */
	public static function initSmarty($astroboaConfiguration) {
		$smarty = new Smarty();
		
		// Setup the site template directories as it is configured in astroboa.ini.
		// For secuirty reasons avoid to install the templates inside your document root directory or use appropriate mod_rewrite rules in your .htaccess to 
		// prevent access to your template code.
		// If templates are not installed inside your application then directory paths in astroba.ini should be absolute.
		$smarty->setTemplateDir($astroboaConfiguration['smarty']['TEMPLATE_DIR']);
		$smarty->setCompileDir($astroboaConfiguration['smarty']['COMPILE_DIR']);
		$smarty->setCacheDir($astroboaConfiguration['smarty']['CACHE_DIR']);
		$smarty->setConfigDir($astroboaConfiguration['smarty']['CONFIG_DIR']);
		
		if (!empty($astroboaConfiguration['smarty']['EXTRA_PLUGINS_DIR'])) {
			$smarty->addPluginsDir($astroboaConfiguration['smarty']['EXTRA_PLUGINS_DIR']);
		}
		
		return $smarty;
	}
	
	public static function availableLocalizedLabel($topicProperty, $locale) {
	    if(empty($topicProperty['localization']['label'])) {
    		return '';
    	}
	    
		if (!empty($topicProperty['localization']['label'][$locale])) {
			return $topicProperty['localization']['label'][$locale];
		}
	    
		if (!empty($topicProperty['localization']['label']['en'])) {
			return $topicProperty['localization']['label']['en'];
		}
	
		return $topicProperty['localization']['label'][0];
	}
	
	public static function getMemoryCache() {
		$memoryCache = null;
		
		if (!empty($GLOBALS['env']) && $GLOBALS['env'] == 'production') {
			$memoryCache = new Memcached();
			$memoryCache->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
			$memoryCache->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
			$memoryCache->addServer('localhost', 11211);
		}
		
		return $memoryCache;
	}
	
	public static function getAstroboaClient($astroboaConfiguration) {
		return new AstroboaClient($astroboaConfiguration['repository']['INTERNAL_REPOSITORY_ADDRESS'], $astroboaConfiguration['repository']['REPOSITORY_NAME']);
	}
	
	public static function getSecureAstroboaClient($astroboaConfiguration) {
		return new AstroboaClient($astroboaConfiguration['repository']['INTERNAL_REPOSITORY_ADDRESS'], $astroboaConfiguration['repository']['REPOSITORY_NAME'], $astroboaConfiguration['repository']['REPOSITORY_USERNAME'], $astroboaConfiguration['repository']['REPOSITORY_PASSWORD']);
	}
	
	// while getTopic is available in Controller class we have add it as utilitity function since it is usefull in templates to instantly access a topic if the topic name is available
	public static function getTopic($topicIdOrName, $astroboaConfiguration, $cacheDefaultExpirationInSeconds = null,$depth = 1) {
		$topic = null;
		$cache = self::getMemoryCache();
		
		if (!empty($cache)) {
			// first look in cache
			$topic = $cache->get($topicIdOrName);
		}
		
		if ($topic == null) {
			// try the repository
			$request = self::getAstroboaClient($astroboaConfiguration)->getTopicByIdOrName($topicIdOrName, $depth);
			
			if ($request->ok()) {
				$topic = $request->getResponseBodyAsArray();
				
				if (!empty($cache)) {
					if ($cacheDefaultExpirationInSeconds == null) {
							if (!empty($astroboaConfiguration['cache']['CACHE_DEFAULT_EXPIRATION_IN_SECONDS_TOPIC'])) {
								$cacheDefaultExpirationInSeconds = $astroboaConfiguration['cache']['CACHE_DEFAULT_EXPIRATION_IN_SECONDS_TOPIC'];
							}
							else {
								$cacheDefaultExpirationInSeconds = 0;
								error_log('No cache expiration time was provided and the "CACHE_DEFAULT_EXPIRATION_IN_SECONDS_TOPIC" has not been set in configuration file (astroboa.ini). The topic will be cached without expiration');
							}
					}
				
					$cache->set($topicIdOrName, $topic, $cacheDefaultExpirationInSeconds);
				}
				
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
	
	
	/** 
	* A safer function than PHP strip_tags for removing tags and javascript code from posted user content.
	* Removes tags and everything inbetween them.
	* PHP strip_tags removes only the tags and leaves the inbetween content so it is possible for a
	* someone to create multiple nested tags and fool the strip_tags function
	* 
	* @param A string containing xml tags (even non closing tags and nested tags are ok)
	* @return A string with all the tags stripped out, including the tag content
	**/ 
	public static function strip_tags_and_tag_content($string) {
		// Match <n>n</n> – open and close tags and everything in between
		$regex = '/<[^>]*>[^<]*<\/[^>]*>/';
		$string = preg_replace($regex, '', $string);
	
		// Match <n /> – xhtnl inline tags like img and br
		$regex = '/<[^>]*\/>/';
		$string = preg_replace($regex, '', $string);
	
		// Match <n> – single tag only like strip_tags()
		// Used for multiple nested tags
		$regex = '/<[^>]*[^<]>/';
		$string = preg_replace($regex, '', $string);
	
		// Match n> – text then the close of an opening tag
		// Cleans up mess left over from previous replace
		$regex = '/[a-zA-Z]+>/';
		$string = preg_replace($regex, '', $string);
	
		// Clean up – Replace double space with only one space
		$string = str_replace('  ', ' ', $string);
	
		// Clean up – Replace less than with nothing
		$string = str_replace('<', '', $string);
	
		// Clean up – Replace greater than with nothing
		$string = str_replace('>', '', $string);
	
		// Return the stripped string
		return $string;
	}
	
	
	public static function closetags($content) {
		preg_match_all('#<(?!meta|img|br|hr|input\b)\b([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $content, $result);
		$openedtags = $result[1];
		preg_match_all('#</([a-z]+)>#iU', $content, $result);
		$closedtags = $result[1];
		$len_opened = count($openedtags);
		if (count($closedtags) == $len_opened) {
			return $content;
		}
		$openedtags = array_reverse($openedtags);
		for ($i=0; $i < $len_opened; $i++) {
			if (!in_array($openedtags[$i], $closedtags)) {
				$content .= '</'.$openedtags[$i].'>';
			} else {
				unset($closedtags[array_search($openedtags[$i], $closedtags)]);
			}
		}
		return $content;
	}
	
	/* download a file */
	public static function downloadFile($fullPathToFile, $fileName) {
		$mtime = ($mtime = filemtime($fullPathToFile)) ? $mtime : gmtime();
		$size = intval(sprintf("%u", filesize($fullPathToFile)));
		
		// Maybe the problem is we are running into PHPs own memory limit, so:
	//	if (intval($size + 1) > return_bytes(ini_get('memory_limit')) && intval($size * 1.5) <= 1073741824) { //Not higher than 1GB
	//		ini_set('memory_limit', intval($size * 1.5));
	//	}
		// Maybe the problem is Apache is trying to compress the output, so:
		@apache_setenv('no-gzip', 1);
		@ini_set('zlib.output_compression', 0);
		
		// Maybe the client doesn't know what to do with the output so send a bunch of these headers:
		header("Content-type: application/force-download");
		header('Content-Type: application/octet-stream');
		//if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE") != false) {
		//	header("Content-Disposition: attachment; filename=" . urlencode(basename($F['FILE']['file_path'])) . '; modification-date="' . date('r', $mtime) . '";');
		//} else {
		//	header("Content-Disposition: attachment; filename=\"" . basename($F['FILE']['file_path']) . '"; modification-date="' . date('r', $mtime) . '";');
		//}
		header("Content-Disposition: attachment; filename=" . urlencode($fileName) . '; modification-date="' . date('r', $mtime) . '";');
		
		// Set the length so the browser can set the download timers
		header("Content-Length: " . $size);
		
		// If it's a large file we don't want the script to timeout, so:
		set_time_limit(300);
		
		// If it's a large file, readfile might not be able to do it in one go, so:
		$chunksize = 1 * (1024 * 1024); // how many bytes per chunk
		if ($size > $chunksize) {
			$handle = fopen($fullPathToFile, 'rb');
			$buffer = '';
			while (!feof($handle)) {
				$buffer = fread($handle, $chunksize);
				echo $buffer;
				ob_flush();
				flush();
			}
			fclose($handle);
		} else {
			readfile($fullPathToFile);
		}
		// Exit successfully. We could just let the script exit
		// normally at the bottom of the page, but then blank lines
		// after the close of the script code would potentially cause
		// problems after the file download.
		exit;
	}
	
	// methods for sending mail
	public static function sendHtmlMailThroughGmail(
		$astroboaConfiguration,
		$fromAddress, $fromFirstName, $fromLastName, 
		$toAddressList, // array(array('address'=>'myaddress@mydomain.com', 'firstName'=>'My Given Name', 'lastName'=>'My Family Name'), array('address'=>'myaddress2@mydomain.com', 'firstName'=>'Other Given Name', 'lastName'=>'Other Family Name')) 
		$replyToAddress, $replyToFirstName, $replyToLastName, 
		$subject, 
		$htmlMessage, $altBody,
		$arrayOfFullPathsToAttachments) {

		$mail = new PHPMailer(true); // the true param means it will throw exceptions on errors, which we need to catch

		$mail->IsSMTP(); // telling the class to use SMTP
		
		try {
			//$mail->SMTPDebug  = 2;						// enables SMTP debug information (for testing)
			$mail->SMTPAuth   = true;					// enable SMTP authentication
			$mail->SMTPSecure = "ssl";					// sets the prefix to the servier
			$mail->Host       = "smtp.gmail.com";		// sets GMAIL as the SMTP server
			$mail->Port       = 465;					// set the SMTP port for the GMAIL server
		  
		  	// GMAIL username
			if (!empty($astroboaConfiguration['mail']['GMAIL_ACCOUNT'])) {
				$mail->Username   = $astroboaConfiguration['mail']['GMAIL_ACCOUNT'];
			}
			else {
				throw new Exception("Please specify the 'GMAIL_ACCOUNT' configuration parameter in 'astroboa.ini', eg. 'myusername@gmail.com' or 'myusername@mycompany.com' if your company emails are managed by google");
			}
		
			// GMAIL password
			if (!empty($astroboaConfiguration['mail']['GMAIL_PASSWORD'])) {
				$mail->Password   = $astroboaConfiguration['mail']['GMAIL_PASSWORD'];
			}
			else {
				throw new Exception("Please specify the 'GMAIL_ACCOUNT' configuration parameter in 'astroboa.ini'");
			}
		  
			$fromName = '';
			if (!empty($fromFirstName) && !empty($fromLastName)) {
				$fromName = $fromFirstName . ' ' . $fromLastName;
			}
			else if (!empty($fromFirstName) && empty($fromLastName)) {
				$fromName = $fromFirstName;
			}
			else if (empty($fromFirstName) && !empty($fromLastName)) {
				$fromName = $fromLastName;
			}
			$mail->SetFrom($fromAddress, $fromName);
			
			foreach ($toAddressList as $addressEntry) {
				if (empty($addressEntry['address'])) {
					error_log('Skipping entry in address list since the email address is missing');
					break;
				}
				
				$toName = '';
				if (!empty($addressEntry['firstName']) && !empty($addressEntry['lastName'])) {
					$toName = $addressEntry['firstName'] . ' ' . $addressEntry['lastName'];
				}
				else if (!empty($addressEntry['firstName']) && empty($addressEntry['lastName'])) {
					$toName = $addressEntry['firstName'];
				}
				else if (empty($addressEntry['firstName']) && !empty($addressEntry['lastName'])) {
					$toName = $addressEntry['lastName'];
				}
				$mail->AddAddress($addressEntry['address'], $toName);
			}
			
			$replyToName = '';
			if (!empty($replyToFirstName) && !empty($replyToLastName)) {
				$replyToName = $replyToFirstName . ' ' . $replyToLastName;
			}
			else if (!empty($replyToFirstName) && empty($replyToLastName)) {
				$replyToName = $replyToFirstName;
			}
			else if (empty($toFirstName) && !empty($toLastName)) {
				$replyToName = $replyToLastName;
			}
			$mail->AddReplyTo($replyToAddress, $replyToName);
			
			$mail->Subject = $subject;
			
			if (!empty($htmlMessage)) {
				$mail->MsgHTML($htmlMessage);
				
				if (empty($altBody)) {
					$altBody = 'To view the message, please use an HTML compatible email viewer!';
				}
				else {
				$mail->AltBody = $altBody;
				}
			
			}
			else {
				if (!empty($altBody)) {
					$mail->Body = $altBody;
				}
				else {
					error_log("You should provide at least one html message or one plain text message (altBody)");
				}
			}
			
			if (!empty($arrayOfFullPathsToAttachments)) {
				foreach ($arrayOfFullPathsToAttachments as $fullPathToAttachment) {
					$mail->AddAttachment($fullPathToAttachment); 
				}
			} 
			
		  	$result= $mail->Send();
		  	if($result) {
				error_log("Message Sent OK. From: " . $fromAddress);
		  	}
		  	else {
		  		error_log("Failed to send Message. From: " . $fromAddress);
		  	}
			return $result;
			
		} catch (phpmailerException $e) {
			error_log($e->errorMessage()); //error messages from PHPMailer
			return false;
		} catch (Exception $e) {
			error_log($e->getMessage()); //error messages from anything else!
			return false;
		}
	}
	
	public static function sendNotificationForNewRegistration($person, $toAddressList, $astroboaConfiguration) {
		$subject = 'User: ' . $person['profile']['title'] . ' has been registered';
		
		$message = 
			'<b>First Name: </b>' . $person['name']['givenName'] . '<br/>' .
			'<b>Last Name: </b>' . $person['name']['familyName'] . '<br/>' .
			'<b>Birthday: </b>' . $person['birthday'] . '<br/>' .
			'<b>Gender: </b>' . $person['gender']['name'] . '<br/>';
		
		$result = self::sendHtmlMailThroughGmail($astroboaConfiguration,
			$astroboaConfiguration['mail']['FROM_ADDRESS_NEW_REGISTRATION_NOTIFICATIONS'], 
			$astroboaConfiguration['mail']['FIRST_NAME_NEW_REGISTRATION_NOTIFICATIONS'], 
			$astroboaConfiguration['mail']['LAST_NAME_NEW_REGISTRATION_NOTIFICATIONS'], 
			$toAddressList, 
			$astroboaConfiguration['mail']['REPLY_TO_ADDRESS_NEW_REGISTRATION_NOTIFICATIONS'], 
			$astroboaConfiguration['mail']['REPLY_TO_FIRST_NAME_NEW_REGISTRATION_NOTIFICATIONS'], 
			$astroboaConfiguration['mail']['REPLY_TO_LAST_NAME_NEW_REGISTRATION_NOTIFICATIONS'],
			$subject, $message, '', 
			null);
			
		return $result;
	}
	
	public static function sendAccountActivationMessage($person, $smarty, $astroboaConfiguration) {
		$subject = $astroboaConfiguration['mail']['SUBJECT_PREFIX_ACCOUNT_ACTIVATION_MESSAGES'] . $person['profile']['title'];
		$replyAddress = $astroboaConfiguration['mail']['REPLY_TO_ADDRESS_ACCOUNT_ACTIVATION_MESSAGES'];
		$replyToFirstName = $astroboaConfiguration['mail']['REPLY_TO_FIRST_NAME_ACCOUNT_ACTIVATION_MESSAGES'];
		$replyToLastName = $astroboaConfiguration['mail']['REPLY_TO_LAST_NAME_ACCOUNT_ACTIVATION_MESSAGES'];
		$toAddress = 
			array(
				array('address'=>$person['emails']['email'][0]['emailAddress'], 'firstName'=>$person['name']['givenName'], 'lastName'=>$person['name']['familyName'])
			);
			
		$smarty->assign('person', $person);
		$textMessage = $smarty->fetch($astroboaConfiguration['mail']['TEXT_MESSAGE_TEMPLATE_ACCOUNT_ACTIVATION_MESSAGES']);
		
		$htmlMessage = $smarty->fetch($astroboaConfiguration['mail']['HTML_MESSAGE_TEMPLATE_ACCOUNT_ACTIVATION_MESSAGES']);
		
		$result = self::sendHtmlMailThroughGmail($astroboaConfiguration,
			$astroboaConfiguration['mail']['FROM_ADDRESS_ACCOUNT_ACTIVATION_MESSAGES'], 
			$astroboaConfiguration['mail']['FIRST_NAME_ACCOUNT_ACTIVATION_MESSAGES'], 
			$astroboaConfiguration['mail']['LAST_NAME_ACCOUNT_ACTIVATION_MESSAGES'],
			$toAddress,
			$replyAddress, $replyToFirstName, $replyToLastName,
			$subject, $htmlMessage, $textMessage,
			null);
		
		return $result;
	}
	
	public static function sendPasswordResetMessage($person, $smarty, $astroboaConfiguration) {
		$subject = $astroboaConfiguration['mail']['SUBJECT_PREFIX_PASSWORD_RESET_MESSAGES'] . $person['profile']['title'];
		$replyAddress = $astroboaConfiguration['mail']['REPLY_TO_ADDRESS_PASSWORD_RESET_MESSAGES'];
		$replyToFirstName = $astroboaConfiguration['mail']['REPLY_TO_FIRST_NAME_PASSWORD_RESET_MESSAGES'];
		$replyToLastName = $astroboaConfiguration['mail']['REPLY_TO_LAST_NAME_PASSWORD_RESET_MESSAGES'];
		
		$toAddress = 
			array(
				array('address'=>$person['emails']['email'][0]['emailAddress'], 'firstName'=>$person['name']['givenName'], 'lastName'=>$person['name']['familyName'])
			);
			
		$smarty->assign('person', $person);
		$textMessage = $smarty->fetch($astroboaConfiguration['mail']['TEXT_MESSAGE_TEMPLATE_PASSWORD_RESET_MESSAGES']);
		
		$htmlMessage = $smarty->fetch($astroboaConfiguration['mail']['HTML_MESSAGE_TEMPLATE_PASSWORD_RESET_MESSAGES']);
		
		$result = self::sendHtmlMailThroughGmail($astroboaConfiguration,
			$astroboaConfiguration['mail']['FROM_ADDRESS_PASSWORD_RESET_MESSAGES'], 
			$astroboaConfiguration['mail']['FIRST_NAME_PASSWORD_RESET_MESSAGES'], 
			$astroboaConfiguration['mail']['LAST_NAME_PASSWORD_RESET_MESSAGES'],
			$toAddress,
			$replyAddress, $replyToFirstName, $replyToLastName,
			$subject, $htmlMessage, $textMessage,
			null);
		
		return $result;
	}
	
	
	
	// Facebook Connect Methods
	public static function getFacebook($astroboaConfiguration) {
	if (empty($astroboaConfiguration) ||
			empty($astroboaConfiguration['social']['FACEBOOK_APPID']) ||
			empty($astroboaConfiguration['social']['FACEBOOK_SECRET'])
		) {
			throw new Exception('Please provide the required facebook client initialization parameters in astroboa.ini file. A sample file exists in the root directory of the astroboa client lib');
		}
		
		$facebook = new Facebook(array(
 			'appId'  => $astroboaConfiguration['social']['FACEBOOK_APPID'],
			'secret' => $astroboaConfiguration['social']['FACEBOOK_SECRET'],
			'cookie' => true,
		));
		
		return $facebook;
	}
	
	/**
	* Get a facebook Login URL
   	*
	* The parameters to the facebook client are:
	* - next: the url to go to after a successful login
	* - cancel_url: the url to go to after the user cancels
	* - req_perms: comma separated list of requested extended perms
	* - display: can be "page" (default, full page) or "popup"
	*
	* @astroboaConfiguration Array $astroboaConfiguration provides the required parameters for initializing the facebook client
	* @return String the URL for the login flow
	*/
	public static function getFacebookLoginUrl($astroboaConfiguration) {
		
		if (empty($astroboaConfiguration) ||
			empty($astroboaConfiguration['social']['FACEBOOK_SUCCESS_CALLBACK_URL']) ||
			empty($astroboaConfiguration['social']['FACEBOOK_CANCEL_CALLBACK_URL']) ||
			empty($astroboaConfiguration['social']['FACEBBOK_REQUESTED_PERMISSIONS'])
		) {
			throw new Exception('Please provide the required facebook client initialization parameters in astroboa.ini file. A sample file exists in the root directory of the astroboa client lib');
		}

		$params = array(
				'next' => $astroboaConfiguration['social']['FACEBOOK_SUCCESS_CALLBACK_URL'],
				'cancel_url' => $astroboaConfiguration['social']['FACEBOOK_CANCEL_CALLBACK_URL'],
				'req_perms' => $astroboaConfiguration['social']['FACEBBOK_REQUESTED_PERMISSIONS']
		);
		
		$facebook = self::getFacebook($astroboaConfiguration);
		$facebookLoginUrl = $facebook->getLoginUrl($params);
		return $facebookLoginUrl;
	}
	
	/**
	* Get a linked in Login URL
   	*
	* The parameters:
	* - next: the url to go to after a successful login
	* - cancel_url: the url to go to after the user cancels
	* - req_perms: comma separated list of requested extended perms
	* - display: can be "page" (default, full page) or "popup"
	*
	* @param Array $params provide custom parameters
	* @return String the URL for the login flow
	*/
	public static function getLinkedinLoginUrl($params=null) {
		if (empty($astroboaConfiguration) ||
			empty($astroboaConfiguration['social']['LINKEDIN_SUCCESS_CALLBACK_URL']) ||
			empty($astroboaConfiguration['social']['LINKEDIN_CANCEL_CALLBACK_URL']) ||
			empty($astroboaConfiguration['social']['LINKEDIN_REQUESTED_PERMISSIONS'])
		) {
			throw new Exception('Please provide the required linkedin client initialization parameters in astroboa.ini file. A sample file exists in the root directory of the astroboa client lib');
		}

		$params = array(
				'next' => $astroboaConfiguration['social']['LINKEDIN_SUCCESS_CALLBACK_URL'],
				'cancel_url' => $astroboaConfiguration['social']['LINKEDIN_CANCEL_CALLBACK_URL'],
				'req_perms' => $astroboaConfiguration['social']['LINKEDIN_REQUESTED_PERMISSIONS']
		);

		// TODO
		$linkedinLoginUrl = '';
		return $linkedinLoginUrl;
	}
	
	// check if the user agent is a spider
	public static function user_agent_is_a_spider() {
		
		// Add as many spiders you want in this array  
		$spiders = array(  
			'Googlebot', 'Yammybot', 'Openbot', 'Yahoo', 'Slurp', 'msnbot',  
			'ia_archiver', 'Lycos', 'Scooter', 'AltaVista', 'Teoma', 'Gigabot',  
			'Googlebot-Mobile', 'Googlebot-Image' 
		);  
  
		// Loop through each spider and check if it appears in  
		// the User Agent  
		foreach ($spiders as $spider) {  
			$pattern = '/'.$spider.'/i';
			if (preg_match($pattern, $_SERVER['HTTP_USER_AGENT'])) {  
				return TRUE;  
			}  
		}
		return FALSE;
	}
	
	/**
	* This method can be used at the initiation of each http request
	* to handle the starting of the user session.
	* The method does some initialization to required session parameters in order to properly
	* haldle session expiration, it then starts the session using the related PHP function and goes on
	* to: 
	* - check and manually timeout the user session if it is has expired.
	* - take care of regenerating session ids every n minutes to prevent session fixation attacks.
	* - handle the remember me functionality
	* 
	* Both the session expiration time and the session regeneration time can be configured in your
	* astroboa.ini file using the provided configuration properties:
	* [session]
	* ; The user idle time in seconds after which the session will expire (default is 60 minutes = 60 * 60)
	* SESSION_EXPIRATION_IN_SECONDS_OF_IDLE_TIME = 3600
	* ; The seconds after session creation that a new session id will be created (default is 1 minute = 60 * 1)
	* SESSION_ID_REGENERATION_IN_SECONDS_AFTER_SESSION_CREATION = 60
	*
	* The defaults are set to 60 minutes and 1 minute respectively
	*  
	*  Since we manually expire the session here we also take care to appropriately set the session.gc_maxlifetime 
	*  to be at least equal to the configured life time. The default value for 
	*  session.cookie_lifetime is 0 that means when the browser is closed, so we also set it to be equal to 
	*  to the configured life time. We also set the propability of gc run to 1%
	*/
	public static function sessionStart($astroboaConfiguration) {
		
		$sessionExpirationSeconds = 3600;
		$sessionRegenerationSeconds = 60;
		$persistentLoginCookieName = 'site-plc';
		$objectTypeForPersonProfiles = 'personObject';
		
		if (!empty($astroboaConfiguration['session']['SESSION_EXPIRATION_IN_SECONDS_OF_IDLE_TIME'])) {
			$sessionExpirationSeconds = $astroboaConfiguration['session']['SESSION_EXPIRATION_IN_SECONDS_OF_IDLE_TIME'];
		}
		
		if (!empty($astroboaConfiguration['session']['SESSION_ID_REGENERATION_IN_SECONDS_AFTER_SESSION_CREATION'])) {
			$sessionRegenerationSeconds = $astroboaConfiguration['session']['SESSION_ID_REGENERATION_IN_SECONDS_AFTER_SESSION_CREATION'];
		}
		
		if (!empty($astroboaConfiguration['session']['PERSISTENT_LOGIN_COOKIE_NAME'])) {
			$persistentLoginCookieName = $astroboaConfiguration['session']['PERSISTENT_LOGIN_COOKIE_NAME'];
		}
		
		if (!empty($astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES'])) {
			$objectTypeForPersonProfiles = $astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES'];
		}
		
		// set session parameters
		ini_set('session.cookie_lifetime', $sessionExpirationSeconds); // browser cookie deletion on browser close
		ini_set('session.gc_maxlifetime', $sessionExpirationSeconds);
		ini_set('session.gc_probability', 1); // see next line...
		ini_set('session.gc_divisor', 100); // in combination with previous ini_set, this will make GC run on 1% of the requests
		
		// start the session
		session_start();
		
		// handle remember me
		$persistentLoginCookie = null;
		$usernameInCookie = null;
		$hashCodeInCookie = null;
		error_log('Starting user session. Checking for persistent login cookie. plc-name:' . $persistentLoginCookieName);
		if (empty($_SESSION['user']) && !empty($_COOKIE[$persistentLoginCookieName])) {
				$persistentLoginCookie = $_COOKIE[$persistentLoginCookieName];
				$cookieParts = explode('-', $persistentLoginCookie);
				$usernameInCookie = $cookieParts[0];
				$hashCodeInCookie = $cookieParts[1];
				
				error_log('Found persistent login cookie for not logged in user. The username in cookie is: ' . $usernameInCookie . ' We will validate the cookie in order to auto-login the user.');
				
				try {
					$cmsQuery = 'contentTypeName="' . $objectTypeForPersonProfiles . '" AND personAuthentication.username="' . $usernameInCookie . '" AND persistentLoginHashCode="' . $hashCodeInCookie . '"';
					$request = self::getSecureAstroboaClient($astroboaConfiguration)->getObjectCollection($cmsQuery, null, 0, 1, null);
					
					if ($request->ok()) {
						$users = $request->getResponseBodyAsArray();
						if ($users['totalResourceCount'] == 1) {
							error_log('Found user with username: ' . $usernameInCookie . ' and valid persistent login hash code. User will be auto-loggedin');
								
							$user = $users['resourceCollection']['resource'][0];
							$_SESSION['user'] = $user;
								
						}
						else if ($users['totalResourceCount'] == 0) {
							error_log('No accounts found for the given persistest login cookie. Cookie='. $persistentLoginCookie);
						}
						else if ($users['totalResourceCount'] > 1){
							error_log('More than one accounts found for the given persistest login cookie. Cookie ='. $persistentLoginCookie);
						}
					}
					else {
						$responseInfo = $request->getResponseInfo();
						error_log('The query that searches users with persistent login cookies returned with error code: ' 
						. $responseInfo['http_code'] . 'username='.$usernameInCookie);
					}
				}
				catch (Exception $e) {
					error_log('An error occured  while trying to establish a persistent user login. username='. $usernameInCookie. ' The exception is: ' . $e->getTraceAsString());
				}
		}
		
		// timeout session if idle more than $sessionExpirationMinutes and no valid remember me cookie exists
		if (isset($_SESSION['LAST_ACTIVITY']) && 
			(time() - $_SESSION['LAST_ACTIVITY'] > $sessionExpirationSeconds) && 
			!self::validPersistentLoginHashCodeExistsForLoggedInUser($usernameInCookie, $hashCodeInCookie)) {
			
			$_SESSION = array();
			if (ini_get("session.use_cookies")) {
				$params = session_get_cookie_params();
				setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
				);
			}
			session_destroy();   // destroy session data in storage
			session_unset();     // unset $_SESSION variable for the runtime
		}
		$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
		
		// regenerate session id if the session id has been created more than $sessionRegenerationMinutes ago
		if (!isset($_SESSION['CREATED'])) {
			$_SESSION['CREATED'] = time();
		} else if (time() - $_SESSION['CREATED'] > $sessionRegenerationSeconds) {
			// session started more than 30 minutes ago
			session_regenerate_id(true);    // change session ID for the current session and invalidate old session ID
			$_SESSION['CREATED'] = time();  // update creation time
		}
		
	}
	
	/**
	 * This method checks if the user is logged in and a valid persistent login cookie is set in her browser.
	 * It is used in order to prevent session expiration for logged in users that have requested to have persistent loggin sessions (remember me)
	 */
	protected static function validPersistentLoginHashCodeExistsForLoggedInUser($usernameInCookie, $hashCodeInCookie) {
		if (!empty($_SESSION['user']) && !empty($usernameInCookie) && !empty($hashCodeInCookie) && $_SESSION['user']['personAuthentication']['username'] == $usernameInCookie) {
			$storedHashCodes = $_SESSION['user']['persistentLoginHashCode'];
			foreach ($storedHashCodes as $storedHashCode) {
				if ($hashCodeInCookie == $storedHashCode) {
					return true;
				}
			}
		}
		
		return false;
	}
	
	// methods for calling processes
	public static function executeWithTimeout($command, $timeout = 60, $sleep = 2) {
        // First, execute the process, get the process ID

        $pid = self::execute($command);

        if( $pid === false )
            return false;

        $cur = 0;
        // Second, loop for $timeout seconds checking if process is running
        while( $cur < $timeout ) {
            sleep($sleep);
            $cur += $sleep;
            // If process is no longer running, return true;

           echo "\n ---- $cur ------ \n";

            if( !self::processExists($pid) )
                return true; // Process must have exited, success!
        }

        // If process is still running after timeout, kill the process and return false
        self::processKill($pid);
        return false;
    }

    public static function execute($commandJob) {

        $command = $commandJob.' > /tmp/groovy.error 2>&1 & echo $!';
        exec($command ,$op);
        $pid = (int)$op[0];

        if($pid!="") return $pid;

        return false;
    }

    public static function processExists($pid) {

        exec("ps ax | grep $pid 2>&1", $output);

        while( list(,$row) = each($output) ) {

                $row_array = explode(" ", $row);
                $check_pid = $row_array[0];

                if($pid == $check_pid) {
                        return true;
                }

        }

        return false;
    }

    public static function processKill($pid) {
        exec("kill -9 $pid", $output);
    }
    
    public static function startsWith($string, $prefix){
    	$length = strlen($prefix);
    	return (substr($string, 0, $length) === $prefix);
    }
    
    public static function endsWith($string, $suffix){
    	$length = strlen($suffix);
    	$start  = $length * -1; //negative
    	return (substr($string, $start) === $suffix);
    }
    
  	/**
  	 * 
  	 * Return a connection to a Messaging Queue Server or
  	 * null if the use of the Messaging  Server has been disabled
  	 * 
  	 * Keep in mind that it is the caller's responsibility to 
  	 * close the connection.
  	 * 
  	 * @param unknown_type $astroboaConfiguration
  	 */  
    public static function getConnectionToMessageServer($astroboaConfiguration){
    	
    	if ($astroboaConfiguration['messaging-server']['MESSAGING_SERVER_ENABLE'] == '1'){
	    	return new AMQPConnection(
	    		$astroboaConfiguration['messaging-server']['MESSAGING_SERVER_HOST'],
	    		$astroboaConfiguration['messaging-server']['MESSAGING_SERVER_PORT'],
	    		$astroboaConfiguration['messaging-server']['MESSAGING_SERVER_USERNAME'],
	    		$astroboaConfiguration['messaging-server']['MESSAGING_SERVER_PASSWORD'],
	    		$astroboaConfiguration['messaging-server']['MESSAGING_SERVER_VIRTUAL_HOST']
	    	);
    	}
    	
    	return null;
    	
    }
    
    
    
}
?>