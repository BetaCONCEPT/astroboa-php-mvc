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

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Controller.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'AccountStatus.php');

class FacebookController extends Controller {
	const FACEBOOK_GENERIC_ERROR_MESSAGE = 'We could not retreive your facebook account.';
	const FACEBOOK_ERROR_RESOLUTION_RECOMMENTATION = 'Please try again. If the problem persists please sent us an e-mail';
	const FACEBOOK_NO_EMAIL = 'We could not retreive your email from your facebook account.';
	const FACEBOOK_NO_FIRST_NAME = 'We could not retreive your first name from your facebook account.';
	const FACEBOOK_NO_LAST_NAME = 'We not could retreive your last (family) name from your facebook account.';
	const FACEBOOK_NO_NAME = 'We could not retreive your name from your facebook account.';
	const FACEBOOK_NO_ID = 'We could not retreive your user id from your facebook account.';
	
	protected $redirect_header; 
	protected $redirect_header_authmessage;
	
	public function __construct() {
		$this->redirect_header = 'Location: http://' . $this->astroboaConfiguration['site']['SITE_ADDRESS'];
		$this->redirect_header_authmessage = 'Location: http://' . $this->astroboaConfiguration['site']['SITE_ADDRESS'] . '/auth/message';
	}
	
	
	public function login($args) {	
		
		// the user should not be given log in buttons if she is already logged in but just in case that the web site has a bug we check
		if(isset($_SESSION['user'])) {
			error_log('Already logged in user tries to login. The logged in user is:' . $_SESSION['user']['profile']['title']);
			$_SESSION['authMessage'] = 'You are already logged in ' . $_SESSION['user']['name']['givenName'] . '. Please logout first so you can log in as a different user.';
			header($this->redirect_header_authmessage);
		} 
		
		
		$facebook = Util::getFacebook($this->astroboaConfiguration);
		
		$facebookSession = $facebook->getSession();
	
		$user = null;
		$uid = null;
		
		if ($facebookSession) {
			$uid = $facebook->getUser();
			error_log('user: ' . $uid . 'is logging with facebook'); 

			try {				
				$user = $facebook->api('/me');
				
				if ($user) {
					
					// lets first try to compose a person object from the facebook user data
					$person = $this->createPersonFromFacebookUser($user);
					
					if ($person == null) {
						error_log('Incomplete user data found in facebook session. Could not complete user login or registration');
						header($this->redirect_header_authmessage);
						return;
					}
					
					error_log('Succefully retrieved user data from facebook session. The user is: ' . $person['profile']['title']);
					
					$facebookAccessToken = $facebook->getAccessToken();
					$storedPerson = null;
					
					$accountStatus = $this->checkAccountStatus($person, $storedPerson);
					
					error_log('Checking account status for facebook user: ' .  $person['profile']['title'] . '. Status is: ' . $accountStatus);
					
					switch ($accountStatus) {
						case AccountStatus::REGISTERED:
							error_log('facebook user with id: ' .  $uid . ' is REGISTERED. Activating the login session');
							$this->activateLoginSession($storedPerson, $facebookAccessToken);
							header($this->redirect_header);
						break;
						
						case AccountStatus::NOT_REGISTERED:
							error_log('facebook user with id: ' .  $uid . ' is NOT REGISTERED. Proceeding to registration');
							if ($this->register($person)) {
								error_log('facebook user with id: ' .  $uid . 'has been succesfully REGISTERED. Activating the login session');
								$this->activateLoginSession($person, $facebookAccessToken);
								header($this->redirect_header);
							}
							else {
								error_log('facebook user with id: ' .  $uid . ' could not be REGISTERED.');
								header($this->redirect_header_authmessage);
							}
						break;
						
						case AccountStatus::REGISTERED_BUT_UPDATED_USER_DATA:
							// we reregister the updated person data
							error_log('facebook user with id: ' .  $uid . ' is REGISTERED but she has changed some of her remote profile information. Proceeding to update the local profile');
							if ($this->register($storedPerson)) {
								error_log('facebook user with id: ' .  $uid . ' has been succesfully UPDATED (remote profile changes have been merged to local profile). Activating the login session');
								$this->activateLoginSession($storedPerson, $facebookAccessToken);
								header($this->redirect_header);
							}
							else {
								error_log('facebook user with id: ' .  $uid . ' could not be UPDATED (remote profile changes could NOT be merged to local profile). Login will fail');
								header($this->redirect_header_authmessage);
							}
						break;
						
						case AccountStatus::DUBLICATE_REGISTRATION:
							error_log('facebook user with id: ' .  $uid . ' has a DUBLICATE REGISTRATION. The user will be informed and login will fail');
							header($this->redirect_header_authmessage);
						break;
						
						case AccountStatus::REGISTRATION_DATA_CURRENTLY_INACCESSIBLE:
							error_log('Could not retrieve / validate local account registration data for facebook user with id: ' .  $uid . ' The user will be informed and login will fail');
							header($this->redirect_header_authmessage);
						break;
						
						default:
							error_log('Could not retrieve account registration status for facebook user with id: ' .  $uid . ' The user will be informed and login will fail');
							$_SESSION['authMessage'] = 'It is not currently possible to verify your account in our records. Please try again later or contact us at ' . $this->astroboaConfiguration['mail']['SITE_ADMIN_EMAIL'] .  ' to help you resolve the issue. We apologize for the inconvenience.';
							header($this->redirect_header_authmessage);
						break;
					}
					
				}
				else {
					error_log('Could not retrieve user data for facebook user with id:' . $uid);
					$_SESSION['authMessage'] = self::FACEBOOK_GENERIC_ERROR_MESSAGE . ' ' . self::FACEBOOK_ERROR_RESOLUTION_RECOMMENTATION;
					header($this->redirect_header_authmessage);
				}
		    
			} catch (FacebookApiException $e) {
				error_log('Unsuccesful Facebook Login. Exception when accessing Facebook API to retrieve user data for facebook user with id:' . $uid . 'The exception is: ' . $e);
				$_SESSION['authMessage'] = self::FACEBOOK_GENERIC_ERROR_MESSAGE . ' ' . self::FACEBOOK_ERROR_RESOLUTION_RECOMMENTATION;
				header($this->redirect_header_authmessage);
			}
		}
		else {
			error_log('Unsuccesful Facebook Login. No facebook Session exists');
			$_SESSION['authMessage'] = self::FACEBOOK_GENERIC_ERROR_MESSAGE . ' ' . self::FACEBOOK_ERROR_RESOLUTION_RECOMMENTATION;
			header($this->redirect_header_authmessage);
		}
		
	}
	
	
	protected function createPersonFromFacebookUser($user) {
		$person = array(
			"contentObjectTypeName" => $this->astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES']
		);
		
		if (!empty($user['email'])) {
			$person['emails']['email'][0] = 
				array(
						'emailAddress' => $user['email'],
						'type' => $this->getTopic('facebook-email')							
			);
		}
		else {
			$errorMessage = self::FACEBOOK_NO_EMAIL . ' ' . self::FACEBOOK_ERROR_RESOLUTION_RECOMMENTATION;
			error_log($errorMessage);
			$_SESSION['authMessage'] = $errorMessage;
			return null;
		}
		
		
		if (!empty($user['first_name'])) {
			$person['name']['givenName'] = $user['first_name'];
		}
		else {
			$errorMessage = self::FACEBOOK_NO_FIRST_NAME . ' ' . self::FACEBOOK_ERROR_RESOLUTION_RECOMMENTATION;
			error_log($errorMessage);
			$_SESSION['authMessage'] = $errorMessage;
			return null;
		}
		
		if (!empty($user['last_name'])) {
			$person['name']['familyName'] = $user['last_name'];
		}
		else {
			$errorMessage = self::FACEBOOK_NO_LAST_NAME . ' ' . self::FACEBOOK_ERROR_RESOLUTION_RECOMMENTATION;
			error_log($errorMessage);
			$_SESSION['authMessage'] = $errorMessage;
			return null;
		}
		
		if (!empty($user['name'])) {
			$person['profile']['title'] = $user['name'];
		}
		else {
			$errorMessage = self::FACEBOOK_NO_NAME . ' ' . self::FACEBOOK_ERROR_RESOLUTION_RECOMMENTATION;
			error_log($errorMessage);
			$_SESSION['authMessage'] = $errorMessage;
			return null;
		}
		
		if (!empty($user['id'])) {
			$person['accounts']['account'][0] = 
			array(
				'domain' => $this->astroboaConfiguration['social']['ACCOUNT_DOMAIN_FACEBOOK'],
				'userid' => $user['id']
			);
		}
		else {
			$errorMessage = self::FACEBOOK_NO_ID . ' ' . self::FfACEBOOK_ERROR_RESOLUTION_RECOMMENTATION;
			error_log($errorMessage);
			$_SESSION['authMessage'] = $errorMessage;
			return null;
		}
		
		
		if (!empty($user['link'])) {
			$person['urls']['webResourceLink'][0] = 
			array(
				'title' => 'Facebook Profile Link',
				'description' => 'User Profile at Facebook',
				'url' => $user['link']
			);
		}
		
	//	if (!empty($user['birthday'])) {
	//		$birthday = $user['birthday'];
	//		$person['birthday'] = $user['birthday'];
	//	}
		
		if (!empty($user['location']['name'])) {
			$location = $user['location']['name'];
		}
		
		if (!empty($user['gender'])) {
			$genderAsTopic = $this->getTopic($user['gender']);
			if (!empty($genderAsTopic)) {
				$person['gender'] = $genderAsTopic;
			}
		}
		
		return $person;
	}
	
