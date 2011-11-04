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
 *   @author Savvas Triantafyllou (striantafyllou@betaconcept.com)
 * 
 */
 

 /**
  * 
  *  
  */
 class MessageBundle {
 	
 	const ENGLIGH_LANG = 'en';
 	
 	private $lang;
 	
 	private $astroboaConfiguration;
 	
 	public function __construct($astroboaConfiguration) {
 		
 		$this->astroboaConfiguration = $astroboaConfiguration;
 		
 		$this->lang = self::ENGLIGH_LANG;
 	}
 	
 	
 	public function setLanguage($lang){
 		
 		if ($lang == null){
 			$this->lang = self::ENGLIGH_LANG;
 		}
 		else{
	 		$this->lang = $lang;
 		}
 	}
 	
 	
 	public function getLocalizedMessage($message_key, $args = null){
 		return $this->getLocalizedMessage($this->lang, $message_key, $args);
 	}
 	
 	public function getLocalizedMessageForLang($lang, $message_key, $args = null){
 		
 		if ($message_key == null){
 			return '';
 		}
 		
 		if ($lang == null){
 			$lang = self::ENGLIGH_LANG;
 		}

		//Retrieve topic whose name is the provided message key
		if (!empty($this->astroboaConfiguration['cache']['CACHE_DEFAULT_EXPIRATION_IN_SECONDS_LOCALIZED_VALUE'])) {
			$cacheDefaultExpirationInSeconds = $this->astroboaConfiguration['cache']['CACHE_DEFAULT_EXPIRATION_IN_SECONDS_LOCALIZED_VALUE'];
		}
		else {
			$cacheDefaultExpirationInSeconds = 0;
			error_log('No cache expiration time was provided and the "CACHE_DEFAULT_EXPIRATION_IN_SECONDS_LOCALIZED_VALUE" has not been set in configuration file (astroboa.ini). The topic will be cached without expiration');
		}
		 		
 		$topic = Util::getTopic($message_key, $this->astroboaConfiguration, $cacheDefaultExpirationInSeconds, 0);
 		
 		if ($topic == null){
 			return '';
 		}
 		else {
 			$message = Util::availableLocalizedLabel($topic, $lang);
 			
	 		if ($message == null || empty ($message)){
 				return '';
 			}
 			else{
 				return self::format_message($message, $args);
 			}
 		}
 	}
 	
 	private function format_message($message, $args = null){
 		
 		if ($message == null){
 			return '';
 		}
 		
 		if ($args == null || empty($args)){
 			return $message;
 		}

		$text = $message;
		
		foreach($args as $key=>$value) {
			
			$text = preg_replace("/\{$key\}/", $value, $text);
			
		} 		
 		
 		return $text;
 	}
 	
 }
 
 ?>