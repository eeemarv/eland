<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_mailfunctions.php");

$id = $_GET["id"];

if ($db->update('news', array('approved' => 't', 'published' => 't'), array('id' => $id)))
{
	$alert->success("Nieuwsbericht goedgekeurd");
}
else
{
	$alert->error('Goedkeuren nieuwsbericht mislukt.');
}
header('Location: view.php?id=' . $id);
exit;
