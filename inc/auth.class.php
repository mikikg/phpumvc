<?php

//-------------------------------------------------------------
// Custom Micro MVC AD site
//
// Author: A. Markovic <mikikg@gmail.com>
//-------------------------------------------------------------

if (!defined('MyAdSite')) die ('Direct script access forbidden!');

class MyAuth {

	//Constructor
	public function __construct () {
		session_start();

		//echo '<pre>'.print_r($_SESSION,1).'</pre>';

		//logout handler
		if (isset($_GET['logout']) && $this->is_user_authenticated()) {
			$_SESSION['authenticated'] = false;
			$_SESSION['user_data'] = array();
			session_destroy();
		}
	}

	public static function is_user_authenticated() {
		if(empty($_SESSION['authenticated']) || $_SESSION['authenticated'] != 'true') {
		    return false;
		} else {
			return true;
		}
	}

}
