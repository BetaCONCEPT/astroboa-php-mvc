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
* @author Savvas Triantafyllou (striantafyllou@betaconcept.com)
*
*/
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Controller.php');

class RedirectionController extends Controller {


	protected function setPermanentRedirectionHeaders($url){
		
		$host  = $_SERVER['HTTP_HOST'];
		
		header("HTTP/1.1 301 Moved Permanently");
		
		header("Location: http://$host$url");
	}
	
}


?>