	protected function checkAccountStatus($person, &$storedPerson) {
		try {
			$query = 'contentTypeName="' . $this->astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES'] . '" AND accounts.account.domain="' . $this->astroboaConfiguration['social']['ACCOUNT_DOMAIN_FACEBOOK'] .'" AND accounts.account.userid="' . $person['accounts']['account'][0]['userid'] .'"';
			
			// ask for two persons to also check if double registrations exist
			$request = $this->secureAstroboaClient->getObjectCollection($query, null, 0 , 2, null);
			
			if (!$request->ok()) {
				$_SESSION['authMessage'] = 'It is not currently possible to verify your account in our records. Please try again later or contact us at ' . $this->astroboaConfiguration['mail']['SITE_ADMIN_EMAIL'] . ' to help you resolve the issue. We apologize for the inconvenience.';
				return AccountStatus::REGISTRATION_DATA_CURRENTLY_INACCESSIBLE;
			}
			
			$response = $request->getResponseBodyAsArray();
			
			// user is already registered
			if ($response['totalResourceCount'] == 1) {
				
				$storedPerson = $response['resourceCollection']['resource'][0];
				
				// we should check if the user has updated her data kept by the external account provider, eg. the facebook mail is different from the mail we keep
				if (
					$this->updateRegistrationEmail($storedPerson, $person) ||
					$this->updateGivenName($storedPerson, $person) ||
					$this->updateFamilyName($storedPerson, $person) ||
					$this->updateGender($storedPerson, $person)
				) {
					return AccountStatus::REGISTERED_BUT_UPDATED_USER_DATA;		
				}
				else {
 					return AccountStatus::REGISTERED;
				}
 			}
 			
 			// user is not registered
 			if ($response['totalResourceCount'] == 0) {
 				//TODO: We should also check if the user has been registered with the site Registration Form using her facebook mail 
 				
 				return AccountStatus::NOT_REGISTERED;
 			}
 			
			// check for dublicate entries
			if ($response['totalResourceCount'] > 1) {
					$this->smarty->assign('authMessage', 'We have found a doublicate registration for your account. Please contact us at ' . $this->astroboaConfiguration['mail']['SITE_ADMIN_EMAIL'] . ' to resolve the issue. We apologize for the inconvenience.');
					return AccountStatus::DUBLICATE_REGISTRATION;
 			}
 			
 			// just in case no check has succeeded
 			$_SESSION['authMessage'] = 'It is not currently possible to verify your account in our records. Please contact us at ' . $this->astroboaConfiguration['mail']['SITE_ADMIN_EMAIL'] . ' to resolve the issue. We apologize for the inconvenience.';
			return AccountStatus::REGISTRATION_DATA_CURRENTLY_INACCESSIBLE;
 			
		}
		catch (Exception $e) {
			error_log('An error occured while trying to check the account registration status. The error is:' . $e);
			$_SESSION['authMessage'] = 'An error occured while reading your account registration information. We apologize for the inconvenience. Please try again.';
			return AccountStatus::REGISTRATION_DATA_CURRENTLY_INACCESSIBLE;
		}
	}
	
