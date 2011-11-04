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

class IdentityController extends Controller {
	
	const REGISTRATION_GENERIC_ERROR_MESSAGE = 'We could not complete your registration.';
	const REGISTRATION_ACCOUNT_CREATION_ERROR = 'An error occured during the creation of your account. We apologize for the inconvenience.';
	const ERROR_RESOLUTION_RECOMMENTATION = 'Please try again. If the problem persists please sent an e-mail to site support team';
	const REGISTRATION_NO_EMAIL = 'We could not retreive the email from the registration form.';
	const REGISTRATION_EMAIL_TAKEN = 'The email address you provided is already taken, please enter a different address.';
	const REGISTRATION_NO_PASSWORD = 'We could not retreive the password from the registration form.';
	const REGISTRATION_PASSWORDS_DIFFER = 'Password differs from Confirmation Password. The registration form failed to do the check. Please correct the form code. Registration will fail.';
	const REGISTRATION_NO_FIRST_NAME = 'We could not retreive the first name from the registration form.';
	const REGISTRATION_NO_LAST_NAME = 'We not could retreive the last (family) name from the registration form.';
	const REGISTRATION_NO_GENDER = 'We could not retreive the gender from the registration form.';
	const REGISTRATION_NO_BIRTHDAY = 'We could not retreive the birthday from the registration form.';
	const SHA_256_ENCRYPTION_ALGORITHM_NOT_INSTALLED = "The required encryption algorithm is not supported by the machine. Please install the required libraries. User Registration cannot proceed";
	const LOGIN_GENERIC_ERROR_MESSAGE = 'We cannot sign you in your Account at this moment.';
	const LOGIN_NO_ACTIVE_USER_ACCOUNT = "Your email and/or password are incorrect or you have not yet activated your account. If you forgot your password use the provided link to reset it.";
	
	private $authMessage = '';
	
