<?php
ob_start();
$rootpath = './';
$role = 'guest';
require_once $rootpath . 'includes/inc_default.php';

if(isset($_POST['zend']))
{
	$post = array(
		'subject' 	=> $_POST['subject'],
		'content'	=> $_POST['content'],
	);

    $errors = array();

 	if (!$post['subject'])
	{
		 $errors[] = 'Vul een onderwerp in.';
	}
 	if (strlen($post['content']) < 5)
	{
		 $errors[] = 'De inhoud van je bericht is te kort.';
	} 


}
else
{

}

$h1 = 'Forum';
$fa = 'comments-o';

require_once $rootpath . 'includes/inc_header.php';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';



echo '<div class="form-group">';
//echo '<label for="subject" class="col-sm-2 control-label">Onderwerp</label>';
echo '<div class="col-sm-12">';
echo '<input type="text" class="form-control" id="subject" name="subject" ';
echo 'placeholder="Onderwerp" ';
echo 'value="' . $post['subject'] . '" required>';
echo '</div>';
echo '</div>';

echo '<div class="form-group">';
//echo '<label for="content" class="col-sm-2 control-label">Omschrijving</label>';
echo '<div class="col-sm-12">';
echo '<textarea name="content" class="form-control" id="content" rows="4" required>';
echo $post['content'];
echo '</textarea>';
echo '</div>';
echo '</div>';

echo '<input type="submit" name="zend" value="Verzenden" class="btn btn-default">';

echo '</form>';

echo '</div>';
echo '</div>';

if (!$s_id)
{
	echo '<small><i>Opgelet: je kan vanuit het loginscherm zelf een nieuw paswoord aanvragen met je e-mail adres!</i></small>';
}

include $rootpath . 'includes/inc_footer.php';
