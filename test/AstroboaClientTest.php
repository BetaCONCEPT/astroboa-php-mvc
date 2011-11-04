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

require_once('../resource-api/AstroboaClient.php');

class AstroboaClientTest extends PHPUnit_Framework_TestCase {
		
	
	public function test_getAstroboaClient() {
		return $astroboaClient = new AstroboaClient('demo.betaconcept.com', 'astroboa-demo', 'demo', 'astroboa-demo');
	}
	
	
	/**
	* @depends test_getAstroboaClient
	*/
	public function test_addObject(AstroboaClient $astroboaClient) {
		$person = array (
			'contentObjectTypeName' => 'personObject',
			'profile' => array (
				'title' => 'John Smith'
			),
			'name' => array (
				'familyName' => 'Smith',
				'givenName' => 'John'
			)
		);
		
		$request = $astroboaClient->addObject($person);
		$responseInfo = $request->getResponseInfo();
		$this->assertEquals('201', $responseInfo['http_code'], 'Response Status');
		$this->assertEquals(true, $request->ok(), 'Response Status');
		$responseBody = $request->getResponseBody();
		$this->assertEquals(36, strlen($responseBody));
		
		return $request->getResponseBody();
	}
	
	/**
	* @depends test_getAstroboaClient
	* @depends test_addObject
	*/
	public function test_getObjectByIdOrName(AstroboaClient $astroboaClient, $objectId) {
		 $request = $astroboaClient->getObjectByIdOrName($objectId);
		 $responseInfo = $request->getResponseInfo();
		 $responseBodyAsArray = $request->getResponseBodyAsArray();
		 $responseBodyAsObject = $request->getResponseBodyAsObject();
		 
		 $this->assertEquals('200', $responseInfo['http_code'], 'Response Status');
		 $this->assertEquals(true, $request->ok(), 'Response Status');
		 
		 $this->assertEquals('personObject', $responseBodyAsArray['contentObjectTypeName'], 'The object type');
		 $this->assertEquals('John-Smith', $responseBodyAsArray['systemName'], 'The system name of the object');
		 $this->assertEquals('John Smith', $responseBodyAsArray['profile']['title'], 'The object title');
		 
		 $this->assertEquals('personObject', $responseBodyAsObject->contentObjectTypeName, 'The object type');
		 $this->assertEquals('John-Smith', $responseBodyAsObject->systemName, 'The system name of the object');
		 $this->assertEquals('John Smith', $responseBodyAsObject->profile->title, 'The object title');
		 
	}
}

?>