	public function login() {
		$person = $this->findRegisteredUser();
		if (!empty($person)) {
			if ($this->isValidPassword($person)) {
				$_SESSION['user'] = $person;
				
				// handle remember me
				if (!empty($_POST['rememberme']) && $_POST['rememberme'] == true) {
					$this->createPersistentLoginCookie($person);
				}
				
				echo $this->createSuccessfulLoginResponse($this->authMessage);
			}
			else {
				echo $this->createResponseMessage('error', $this->authMessage);
			}
		}
		else {
			echo $this->createResponseMessage('error', $this->authMessage);
		}
	}
	
	
	public function logout() {
		// if the user logs out and has set a persistent login cookie (remember me) then
		// we must remove the cookie and also delete the relevant hash code from her account
		$this->removePersistentLoginCookieAndHashCode();
		
		// Unset all of the session variables.
		$_SESSION = array();
	
		// If it's desired to kill the session, also delete the session cookie.
		// Note: This will destroy the session, and not just the session data!
		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 86400,
			$params["path"], $params["domain"],
			$params["secure"], $params["httponly"]
			);
		}
	
		// Finally, destroy the session.
		session_destroy();
		session_unset();
	
		// redirect to the configured URL if it is not empty or else redirect to site home page
		$redirectUrl = 'http://' . $_SERVER['SERVER_NAME'];
		if (!empty($this->astroboaConfiguration['session']['REDIRECT_URL_AFTER_LOGOUT'])) {
			$redirectUrl = $this->astroboaConfiguration['session']['REDIRECT_URL_AFTER_LOGOUT'];
		}
		header('Location: ' . $redirectUrl);
	}
	
	/**
	 * Removes the persistent login cookie from user's browser
	 * and also deletes the related hash code from user's account
	 * 
	 * If the hash code removal fails an appropriate message is logged to 
	 * facilitate manual removal of the hash code. It is advised to regularly check 
	 * the log files in order to locate possible failures and remove the hash codes manually.
	 */
	protected function removePersistentLoginCookieAndHashCode() {
		$persistentLoginCookieName = 'site-plc';
		
		if (!empty($this->astroboaConfiguration['session']['PERSISTENT_LOGIN_COOKIE_NAME'])) {
			$persistentLoginCookieName = $this->astroboaConfiguration['session']['PERSISTENT_LOGIN_COOKIE_NAME'];
		}
		
		if (!empty($_COOKIE[$persistentLoginCookieName])) {
			//remove the cookie
			error_log('User is logging out. Removing persistent login cookie: ' . $_COOKIE[$persistentLoginCookieName]);
			setcookie($persistentLoginCookieName, '', time() - 86400, '/');
		
			// find and remove the hash code
			error_log('Trying to also delete the related hash code from user account');
			if (!empty($_SESSION['user'])) {
				// the user might has already login from another device so we need a fresh list of persistent hash codes
				$request = $this->secureAstroboaClient->getObjectByIdOrName($_SESSION['user']['cmsIdentifier']);
				if ($request->ok()) {
					$person = $request->getResponseBodyAsArray();
					if (!empty($person['persistentLoginHashCode'])) {
						$persistentLoginHashCodeList = $person['persistentLoginHashCode'];
						$cookieParts = explode('-', $_COOKIE[$persistentLoginCookieName]);
						$hashCodeInCookie = $cookieParts[1];
			
						$hashCodeIndex = null;
						foreach ($persistentLoginHashCodeList as $index => $storedHashCode) {
							
							if ($hashCodeInCookie == $storedHashCode) {
								$hashCodeIndex = $index;
								break;
							}
						}
						
						if ($hashCodeIndex !== null) {
							unset($persistentLoginHashCodeList[$hashCodeIndex]);
							// we are ready now to save back the list with the persistent login hash codes
							// we will create a minimal person object to do a more efficient partial update rather than saving back the whole person
							$personToSave = array(
													"contentObjectTypeName" => $person['contentObjectTypeName'],
													"cmsIdentifier" => $person['cmsIdentifier'],
							);
							
							// if the list is empty we should send a null in order to remove it. Sending an empty list as a property value causes an error from astroboa api
							// TODO: we may check for empty lists in posted json objects and substitute them with null in order to protect the developer if she forgets to check
							// for empty lists 
							if (!empty($persistentLoginHashCodeList)) {
								$personToSave['persistentLoginHashCode'] = $persistentLoginHashCodeList;
							}
							else {
								$personToSave['persistentLoginHashCode'] = null;
							}
			
							$request = $this->secureAstroboaClient->updateObject($personToSave);
							if ($request->ok()) {
								error_log('The persistent login hash code has been succesfully removed from user\' account. The use is:'
								. $person['profile']['title']
								. ' The related removed cookie was: ' . $_COOKIE[$persistentLoginCookieName]);
							}
							else {
								$responseInfo = $request->getResponseInfo();
								error_log('An error occured while trying to save back to user profile the persistent login hash codes. The error response from Resource API is:'
								. $responseInfo['http_code']
								. '. The user is: '. $person['profile']['title'] . '. You should check the account and try to manually remove the hash code. The related persistent login cookie is: '
								. $_COOKIE[$persistentLoginCookieName]);	
							}
						}
						else {
							error_log('On user logout we tried to remove the persistent login hash code from user\'s account but we could not find the hash code in user\'s account data. The user is: '
							. $_SESSION['user']['profile']['title'] . '. You should check the account manually. The related persistent login cookie is: '
							. $_COOKIE[$persistentLoginCookieName]);
						}
					}
					else {
						error_log('On user logout we tried to remove the persistent login hash code from user\'s account but the property that holds the hash codes in user\'s account data is empty. The user is: '
						. $_SESSION['user']['profile']['title'] . '. The persistent login cookie that was removed from user\'s browser is: '
						. $_COOKIE[$persistentLoginCookieName]);
					}
				}
				else {
					$responseInfo = $request->getResponseInfo();
					error_log('On user logout we tried to remove the persistent login hash code from user\'s account but an error has occurred while retreivint the user from repository. The error code is: ' . $responseInfo['http_code'] 
					. ' The user that failed to be retreived is: '
					. $_SESSION['user']['profile']['title'] . '. The hash code will not be removed. You should remove it manually. The related persistent login cookie is: '
					. $_COOKIE[$persistentLoginCookieName]);
				}
			}
			else {
				error_log('On user logout we tried to remove the persistent login hash code from user\'s account but no user is stored in the session. Please check the UI code to prevent not logged in users from using the logout action');
			}
		
		}
	}
	
	
	public function message() {
		$this->smarty->display('identity/authMessage.tpl');
	}
	
	
	public function showRegistrationForm() {
		$this->smarty->display('identity/signUp.tpl');
	}
	
	
	public function register() {
		
		error_log('Starting Registration');
		try {
			$person = $this->createPersonFromRegistrationData();
			
			if ($person == null) {
				error_log('Incomplete user data in registration form. Could not complete user registration');
				echo $this->createResponseMessage('error', $this->authMessage);
				return;
			}			
			error_log('Succefully retrieved user data from registration form. The user is: ' . $person['profile']['title']);
			
			// In case the user e-mail has not been properly checked in the registration form javascript code we should check if the provided e-mail is taken
			if ( !$this->isEmailAvailable($person['emails']['email'][0]['emailAddress']) ) {
				error_log('The Javascript Validation code in User Registration Form  has failed and allowed the user to post the form with an email that is already used. The user will be prompted to use another email. YOU SHOULD FIX THE FORM VALIDATION CODE!');
				$this->authMessage = self::REGISTRATION_EMAIL_TAKEN;
				echo $this->createResponseMessage('error', $this->authMessage);
				return;
			}
			
			error_log('Confirmed that user: ' .  $person['profile']['title'] . ' is NOT REGISTERED. Email: ' . $person['emails']['email'][0]['emailAddress'] . ' is available.');
			
			$personId = null;
			$response = $this->secureAstroboaClient->addObject($person);
			if (!$response->ok()) {
				$responseInfo = $request->getResponseInfo();
				error_log('An error occured while trying to save new user profile. The error response from Resource API is:' . $responseInfo['http_code']);
				$this->authMessage = self::REGISTRATION_ACCOUNT_CREATION_ERROR . ' ' . self::ERROR_RESOLUTION_RECOMMENTATION;
				echo $this->createResponseMessage('error', $this->authMessage);
				return;
			}
			$personId = $response->getResponseBody();
			error_log('user: ' .  $person['profile']['title'] . '(' . $personId . ') has been succesfully REGISTERED. Sending the account activation message and notification messages to site admins');
			
			if (!Util::sendAccountActivationMessage($person, $this->smarty, $this->astroboaConfiguration)) {
				error_log('An error occured while trying to send the account activation email for user: ' . $person['profile']['title']);
				
				// delete the account so when the user attempts again the email will be available
				error_log('We will try to remove the user so that she can try to register again with the same email account');
				if ($this->removeAccount($personId)) {
					error_log('Succefully removed user. She will prompted to try and register again');
				}
				else {
					error_log('Failed to remove user with id: ' . $personId . ' You should remove her manually. Until you remove the user she will not be able to register with the same email account.');
				}
				$this->authMessage = self::REGISTRATION_ACCOUNT_CREATION_ERROR . ' ' . self::ERROR_RESOLUTION_RECOMMENTATION;
				echo $this->createResponseMessage('error', $this->authMessage);
				return;
			}
			
			// send notification to admins for a new registration
			$toAddressList =
				array(
					array('address'=>$this->astroboaConfiguration['mail']['SITE_ADMIN_EMAIL'], 'firstName'=>'Site', 'lastName'=>'Admin')
			);
			
			if (!Util::sendNotificationForNewRegistration($person, $toAddressList, $this->astroboaConfiguration)) {
				$this->authMessage = 'An error occured during the creation of your account. We apologize for the inconvenience. ' . self::ERROR_RESOLUTION_RECOMMENTATION;
				echo $this->createResponseMessage('error', $this->authMessage);
				return;
			}
			
			$this->authMessage = 
			'<p>You have been successfully registered!</p>' . 
			'<p>An account confirmation email has been sent to the provided email account.</p>' . 
			'<p>Please find the email with Subject: ' . 
			$this->astroboaConfiguration['mail']['SUBJECT_PREFIX_ACCOUNT_ACTIVATION_MESSAGES'] .  $person['profile']['title']  . 
			' and click the provided link in order to complete your registration. ' . 
			'Make sure that ' . $this->astroboaConfiguration['mail']['FROM_ADDRESS_ACCOUNT_ACTIVATION_MESSAGES'] . ' is not blocked by your email spam filter.</p>';
			
			error_log('User: ' .  $person['profile']['title'] . ' REGISTRATION has been successfuly completed.');
			
			echo $this->createResponseMessage('ok', $this->authMessage);
		
		}
		catch (Exception $e) {
			error_log('An exception occured during the registration of a user. The error is: ' . $e->getMessage());
			$this->authMessage = 'An error occured during the creation of your account. We apologize for the inconvenience. ' . self::ERROR_RESOLUTION_RECOMMENTATION;
			echo $this->createResponseMessage('error', $this->authMessage);
		}
	}
	
	
	public function sendPasswordResetEmail() {
		if (!empty($_POST['reset-password-email'])) {
			$email = trim($_POST['reset-password-email']);
			
			// retreive the user that requested the password reset, We require that her account has been activated in order to allow a password reset.
			$cmsQuery = 'contentTypeName="' . $this->astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES'] . '" AND personAuthentication.username="' . $email . '" AND personAuthentication.authenticationDataEnabled="true"';
			$request = $this->secureAstroboaClient->getObjectCollection($cmsQuery, null, 0, 1, null);
			
			$user = null;
			if ($request->ok()) {
				$users = $request->getResponseBodyAsArray();
				if($users['totalResourceCount'] == 1) {
					$user = $users['resourceCollection']['resource'][0];
					error_log('Password Reset Requested. An active account found for the given user mail. Email='.$email . ' The user is: ' . $user['profile']['title']);
				}
				else {
					if ($users['totalResourceCount'] == 0) {
						error_log('No active accounts found for the given user mail. Email='.$email . ' User will informed that should first activate her account before being able to reset password.');
						$this->authMessage = 'You have not activated your account. <br/> Please activate your account first.';
					}
					else if ($users['totalResourceCount'] > 1){
						error_log('More than one accounts found for the given user mail. email='.$email . ' Password reset will not proceed and user will be advised to try again or contact support');
						$this->authMessage = 'It is not currently possible to reset your account password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION;
					}
					
					echo $this->createResponseMessage('error', $this->authMessage);
					return;
				}
			}
			else {
				$responseInfo = $request->getResponseInfo();
				error_log('The query that finds the user that wants a password reset returned with error code: '. $responseInfo['http_code'] . '. User email='.$email . ' User will be advised to try again');
				$this->authMessage = 'It is not currently possible to reset your account password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION;
				echo $this->createResponseMessage('error', $this->authMessage);
				return;
			}
			
			// add to profile.description field a hash code to use it for validation of the password reset url
			$this->addHashCode($user);
			// update user object in repository in order to save the hash code
			$request = $this->secureAstroboaClient->updateObject($user);
			if (!$request->ok()) {
				$responseInfo = $request->getResponseInfo();
				error_log('An error occured while trying to save the validation hash code for password reset into user object. The error response from Resource API is:' . $responseInfo['http_code']);
				$this->authMessage = 'It is not currently possible to reset your account password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION;
				echo $this->createResponseMessage('error', $this->authMessage);
				return;
			}
			
			error_log('Succesufully updated account of user: ' . $user['profile']['title'] . ' with hash code for password reset');
				
			
			if (Util::sendPasswordResetMessage($user, $this->smarty, $this->astroboaConfiguration)) {
				$this->authMessage = 'An email has been sent with instructions on how to reset your password<br/>Please make sure that your spam filter does not block ' . $this->astroboaConfiguration['mail']['FROM_ADDRESS_PASSWORD_RESET_MESSAGES'];
				echo $this->createResponseMessage('ok', $this->authMessage);
			}
			else {
				// we shall remove the hash code from user account
				$user['profile']['description'] = '';
				$request = $this->secureAstroboaClient->updateObject($user);
				if (!$request->ok()) {
					$responseInfo = $request->getResponseInfo();
					error_log('An error occured while trying to remove the validation hash code for password reset from user account. The error response from Resource API is:' . $responseInfo['http_code'] . '. You should remove it manually');
				}
				$this->authMessage = 'An error has occured while emailing the reset password message. We apologize for the inconvenience. ' . self::ERROR_RESOLUTION_RECOMMENTATION;
				echo $this->createResponseMessage('error', $this->authMessage);
			}
		}
		else {
			error_log('Javascript Validation code in the Password Reset Form  has failed and allowed the user to post the form with an empty email. The user will be prompted to try again. YOU SHOULD FIX THE FORM VALIDATION CODE!');
			$this->authMessage = 'Please type your registration email address';
			echo $this->createResponseMessage('error', $this->authMessage);
		}
	}
	
	public function showPasswordResetForm() {
		// validate password reset url
		$email = '';
		$code = '';
		
		if (!empty($_GET['user'])) {
			$email = trim($_GET['user']);
		}
		else {
			error_log("cannot retreive the user email from Password Reset Link");
			$this->smarty->assign('authMessage', 'The Password Reset Link is invalid');
			$this->smarty->display('identity/authMessage.tpl');
		}
		
		if (!empty($_GET['code'])) {
			$code = trim($_GET['code']);
		}
		else {
			error_log("cannot retreive the hash code from Password Reset Link");
			$this->smarty->assign('authMessage', 'The Password Reset Link is invalid');
			$this->smarty->display('identity/authMessage.tpl');
		}
		
		$cmsQuery = 'contentTypeName="' . $this->astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES'] . '" AND personAuthentication.username="' . $email . '" AND profile.description="' . $code . '"';
		$request = $this->secureAstroboaClient->getObjectCollection($cmsQuery, null, 0, 1, null);
			
		if ($request->ok()) {
			$users = $request->getResponseBodyAsArray();
			if ($users['totalResourceCount'] == 1) {
				error_log('Found user with username (email): ' . $email . ' and password reset code: ' . $code);
			
				$user = $users['resourceCollection']['resource'][0];
			
				$this->smarty->assign('user', $user);
			
				$this->smarty->display('identity/passwordReset.tpl');
			}
			else {
				if ($users['totalResourceCount'] == 0) {
					error_log('No accounts found for the given user mail and password reset code. email='.$email . ' code='.$code);
					$this->smarty->assign('authMessage', 'The password reset link is invalid.');
				}
				else if ($users['totalResourceCount'] > 1){
					error_log('More than one accounts found for the given user mail and activation code. email='.$email . ' code='.$code . ' Password reset will not proceed and user will be advised to try again or contact support');
					$this->smarty->assign('authMessage', 'It is not currently possible to reset your password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION);
				}
				
				$this->smarty->display('identity/authMessage.tpl');
			}
		}
		else {
			$responseInfo = $request->getResponseInfo();
			error_log('The query that searches users for password reset returned with error code: '. $responseInfo['http_code'] . '. The password reset url has the following user mail and activation code. email='.$email . ' code='.$code . ' User will be advised to try again');
			$this->smarty->assign('authMessage', 'It is not currently possible to reset your password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION);
			$this->smarty->display('identity/authMessage.tpl');
		}
		
	}
	
	public function resetPassword() {
		$email = '';
		$code = '';
		
		if (!empty($_POST['email'])) {
			$email = trim($_POST['email']);
		}
		else {
			error_log("cannot retreive the user email from Password Reset Form");
			$this->authMessage = 'It is not currently possible to reset your password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION;
			echo $this->createResponseMessage('error', $this->authMessage);
			return;
		}
		
		if (!empty($_POST['code'])) {
			$code = trim($_POST['code']);
		}
		else {
			error_log("cannot retreive the hash code from Password Reset Form");
			$this->authMessage = 'It is not currently possible to reset your password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION;
			echo $this->createResponseMessage('error', $this->authMessage);
			return;
		}
		
		// we should find the user in repository
		$user = null;
		$cmsQuery = 'contentTypeName="' . $this->astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES'] . '" AND personAuthentication.username="' . $email . '" AND profile.description="' . $code . '"';
		$request = $this->secureAstroboaClient->getObjectCollection($cmsQuery, null, 0, 1, null);
			
		if ($request->ok()) {
			$users = $request->getResponseBodyAsArray();
			
			if($users['totalResourceCount'] == 1) {
				error_log('Found user with username (email): ' . $email . ' and password reset code: ' . $code);
				$user = $users['resourceCollection']['resource'][0];
			}
			else {
				if ($users['totalResourceCount'] == 0) {
					error_log('No accounts found for the given user mail and password reset code. email='.$email . ' code='.$code);
					$this->authMessage = 'Validation Code for Reseting your password has expired.<br/> Please use the login form to make a new password reset request';
				}
				else if ($users['totalResourceCount'] > 1){
					error_log('More than one accounts found for the given user mail and activation code. email='.$email . ' code='.$code . ' Password reset will not proceed and user will be advised to try again or contact support');
					$this->authMessage = 'It is not currently possible to reset your password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION;
				}
				
				echo $this->createResponseMessage('error', $this->authMessage);
				return;
			}
		}
		else {
			$responseInfo = $request->getResponseInfo();
			error_log('The query that searches users for password reset returned with error code: ' . $responseInfo['http_code'] . '. The password reset url has the following user mail and activation code. email='.$email . ' code='.$code . ' User will be advised to try again');
			$this->authMessage = 'It is not currently possible to reset your password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION;
			echo $this->createResponseMessage('error', $this->authMessage);
			return;
		}
		
		if (!empty($_POST['password']) && !empty($_POST['password-confirm'])) {
			// lets check if passwords match just in case the form has failed to properly check
			if ($_POST['password'] != $_POST['password-confirm']) {
				error_log('Password differs from Confirmation Password. The password reset form failed to do the check. Please correct the form code. Password Reset will fail.');
				$this->authMessage = 'The password and confirm password differ. Please type again the same password in both fields';
				echo $this->createResponseMessage('error', $this->authMessage);
				return;
			}
			
			if (CRYPT_SHA256 == 1) {
				$user['personAuthentication']['password'] = crypt(trim($_POST['password']), $this->astroboaConfiguration['users']['PASSWORD_ENCRYPTION_SALT']);
			}
			else {
				error_log(self::SHA_256_ENCRYPTION_ALGORITHM_NOT_INSTALLED);
				$this->authMessage = 'It is not currently possible to reset your password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION;
				echo $this->createResponseMessage('error', $this->authMessage);
				return;
			}
		}
		else {
			error_log('It was not possible to retreive the new password or password confirmation from password reset form. Password Reset will fail and user will be prompted to try again.');
			$this->authMessage = 'It is not currently possible to reset your password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION;
			echo $this->createResponseMessage('error', $this->authMessage);
			return;
		}
		
		// Remove hash code from user account
		$user['profile']['description'] = '';
		
		// persist updated user
		$response = $this->secureAstroboaClient->updateObject($user);
		if ($response->ok()) {
			error_log('Succesufully changed user password for user: ' . $user['profile']['title']);
			$this->authMessage = $user['name']['givenName'] . ', your password has been succesfully changed. <br/>Thank you for using our site';
			echo $this->createResponseMessage('ok', $this->authMessage);
		}
		else {
			$responseInfo = $response->getResponseInfo();
			error_log('An error occured while trying to persist user profile with new password. The error response from Resource API is:' . $responseInfo['http_code']);
			$this->authMessage =  'It is not currently possible to reset your password. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION;
			echo $this->createResponseMessage('error', $this->authMessage);	
		}
		
	}
	
	
	public function removeAccount($personId) {
		$response=$this->secureAstroboaClient->deleteObjectByIdOrName($personId);
		if ($response->ok()) {
			return true;
		}
		$responseInfo = $response->getResponseInfo();
		error_log('An error occured while trying to delete user account with id: ' . $personId . ' The error response from Resource API is:' . $responseInfo['http_code']);
		return false;
	}
	
	protected function isValidPassword($person) {
		error_log('Validating password for user: ' . $person['profile']['title']);
		if (!empty($_POST['login-password'])) {
			
			$password = trim($_POST['login-password']);
			
			if (CRYPT_SHA256 == 1) {
				$providedEncryptedPassword = crypt($password, $this->astroboaConfiguration['users']['PASSWORD_ENCRYPTION_SALT']);
				
				$storedEncryptedPassword = $person['personAuthentication']['password'];
				if ($storedEncryptedPassword == $providedEncryptedPassword) {
					error_log('The provided password is valid for user: ' . $person['profile']['title']);
					$this->authMessage = 'Welcome back ' . $person['name']['givenName'];
					return true;
				}
				else {
					error_log('INVALID PASSWORD for user: ' . $person['profile']['title']);
					$this->authMessage = self::LOGIN_NO_ACTIVE_USER_ACCOUNT;
					return false;
				}
			}
			else {
				error_log(self::SHA_256_ENCRYPTION_ALGORITHM_NOT_INSTALLED);
				$this->authMessage = self::LOGIN_GENERIC_ERROR_MESSAGE . ' ' . self::ERROR_RESOLUTION_RECOMMENTATION;
				return false;
			}
		}
		else {
			error_log("Could not retrieve user password from posted login form arguments. Please fix the login form to not allow posting if the user has not provided a password. Null will be returned");
			$this->authMessage = "Please type your password in the form and try again";
			return false;
		}
	}
	
	protected function createResponseMessage($status, $message) {
		$responseMessage = array("status"=>$status, "message"=>$message);
		return json_encode($responseMessage);
		//return '{"status": "' . $status . '","message": "' . addslashes($this->authMessage) . '"}';
	}
	
	protected function createSuccessfulLoginResponse($message) {
		$renderedLoginPanel = $this->smarty->fetch('login-panel.tpl');

		$response = array(
			"status"=>'ok', 
			"message"=>$message,
			"loginPanel"=>$renderedLoginPanel
			);
		return json_encode($response);
	}
	
	// this function is used by ajax call to check if mail exists
	public function checkIfEmailExists() {
		if (!empty($_GET['reset-password-email'])) {
			$email = trim($_GET['reset-password-email']);
			
			$emailExists = !$this->isEmailAvailable($email);
			print json_encode($emailExists);
		}
		else {
			error_log("The 'checkIfEmailExists' function was called without arguments. False will be returned");
			print json_encode(false);
		}	
	}
	
	// this function is used by ajax call to check mail availability
	public function checkIfEmailIsAvailable() {

		if (!empty($_GET['email'])) {
			$email = trim($_GET['email']);
			
			print json_encode($this->isEmailAvailable($email));
			
		}
		else {
			error_log("The 'checkIfEmailIsAvailable' function was called without arguments. False will be returned");
			print json_encode(false);
		}
	}
	
	// this function is used internally to check mail availability
	protected function isEmailAvailable($email) {
		
		if (!empty($email)) {
			
			$cmsQuery = 'contentTypeName="' . $this->astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES'] . '" AND personAuthentication.username="' . $email . '" OR emails.email.emailAddress="' . $email . '"';
			$request = $this->secureAstroboaClient->getObjectCollection($cmsQuery, 'profile.title', 0, 0, null);
			
			if ($request->ok()) {
				$usersWithSameUsernameOrEmail = $request->getResponseBodyAsArray();
				$numberOfUsers = $usersWithSameUsernameOrEmail['totalResourceCount'];
				if ($numberOfUsers > 0) {
					error_log("The number of users registered with email: " . $email . ' is ' . $numberOfUsers);
					return false;
				}
				else {
					error_log('The mail address: ' . $email . ' is available');
					return true;
				}
			}
			else { // if no results have been returned then an error has occured so we will return false
				$responseInfo = $request->getResponseInfo();
				error_log('An error response was returned from email existense query. The error code is: ' . $responseInfo['http_code'] . '. For safety, we will assume that the email is not available .');
				return false;
			}
		}
		else {
			error_log("The 'isEmailAvailable' function was called with empty arguments. False will be returned");
			return false;
		}
	}
	
	public function confirmRegistration() {
		$email = '';
		$code = '';
		
		if (!empty($_GET['user'])) {
			$email = trim($_GET['user']);
		}
		else {
			error_log("cannot retreive the user email from Account Activation Link");
			$this->smarty->assign('authMessage', 'The Account Activation Link is invalid');
			$this->smarty->display('identity/authMessage.tpl');
		}
		
		if (!empty($_GET['code'])) {
			$code = trim($_GET['code']);
		}
		else {
			error_log("cannot retreive the activation code from Account Activation Link");
			$this->smarty->assign('authMessage', 'The Account Activation Link is invalid');
			$this->smarty->display('identity/authMessage.tpl');
		}
		
		$cmsQuery = 'contentTypeName="' . $this->astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES'] . '" AND personAuthentication.username="' . $email . '" AND profile.description="' . $code . '"';
		$request = $this->secureAstroboaClient->getObjectCollection($cmsQuery, null, 0, 1, null);
			
		if ($request->ok()) {
			$users = $request->getResponseBodyAsArray();
			
			if ($users['totalResourceCount'] == 1) {
				error_log('Found user with username (email): ' . $email . ' and activation code: ' . $code);
			
				$user = $users['resourceCollection']['resource'][0];
			
				// enable account
				$user['personAuthentication']['authenticationDataEnabled'] = true;
				// remove activation code
				$user['profile']['description'] = '';
			
				$request = $this->secureAstroboaClient->updateObject($user);
				if ($request->ok()) {
					error_log('Succesufully activated and updated profile of user: ' . $user['profile']['title']);
					$this->smarty->assign('authMessage', 'Welcome ' . $user['name']['givenName'] . '<br/> Your account has been activated.');
				}
				else {
					$responseInfo = $request->getResponseInfo();
					error_log('An error occured while trying to activate and update user profile. The error response from Resource API is:' . $responseInfo['http_code']);
					$this->smarty->assign('authMessage', 'It is not currently possible to activate your account. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION);
				}
			
				$this->smarty->display('identity/authMessage.tpl');
			}
			else {
				if ($users['totalResourceCount'] == 0) {
					error_log('No accounts found for the given user mail and activation code. email='.$email . ' code='.$code);
					$this->smarty->assign('authMessage', 'The activation link is invalid or has expired. <br/>Activation links are valid for one week. <br/> If your activation link has expired you need to register again.');
				}
				else if ($users['totalResourceCount'] > 1){
					error_log('More than one accounts found for the given user mail and activation code. email='.$email . ' code='.$code . ' Activation will not proceed and user will be advised to try again or contact support');
					$this->smarty->assign('authMessage', 'It is not currently possible to activate your account. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION);
				}

				$this->smarty->display('identity/authMessage.tpl');
			}
		}
		else {
			$responseInfo = $request->getResponseInfo();
			error_log('The query that searches users to be activated returned with error code: '  . $responseInfo['http_code'] . '. The activation url has the following user mail and activation code. email='.$email . ' code='.$code . ' User will be advised to try again');
			$this->smarty->assign('authMessage', 'It is not currently possible to activate your account. <br/>' . self::ERROR_RESOLUTION_RECOMMENTATION);
			
		}
			
	}
	
	protected function createPersonFromRegistrationData() {
		$errorMessageForTheUser = self::REGISTRATION_GENERIC_ERROR_MESSAGE . ' ' .self::ERROR_RESOLUTION_RECOMMENTATION;
		$person = array(
			"contentObjectTypeName" => $this->astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES'] 
		);
		
		if (!empty($_POST['email'])) {
			$email = trim($_POST['email']);
			$person['emails']['email'][0] = 
				array(
						'emailAddress' => $email,
						'type' => $this->getTopic('registration-email')							
			);
			// mail is used as the user name
			$person['personAuthentication']['username'] = $email;
		}
		else {
			error_log(self::REGISTRATION_NO_EMAIL);
			$this->authMessage = $errorMessageForTheUser;
			return null;
		}
		
		if (!empty($_POST['password']) && !empty($_POST['password-confirm'])) {
			// lets check if passwords match just in case the form has failed to properly check
			if ($_POST['password'] != $_POST['password-confirm']) {
				error_log(self::REGISTRATION_PASSWORDS_DIFFER);
				$this->authMessage = $errorMessageForTheUser;
				return null;
			}
			
			if (CRYPT_SHA256 == 1) {
				$person['personAuthentication']['password'] = crypt(trim($_POST['password']), $this->astroboaConfiguration['users']['PASSWORD_ENCRYPTION_SALT']);
			}
			else {
				error_log(self::SHA_256_ENCRYPTION_ALGORITHM_NOT_INSTALLED);
				$this->authMessage = $errorMessageForTheUser;
				return null;
			}
		}
		else {
			error_log(self::REGISTRATION_NO_PASSWORD);
			$this->authMessage = $errorMessageForTheUser;
			return null;
		}
		
		if (!empty($_POST['first-name'])) {
			$person['name']['givenName'] = trim($_POST['first-name']);
		}
		else {
			error_log(self::REGISTRATION_NO_FIRST_NAME);
			$this->authMessage = $errorMessageForTheUser;
			return null;
		}
		
		if (!empty($_POST['last-name'])) {
			$person['name']['familyName'] = trim($_POST['last-name']);
		}
		else {
			error_log(self::REGISTRATION_NO_LAST_NAME);
			$this->authMessage = $errorMessageForTheUser;
			return null;
		}
		
		// We have the first and last name lets create the object title
		$person['profile']['title'] = $person['name']['givenName'] . ' ' . $person['name']['familyName'];
		
		if ( !empty($_POST['birthday-day']) && !empty($_POST['birthday-month']) && !empty($_POST['birthday-year']) ) {
			$birthday_day = $_POST['birthday-day'];
			$birthday_month = $_POST['birthday-month'];
			$birthday_year = $_POST['birthday-year'];
			$time = mktime(0,0,0, $birthday_month, $birthday_day, $birthday_year);
			// Date shoud follow the ISO-8601 standard, i.e. 2011-01-27T10:15:42.431+02:00
			$iso8601Birthday = date('Y-m-d\TH:i:s.uP', $time);
			$person['birthday'] = $iso8601Birthday;
		}
		else {
			error_log(self::REGISTRATION_NO_BIRTHDAY);
			$this->authMessage = $errorMessageForTheUser;
			return null;
		}
		
		//if (!empty($user['location']['name'])) {
		//	$location = $user['location']['name'];
		//}
		
		if (!empty($_POST['gender'])) {
			$genderAsTopic = $this->getTopic($_POST['gender']);
			if (!empty($genderAsTopic)) {
				$person['gender'] = $genderAsTopic;
			}
		}
		else {
			error_log(self::REGISTRATION_NO_GENDER);
			$this->authMessage = $errorMessageForTheUser;
			return null;
		}
		
		// we should also store a hash code that will be used for account confirmation and activation
		// i.e. we will mail to the provided mail account a confirmation url with the hash code
		$this->addHashCode($person);
		
		return $person;
	}
	
	protected function addHashCode(&$person) {
		$accountActivationCode = md5($person['emails']['email'][0]['emailAddress'] . $person['gender']['name'] . $person['name']['familyName'] . time());
		$person['profile']['description'] = $accountActivationCode;
	}
	
	
	protected function findRegisteredUser() {
		if (!empty($_POST['login-email'])) {
			$email = trim($_POST['login-email']);
			
			$cmsQuery = 'contentTypeName="' . $this->astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES'] . '" AND personAuthentication.username="' . $email . '" AND personAuthentication.authenticationDataEnabled="true"';
			$request = $this->secureAstroboaClient->getObjectCollection($cmsQuery, null, 0, 1, null);
			
			if ($request->ok()) {
				$users = $request->getResponseBodyAsArray();
				$numberOfUsers = $users['totalResourceCount'];
				if ($numberOfUsers > 1) {
					error_log("The number of users registered with email: " . $email . ' is ' . $numberOfUsers . ' A Null person will be returned. Please fix the registration code to prevent different users to register with the same email');
					$this->authMessage = self::LOGIN_GENERIC_ERROR_MESSAGE . ' ' . self::ERROR_RESOLUTION_RECOMMENTATION;
					return null;
				}
				else if ($numberOfUsers == 1) {
					$person = $users['resourceCollection']['resource'][0];
					error_log('User: ' . $person['profile']['title'] .' registered with email address: ' . $email . ' was found and his account is active');
					return $person;
				}
				else if ($numberOfUsers == 0) {
					error_log('No user found that has been registered with email address: ' . $email . ' or an account exists but has not been activated');
					$this->authMessage = self::LOGIN_NO_ACTIVE_USER_ACCOUNT;
					return null;
				}
			}
			else { // if no results have been returned then an error has occured so we will return false
				$responseInfo = $request->getResponseInfo();
				error_log('An error response returned from \'find registered user\' query. The error code is: ' . $responseInfo['http_code'] . '. A Null person will be returned');
				$this->authMessage = self::LOGIN_GENERIC_ERROR_MESSAGE . ' ' . self::ERROR_RESOLUTION_RECOMMENTATION;
				return null;
			}
		}
		else {
			error_log("Could not retrieve user email from posted login form arguments. Please fix the login form to not allow posting if the user has not provided an email. Null will be returned");
			$this->authMessage = "Please type your email address in the form and try again";
			return null;
		}
	}
	
	protected function createPersistentLoginCookie($person) {
		$hashcode = md5($person['cmsIdentifier'] . $person['emails']['email'][0]['emailAddress'] . $person['gender']['name'] . $person['name']['familyName'] . time());
		$persistentLoginCookie = $person['personAuthentication']['username'] . '-' . $hashcode;
		
		// save hashcode in person's account
		// we will create a minimal person object with just the cookie hash code in order to perform the save instead of saving the whole person object
		$persistentLoginHashCodeList = array();
		if (!empty($person['persistentLoginHashCode'])) {
			$persistentLoginHashCodeList = $person['persistentLoginHashCode'];
		}
		
		$persistentLoginHashCodeList[] = $hashcode;
		
		$personToSave = array(
			"contentObjectTypeName" => $person['contentObjectTypeName'],
			"cmsIdentifier" => $person['cmsIdentifier'],
			"persistentLoginHashCode" => $persistentLoginHashCodeList
		);
		
		 $request = $this->secureAstroboaClient->updateObject($personToSave);
		if ($request->ok()) {
			$responseInfo = $request->getResponseInfo();
			error_log('An error occured while trying to save persistent login hash code to user profile. The error response from Resource API is:' . $responseInfo['http_code'] . ' The login process will continue but no cookie will be created.');
			
			return false;
		}
		
		// lets write the cookie to users browser. It will expire in 90 daysby default (60*60*24*90 seconds)
		$persistentLoginCookieExpirationSeconds = 7776000;
		$persistentLoginCookieName = 'site-plc';
		
		if (!empty($this->astroboaConfiguration['session']['PERSISTENT_LOGIN_COOKIE_NAME'])) {
			$persistentLoginCookieName = $this->astroboaConfiguration['session']['PERSISTENT_LOGIN_COOKIE_NAME'];
		}
		
		if (!empty($this->astroboaConfiguration['session']['PERSISTENT_LOGIN_COOKIE_EXPIRATION_IN_SECONDS'])) {
			$persistentLoginCookieExpirationSeconds = $this->astroboaConfiguration['session']['PERSISTENT_LOGIN_COOKIE_EXPIRATION_IN_SECONDS'];
		}
		setcookie($persistentLoginCookieName, $persistentLoginCookie, time() + $persistentLoginCookieExpirationSeconds);
		return true;
	}
}

?>