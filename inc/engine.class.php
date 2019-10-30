<?php

//-------------------------------------------------------------
// Custom Micro MVC AD site
//
// Author: A. Markovic <mikikg@gmail.com>
//-------------------------------------------------------------

if (!defined('MyAdSite')) die ('Direct script access forbidden!');

class MyEngine {

	private $auth = array(); //auth obj goes here
	private $db = array(); //db obj goes here
	private $html_data = ''; //full page HTML goes here
	private $engine_base_path = '';
	private $last_csrf = '';
	private $my_pages = array(); //page definition and rules
	private $dev_mode = true; //in production mode we don't show ANY errors, only logs them ...

	//-------------------
	//Constructor
	//-------------------
	public function __construct ($my_pages, $base_file) {

		//Set base dir
		$this->engine_base_path = dirname($base_file);

		//Set pages definition
		$this->my_pages = $my_pages;

		//Check for requred stuff
		$this->engine_pre_check();

		//Load and init Auth class
		require_once ($this->engine_base_path.'/inc/auth.class.php');
		$this->auth = new MyAuth;

		//Load and init DB class
		require_once ($this->engine_base_path.'/inc/db.class.php');
		$this->db = new MyDb ($this->engine_base_path);

		//Call csrf handler
		$this->csrf_handler();

		//Load Master template
		$this->html_data = file_get_contents($this->engine_base_path.'/tmpl/template.html');

		//Get page value
		if (isset($_GET['page']) && !empty($_GET['page'])) {
			$page = substr(trim($_GET['page']), 0, 10); //max 10 chars
		} else {
			//Default page
			foreach ($this->my_pages as $page_name => $page_props) {
				if ($page_props['default']==true) {
					$page = $page_name;
					break;
				}
			}
		}

		//Does requested page requere authorisation?
		if (isset ($this->my_pages[$page]['auth']) && $this->my_pages[$page]['auth'] == true) {
			//Is user already logged in?
			if (!MyAuth::is_user_authenticated()) {
				//not logged, forward to login page
				$page = 'login';
			}
		}

		//Check existance of page and load content
		if (array_key_exists($page, $this->my_pages)) {
			$page_content = file_get_contents($this->engine_base_path.'/views/'.$page.'.html');
		} else {
			header('HTTP/1.0 404 Not Found');
			$page_content = file_get_contents($this->engine_base_path.'/views/404.html');
		}

		//Alert mesages from session
		if (isset($_SESSION['my_alert']) && !empty($_SESSION['my_alert'])) {
			$this->html_data = str_replace('##my_alert##', $this->get_alert_html($_SESSION['my_alert']), $this->html_data);
			$_SESSION['my_alert'] = '';
		} else {
			$this->html_data = str_replace('##my_alert##', '', $this->html_data);
		}

		//Different navigation menu for reg/unreg users - UGLY
		if (MyAuth::is_user_authenticated()) {
			$this->html_data = str_replace(array('##style_unreg_nav##', '##style_reg_nav##'), array('display:none', 'display:inherit'), $this->html_data);
		} else {
			$this->html_data = str_replace(array('##style_unreg_nav##', '##style_reg_nav##'), array('display:inherit', 'display:none'), $this->html_data);
		}

		//Insert page into master template
		$this->set_page_content($page_content);

		//Call page controller
		if (isset($this->my_pages[$page]['func'])) {
			$ctl = $this->my_pages[$page]['func'];
			if (method_exists($this, $ctl)) $this->$ctl();
			else $this->my_die('FATAL ERROR: No defined method: '.$ctl);
		}
		
		//Set CSRF and userid
		$this->html_data = str_replace(array('##csrf##','##userid##'), array($this->last_csrf, isset($_SESSION['user_data']['iduser'])?$_SESSION['user_data']['iduser']:0), $this->html_data);
	}