	// we will update the stored registration email if the person that logins has change it and return true
	// if no update is performed we return false
	protected function updateRegistrationEmail(&$storedPerson, $personThatLogins) {
		$newEmail = $personThatLogins['emails']['email'][0]['emailAddress'];
		$newEmailType = $personThatLogins['emails']['email'][0]['type'];
		foreach ($storedPerson['emails']['email'] as $index=>$storedEMail) {
			if ($storedEMail['type'] == $newEmailType && $storedEMail['emailAddress'] != $newEmail) {
				$storedPerson['emails']['email'][$index]['emailAddress'] = $newEmail;
				error_log('user ' . $storedPerson['profile']['title']  . ' has updated the email. The old email is: ' . $storedEMail . '. The new email is: ' . $newEmail);
				return true;
			}
		}
		
		return false;
	}
	
	// we will update the stored given name if the person that logins has change it and return true
	// if no update is performed we return false
	protected function updateGivenName(&$storedPerson, $personThatLogins) {
		$newGivenName = $personThatLogins['name']['givenName'];
		$storedGivenName = $storedPerson['name']['givenName'];
		
		if ($storedGivenName != $newGivenName) {
			$storedPerson['name']['givenName'] = $newGivenName;
			// we should update the person title with its new full name
			$storedPerson['profile']['title'] = $personThatLogins['profile']['title'];
			error_log('user ' . $storedPerson['profile']['title']  . ' has updated the given name. The old given name is: ' . $storedGivenName . '. The new given name is: ' . $newGivenName);
			return true;
		}

		return false;
	}
	
