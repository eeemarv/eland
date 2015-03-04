<?php

include($rootpath.'/includes/Smarty/libs/Smarty.class.php');
$smarty = new Smarty;
$smarty->template_dir = $rootpath;
$smarty->compile_dir = $rootpath."templates_c";
$smarty->cache_dir = $rootpath."cache";
$smarty->config_dir = $rootpath."configs";;
$smarty->assign('rootpath', $rootpath);
$smarty->assign('localpath', $localpath);

?>