	//CSRF protection
	private function csrf_handler() {
		//Depending on request method, set or check CSRF
		if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			//get, generate CSRF and store into SESSION
			$this->last_csrf = $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			//post, check validity
			if (isset($_POST['csrf']) && $_POST['csrf'] == $_SESSION['csrf']) {
				//ok, generate new csrf
				$this->last_csrf = $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
			} else {
				//error, someone playing around!
				$this->my_die ('Invalid CSRF request!');
			}
		}
	}

	//Pre-check
	private function engine_pre_check() {
		//does we have writable file db/AdDb_PDO.sqlite and data/ folders
		$to_check = array (
			$this->engine_base_path.'/db/AdDb_PDO.sqlite',
			$this->engine_base_path.'/data',
		);

		foreach ($to_check as $key => $val) {
			if (!file_exists($val)) {
				$this->my_die ('FATAL ERROR: Required file/folder not found at '.$val);
			}
			if (!is_writable($val)) {
				$this->my_die ('FATAL ERROR: File/folder not writable at '.$val);
			}
		}
	}

	//Exit wrapper
	private function my_die($msg) {
		//Log errors ...
		error_log($msg, 0);
		if ($this->dev_mode) {
			$_SESSION['my_alert'] = $msg;
			header("Location: index.php");
			die;
		} else {
			header("HTTP/1.0 500 Internal Server Error");
			die;
		}
	}

	//------------------- ######## Helpers ######## -----------------
	public function set_page_content($data) {
		$this->html_data = str_replace('##content##', $data, $this->html_data);
	}

	public static function get_nav_uri ($string) {
		return  $string;
	}

	public function get_site_html () {
		return $this->html_data;
	}

	public function get_csrf () {
		return $this->last_csrf;
	}

	public function get_alert_html ($msg = 'some text', $type='success') {
		return '<div class="alert alert-'.$type.'">'.$msg.'</div>';
	}

	public function img_helper ($id) {
		$img_name = 'data/img_'.sha1($id+0xffffffff).'.jpg';
		file_exists($this->engine_base_path.'/'.$img_name) ? $img=$img_name : $img='';
		return $img;
	}

	public function valid_upload_image($file) {
		$whitelist_type = array('image/jpeg', 'image/png','image/gif');
		$error = 0;
		if(function_exists('finfo_open')){    //(PHP >= 5.3.0, PECL fileinfo >= 0.1.0)
		   $fileinfo = finfo_open(FILEINFO_MIME_TYPE);

		    if (!in_array(finfo_file($fileinfo, $file['tmp_name']), $whitelist_type)) {
		      $error++;
		    }
		}else if(function_exists('mime_content_type')){  //supported (PHP 4 >= 4.3.0, PHP 5)
		    if (!in_array(mime_content_type($file['tmp_name']), $whitelist_type)) {
		      $error++;
		    }
		}else{
		   if (!@getimagesize($file['tmp_name'])) {  //@ - for hide warning when image not valid
		      $error++;
		   }
		}
		return $error?false:true;
	}

	//------------ ######## page controllers ######### -------------
	//------- Page list - home -------------
	private function controller_ad_list() {

		//Load card template
		$card_html = file_get_contents($this->engine_base_path.'/views/card.html');

		//Filter by user
		if (isset($_GET['userid'])) {
			$userid = (int)substr($_GET['userid'],0,16);
		} else {
			$userid = 0;
		}

		//Get last 10 cards
		$data = $this->db->get_last_cards(10, $userid);

		//Build list
		$out = '';
		if (!empty($data)) foreach ($data as $id => $val) {
			$out .= str_replace(
				array('##title##','##description##','##link##','##img##'),
				array($val['title'], preg_replace('/\s+/', ' ', trim(substr($val['description'],0,300))), 'index.php?page=ad-view&id='.(int)$id, $this->img_helper($val['idad']))
				, $card_html);
		}

		//Put on template
		$this->html_data = str_replace('##ad_list_html##', $out, $this->html_data);
	}

	//------- Single ad page -------------
	private function controller_ad_view() {
		isset($_GET['id']) ? $id = (int)substr($_GET['id'],0,16) : $id = 0;

		//Fetch data
		$data = $this->db->get_ad_details($id);

		if ($data) {
			$this->html_data = str_replace(
				array('##title##','##user##','##tel##', '##email##', '##time##','##description##','##img##','##byuserid##'),
				array($data['title'],$data['name'].' '.$data['surname'], $data['tel'], $data['email'], date("l jS \of F Y h:i:s A",$data['timestamp']), nl2br($data['description']), $this->img_helper($data['idad']), $data['userid']),
				$this->html_data);
		} else {
			//Invalid request
			header("Location: index.php");
		}
	}

	//------- Login page -------------
	private function controller_login() {

		//Action by method
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			//POST, filter input data
			$args = array(
			    'email'     => FILTER_VALIDATE_EMAIL,
			    'password' => FILTER_SANITIZE_SPECIAL_CHARS
			);
			$this->myinputs = filter_input_array(INPUT_POST, $args);

			$res = $this->db->get_user_auth($this->myinputs['email'], sha1($this->myinputs['password']));

			//User found?
			if (empty($res)) {
				//invalid user/pass
				//todo: more security logic, failed attempt count, time delay etc ...
				$_SESSION['my_alert'] = 'Authentication failed! Invalid email or password.';
				header("Location: index.php?page=login");
				die;
			} else {
				//Login NOW
				$_SESSION['user_data'] = $res;
				$_SESSION['my_alert'] = 'Welcome back '.$_SESSION['user_data']['name'];
				$_SESSION['authenticated'] = 'true';
				header("Location: index.php");
				die;
			}
		}
	}

	//------- Ad post page -------------
	private function controller_ad_post() {
		//Action by method

		$search = array('##title##','##description##');
		$replace = array('','');
		$form_errors = 0;
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {

			//POST, filter input data
			$this->myinputs = filter_input_array(INPUT_POST, array('title' => FILTER_SANITIZE_SPECIAL_CHARS,'description' => FILTER_SANITIZE_STRING));

			//Limit input size
			$this->myinputs['title'] = substr($this->myinputs['title'],0,100);
			$this->myinputs['description'] = substr($this->myinputs['description'],0,1024);
			$replace = array($this->myinputs['title'], $this->myinputs['description']);

			//Validate
			if (!empty($this->myinputs['title']) && !empty($this->myinputs['description'])) {
				//Form ok, insert ad into DB and get created ID
				$new_id = $this->db->insert_ad($this->myinputs, $_SESSION['user_data']['iduser']);

				if ($new_id) {
					//Handle file uploads
					if (isset($_FILES['upfile']['size']) && $_FILES['upfile']['size'] < 2048*1000*1000 && $_FILES['upfile']['size'] > 100) {
						//We got some file, move it to data/
						if ($this->valid_upload_image($_FILES['upfile'])) {
							move_uploaded_file($_FILES['upfile']['tmp_name'], sprintf('%s/data/img_%s.jpg',$this->engine_base_path, sha1($new_id+0xFFFFFFFF)));
						} else {
							$form_errors++;
						}
					}
				}

			} else {
				$form_errors++;
			}

			if ($form_errors==0) {
				$_SESSION['my_alert'] = 'You Ad posted successfully, view it <a href="index.php?page=ad-view&id='.(int)$new_id.'">here</a>';
				header("Location: index.php?page=ad-list");
			}
		}

		//Show form error
		$this->html_data = str_replace('##form_error##', $form_errors?'Please fill all required (*) fields.':'', $this->html_data);

		//Set filtered form field values
		$this->html_data = str_replace($search, $replace, $this->html_data);
	}

	//-------- Register/form page -------------
	private function controller_register() {

		$form_errors = 0;
		$form_ok = 0;

		//Our fields
		$args = array(
		    'name'    	=> FILTER_SANITIZE_SPECIAL_CHARS,
		    'surname'   => FILTER_SANITIZE_SPECIAL_CHARS,
		    'email'     => FILTER_VALIDATE_EMAIL,
		    'tel' 		=> FILTER_VALIDATE_INT,
		    'password1' => FILTER_SANITIZE_SPECIAL_CHARS,
		    'password2' => FILTER_SANITIZE_SPECIAL_CHARS,
		    'human'		=> FILTER_VALIDATE_INT
		);

		//Template vars
		$search = array(
			'##val_name##',
			'##val_surname##',
			'##val_email##',
			'##val_tel##',
		);

		//Action by method
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {

			//POST, filter input data
			$this->myinputs = filter_input_array(INPUT_POST, $args);

			$replace = array(
				$this->myinputs['name'],
				$this->myinputs['surname'],
				$this->myinputs['email'],
				$this->myinputs['tel'],
			);

	 		//At this point we dont allow values longer than 100 chars, more advanced filtering requred, word blacklist etc.
	 		foreach ($args as $var_name => $val) {
		 		if (isset($this->myinputs[$var_name])) {
			 		//ok
			 		$this->myinputs[$var_name] = substr($this->myinputs[$var_name], 0, 100);
			 		//empty field
				 	if (empty($this->myinputs[$var_name])) {
					 	$form_errors ++;
					} else {
						$form_ok++;
					}
				} else {
					$form_errors ++;
				}
	 		}

		} else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
			//GET, empty form
			$replace = array();
			$form_errors = count (array_keys($args));
		} else {
			$form_errors ++;
		}

		if ($form_ok == count (array_keys($args))) {
			//Next check, password 1 and 2 need to match
			if ($this->myinputs['password1'] != $this->myinputs['password2']) $form_errors ++;

			//Password need to be at least 8 characters long
			if (strlen($this->myinputs['password1']) < 8 || $this->myinputs['password2'] < 8) $form_errors ++;

			//Crypt password
			$this->myinputs['password1'] = sha1($this->myinputs['password1']);
			$this->myinputs['password2'] = sha1($this->myinputs['password2']);

			//Human answer? We need some randomness ...
			if ($this->myinputs['human'] != '5') $form_errors ++;
		}

		if ($form_errors == 0) {

			//Is this a new email in DB
			if ($this->db->email_exists($this->myinputs['email'])) {
				//No, email exists in DB
				$this->html_data = str_replace('##form_error##', 'Please provide different email address!', $this->html_data);
			} else {
				//All OK, exit here ------------------------->>>>
				//Of course we need to confirm account via email ...
				$_SESSION['my_alert'] = 'Your account created successfully.';
				$_SESSION['authenticated'] = 'true';

				//Insert new user into DB
				$this->db->insert_new_user($this->myinputs);

				//Load it's data to session
				$this->db->get_user_auth ($this->myinputs['email'], $this->myinputs['password1']);

				header("Location: index.php");
				die;
			}
		} else {
			//Show form and error
			$this->html_data = str_replace('##form_error##', 'Please fill all required (*) fields.', $this->html_data);
		}

		//Set filtered form field values
		$this->html_data = str_replace($search, $replace, $this->html_data);
	}

}
