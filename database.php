<?php
ob_start();
$rootpath = "./";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");

include $rootpath . 'inc_header.php';

include $rootpath . 'inc_footer.php';
