<?php

//-------------------------------------------------------------
// Custom Micro MVC AD site
//
// Author: A. Markovic <mikikg@gmail.com>
//
//-------------------------------------------------------------

define("MyAdSite", true);

error_reporting(E_ALL | E_STRICT);

//Check existance of engine class file
$class_file = dirname(__FILE__)."/inc/engine.class.php";
if (is_file($class_file)) {
	//Iclude engine class file
	require_once($class_file);
} else {
	//class not found
	die ("FATAL ERROR: Failed opening required file '$class_file'\n");
}

//Pages & rules
$my_pages = array (
	'ad-list' 	=> array ('func' => 'controller_ad_list', 'default'=>true),
	'ad-post' 	=> array ('func' => 'controller_ad_post', 'auth'=>true),
	'ad-view' 	=> array ('func' => 'controller_ad_view'),
	'register' 	=> array ('func' => 'controller_register'),
	'login' 	=> array ('func' => 'controller_login')
);

//Boostrap site engine
$my_site = new MyEngine ($my_pages, __FILE__);
echo $my_site->get_site_html();

