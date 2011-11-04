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
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'securimage' . DIRECTORY_SEPARATOR . 'securimage.php');

class CaptchaController extends Controller {
	
	public function show() {
		$img = new securimage();
		
		// Change some settings
		
		//$img->image_width = 275;
		//$img->image_height = 90;
		//$img->perturbation = 0.9; // 1.0 = high distortion, higher numbers = more distortion
		//$img->image_bg_color = new Securimage_Color("#0099CC");
		//$img->text_color = new Securimage_Color("#EAEAEA");
		//$img->text_transparency_percentage = 65; // 100 = completely transparent
		//$img->num_lines = 8;
		//$img->line_color = new Securimage_Color("#0000CC");
		//$img->signature_color = new Securimage_Color(rand(0, 64), rand(64, 128), rand(128, 255));
		//$img->image_type = SI_IMAGE_PNG;
		
		
		$img->show(); // alternate use:  $img->show('/path/to/background_image.jpg');	
	}
	
	
	public function check() {
		$captchaCode = '';
		
		if (!empty($_GET['captcha-code'])) {
			$captchaCode = trim($_GET['captcha-code']);
		
			$img = new Securimage();
		
			$valid = $img->check($captchaCode);

			if($valid == true) {
				error_log('The captcha code: ' . $captchaCode . ' is valid');
				print json_encode(true);
				return;		
			}
		}
		
		error_log('The captcha code: ' . $captchaCode . ' is not valid');
		print json_encode(false);
	}
}

?>