	// we will update the stored family name if the person that logins has change it and return true
	// if no update is performed we return false
	protected function updateFamilyName(&$storedPerson, $personThatLogins) {
		$newFamilyName = $personThatLogins['name']['familyName'];
		$storedFamilyName = $storedPerson['name']['familyName'];
		
		if ($storedFamilyName != $newFamilyName) {
			$storedPerson['name']['familyName'] = $newFamilyName;
			// we should update the person title with its new full name
			$storedPerson['profile']['title'] = $personThatLogins['profile']['title'];
			error_log('user ' . $storedPerson['profile']['title']  . ' has updated the family name. The old family name is: ' . $storedFamilyName . '. The new family name is: ' . $newFamilyName);
			return true;
		}

		return false;
	}
	
	// we will update the stored gender if the person that logins has change it and return true
	// if no update is performed we return false
	protected function updateGender(&$storedPerson, $personThatLogins) {
		$newGender = $personThatLogins['gender'];
		$storedGender = $storedPerson['gender'];
		
		if ($storedGender['name'] != $newGender['name']) {
			$storedPerson['gender'] = $newGender;
			error_log('user ' . $storedPerson['profile']['title']  . ' has updated gender. The old gender is: ' . $storedGender['name'] . '. The new gender is: ' . $newGender['name']);
			return true;
		}

		return false;
	}
	
	protected function register($person) {
		
		try {
			$request = $this->secureAstroboaClient->addObject($person);
			
			if (!$request->ok()) {
				error_log('An error occured while trying to save user profile. The error response from Resource API is:' . $responseInfo['http_code']);
				$_SESSION['authMessage'] = 'An error occured during the creation of your account. We apologize for the inconvenience. Please try again.';
				return false;
			}
			
			return true;
		}
		catch (Exception $e) {
			error_log('An error occured while trying to save user profile. The error is:' . $e);
			$_SESSION['authMessage'] = 'An error occured during the creation of your account. We apologize for the inconvenience. Please try again.';
			return false;
		}
		
		/*
		if (!empty($user['timezone'])) {
			$timezone = $user['timezone'];
		}
		
		if (!empty($user['locale'])) {
			$locale = $user['locale'];
		}
		
		if (!empty($user['verified'])) {
			$profile_verified = $user['verified'];
		}
		
		if (!empty($user['updated_time'])) {
			$profile_update_time = $user['updated_time'];
		}
		
		*/
	}
	
	protected function update($person) {
		
		try {
			$request = $this->secureAstroboaClient->updateObject($person);
			
			if (!$request->ok()) {
				error_log('An error occured while trying to update your user profile. The error response from Resource API is:' . $responseInfo['http_code']);
				$_SESSION['authMessage'] = 'An error occured during the update of your account information. We apologize for the inconvenience. ' . self::FACEBOOK_ERROR_RESOLUTION_RECOMMENTATION;
				return false;
			}
			
			return true;
		}
		catch (Exception $e) {
			error_log('An error occured while trying to save user profile. The error is:' . $e);
			$_SESSION['authMessage'] = 'An error occured during the creation of your account. We apologize for the inconvenience. Please try again.';
			return false;
		}
	}
	
	protected function activateLoginSession($person, $facebookAccessToken) {
		foreach ($person['accounts']['account'] as $index=>$account) {
			if ($account['domain'] == $this->astroboaConfiguration['social']['ACCOUNT_DOMAIN_FACEBOOK']) {
				$person['accounts']['account'][$index]['temporaryAccessToken'] = $facebookAccessToken;
			}
		}
		
		$_SESSION['user'] = $person;
	}
}

?>