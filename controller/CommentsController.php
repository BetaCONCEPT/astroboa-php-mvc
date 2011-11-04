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

class CommentsController extends Controller {
	
	const COMMENT_SUBMIT_GENERIC_ERROR = "An error occured during the submission of your comment. We apologize for the inconvenience.";
	const ERROR_RESOLUTION_RECOMMENTATION = 'Please try again. If the problem persists please sent us an e-mail';
	const COMMENT_SUBMIT_NOT_LOGGEDIN = "You are not logged in. Please login and try again.";
	const COMMENT_SUBMIT_SUCCESSFUL = "Your comment has been submitted";
	
	private $errorMessage = '';
	
	public function submit() {
		error_log('Starting comment submission');
		
		try {
			
			$person = $_SESSION['user'];
			
			// let's make sure that the user is logged in
			if (!empty($person)) {
				$comment = $this->createCommentFromPostedData($person);
				if (!empty($comment)) {
					error_log('Succefully retrieved comment data from the form. The commentator is: ' . $person['profile']['title']);
					
					error_log(json_encode($comment));
					
					$request = $this->secureAstroboaClient->addObject($comment);
					
					if (!$request->ok()) {
						$responseInfo = $request->getResponseInfo();
						error_log('An error occured while trying to save the comment. The error response from Resource API is:' . $responseInfo['http_code']);
						echo $this->createResponseMessage('error', self::COMMENT_SUBMIT_GENERIC_ERROR . ' ' . self::ERROR_RESOLUTION_RECOMMENTATION);
						return;
					}
			
					error_log('user: ' .  $person['profile']['title'] . ' has successfully submit a comment.');
					
					echo $this->createSuccessResponseMessage($comment, self::COMMENT_SUBMIT_SUCCESSFUL);
				}
				else {
					echo $this->createResponseMessage('error', $this->errorMessage);
				}
				
			}
			else {
				echo $this->createResponseMessage('error', self::COMMENT_SUBMIT_NOT_LOGGEDIN);
			}
		}
		catch (Exception $e) {
			error_log('An exception occured during the submission of comment. The error is: ' . $e->getMessage());
			echo $this->createResponseMessage('error', self::COMMENT_SUBMIT_GENERIC_ERROR . ' ' . self::ERROR_RESOLUTION_RECOMMENTATION);
		}
		
	}
	
	
	protected function createSuccessResponseMessage($comment, $message) {
		$this->smarty->assign('comment', $comment);
		$renderedComment = $this->smarty->fetch('component-newcomment.tpl');

		$response = array(
			"status"=>'ok', 
			"message"=>$message,
			"comment"=>$renderedComment
			);
		return json_encode($response);
	}
	
	protected function createResponseMessage($status, $message) {
		$responseMessage = array("status"=>$status, "message"=>$message);
		return json_encode($responseMessage);
	}
	
	
	protected function createCommentFromPostedData($person) {
		$errorMessageForTheUser = self::COMMENT_SUBMIT_GENERIC_ERROR . ' ' .self::ERROR_RESOLUTION_RECOMMENTATION;
		
		$objectType = '';
		$objectTypeEnLabel = '';
		$objectTitle = '';
		
		$comment = array(
			"contentObjectTypeName" => "commentObject"
		);
		
		if (!empty($_POST['objectType'])) {
			$objectType = trim($_POST['objectType']);
		}
		else {
			error_log('The submitted comment form does not contain the related object type. Please check and fix the comment input form.');
			$this->errorMessage = $errorMessageForTheUser;
			return null;
		}
		
		if (!empty($_POST['objectTypeEnLabel'])) {
			$objectTypeEnLabel = trim($_POST['objectTypeEnLabel']);
		}
		else {
			error_log('The submitted comment form does not contain the related object type english label. Please check and fix the comment input form.');
			$this->errorMessage = $errorMessageForTheUser;
			return null;
		}
		
		if (!empty($_POST['objectTitle'])) {
			$objectTitle = trim($_POST['objectTitle']);
		}
		else {
			error_log('The submitted comment form does not contain the related object title. Please check and fix the comment input form.');
			$this->errorMessage = $errorMessageForTheUser;
			return null;
		}
		
		$iso8601Date = date('Y-m-d\TH:i:s.uP');
		$commentTitle = $iso8601Date ." Comment from User " . $person['profile']['title'] . " for " . $objectTypeEnLabel . ' ' . $objectTitle;
		$comment['profile']['title'] = $commentTitle;
		
		if (!empty($person)) {
			$comment['commentator'] = array (
				"cmsIdentifier" => $person['cmsIdentifier'],
				"contentObjectTypeName" => $this->astroboaConfiguration['users']['OBJECT_TYPE_FOR_PERSON_PROFILES']
			);
		}
		else {
			error_log('The method called without a person id. Please check and fix the CommentController class.');
			$this->errorMessage = $errorMessageForTheUser;
			return null;
		}
		
		if (!empty($_POST['comment'])) {
			$commentBody = trim($_POST['comment']);
			// remove tags and javascript code
			//$commentBody = strip_tags($commentBody);
			$commentBody = Util::strip_tags_and_tag_content($commentBody);
			$comment['body'] = $commentBody;
		}
		else {
			error_log('User submitted an empty comment. This should not happen. Please check and fix the javascript validation code for the comment input form .');
			$this->errorMessage = $errorMessageForTheUser;
			return null;
		}
		
		if (!empty($_POST['objectId'])) {
			$objectId = trim($_POST['objectId']);
			$comment['commentedObject'] = array(
				"cmsIdentifier" => $objectId,
				"contentObjectTypeName" => $objectType
			);
		}
		else {
			error_log('The submitted comment form does not contain the related object id. Please check and fix the comment input form.');
			$this->errorMessage = $errorMessageForTheUser;
			return null;
		}
		
		
		$comment['status'] = $this->getTopic('approved'); // status = approved
		
		return $comment;
	}
}

?>