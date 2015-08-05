<?php
ob_start();
$rootpath = '../';
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$types = $db->GetArray('select * from type_contact tc');

$contact_count = $db->GetAssoc('select id_type_contact, count(id)
	from contact
	group by id_type_contact');

$top_buttons = '<a href="' . $rootpath . 'type_contact/add.php" class="btn btn-success"';
$top_buttons .= ' title="Contact type toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$h1 = 'Contact types';
$fa = 'circle-o-notch';

include $rootpath . 'includes/inc_header.php';

echo '<div class="table-responsive">';
echo '<table class="table table-striped table-hover table-bordered footable" data-sort="false">';
echo '<tr>';
echo '<thead>';
echo '<th>Naam</th>';
echo '<th>Afkorting</th>';
echo '<th data-hide="phone">Verwijderen</th>';
echo '<th data-hide="phone">Contacten</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';

foreach($types as $t)
{
	$count = $contact_count[$t['id']];
	$protected = (in_array($t['abbrev'], array('mail', 'gsm', 'tel', 'adr', 'web'))) ? true : false;

	echo '<tr>';

	echo '<td>';
	echo ($protected) ? '' : '<a href="' . $rootpath . 'type_contact/edit.php?id=' . $t['id'] . '">';
	echo htmlspecialchars($t['abbrev'],ENT_QUOTES);
	echo ($protected) ? '*' : '</a>';
	echo '</td>';

	echo '<td>';
	echo ($protected) ? '' : '<a href="' . $rootpath . 'type_contact/edit.php?id=' . $t['id'] . '">';
	echo htmlspecialchars($t['name'],ENT_QUOTES);
	echo ($protected) ? '*' : '</a>';
	echo '</td>';

	echo '<td>';
	if ($protected || $count)
	{
		echo '&nbsp;';
	}
	else
	{
		echo '<a href="' . $rootpath . 'type_contact/delete.php?id=' . $t['id'] . '" ';
		echo 'class="btn btn-danger btn-xs"><i class="fa fa-times"></i> ';
		echo 'Verwijderen</a>';
	}
	echo '</td>';

	echo '<td>';
	echo $count;
	echo '</td>';

	echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

echo '<p>Kunnen niet verwijderd worden: ';
echo 'contact types waarvan contacten bestaan en beschermde contact types (*).</p>';

include $rootpath . 'includes/inc_footer.php';
