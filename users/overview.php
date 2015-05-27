<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once $rootpath . 'includes/inc_default.php';
require_once $rootpath . 'includes/inc_adoconnection.php';

$status_ary = array(
	0 	=> 'inactief',
	1 	=> 'actief',
	2 	=> 'uitstapper',
	3	=> 'instapper',		// not used
	4	=> 'secretariaat',	// not used
	5	=> 'info-pakket',
	6	=> 'info-moment',
	7	=> 'extern',
);

$status_class = array(
	0	=> 'black',
	1	=> '',
	2	=> 'danger',
	3	=> 'success',
	4	=> '',
	5	=> 'warning',
	6	=> 'info',
	7	=> 'inactif',
);

$users = $db->GetArray('select * from users order by letscode asc');

$newusertreshold = time() - readconfigfromdb('newuserdays') * 86400;

$c_ary = $db->GetArray('SELECT tc.abbrev, c.id_user, c.value
	FROM contact c, type_contact tc
	WHERE tc.id = c.id_type_contact
		AND tc.abbrev IN (\'mail\', \'tel\', \'gsm\')');

$contacts = array();

foreach ($c_ary as $c)
{
	$contacts[$c['id_user']][$c['abbrev']][] = $c['value'];
}

$top_buttons = '<a href="' . $rootpath . 'users/edit.php?mode=new" class="btn btn-success"';
$top_buttons .= ' title="Gebruiker toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'users/saldomail.php" class="btn btn-default"';
$top_buttons .= ' title="Saldo mail aan/uitzetten"><i class="fa fa-envelope-o"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Saldo mail</span></a>';

$h1 = 'Gebruikers';
$fa = 'users';

include $rootpath . 'includes/inc_header.php';

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-hover footable">';
echo '<thead>';

echo '<tr>';
echo '<th data-sort-initial="true">Code</th>';
echo '<th>Naam</th>';
echo '<th data-hide="phone">Rol</th>';
echo '<th data-hide="phone">Status</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Tel</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">gsm</th>';
echo '<th data-hide="phone">Postc</th>';
echo '<th data-hide="phone, tablet" data-sort-ignore="true">Mail</th>';
echo '<th data-hide="phone">Saldo</th>';
echo '<th data-hide="all">Min</th>';
echo '<th data-hide="all">Max</th>';
echo '<th data-hide="all">Ingeschreven</th>';
echo '<th data-hide="all">Geactiveerd</th>';
echo '<th data-hide="all">Laatst aangepast</th>';
echo '<th data-hide="all">Laatst ingelogd</th>';
echo '<th data-hide="all">Profielfoto</th>';
echo '<th data-hide="all">Admin commentaar</th>';
echo '<th data-hide="all" data-sort-ignore="true">Aanpassen</th>';
echo '</tr>';

echo '</thead>';
echo '<tbody>';

foreach($users as $u)
{
	$id = $u['id'];
	$status = $u['status'];
	$new_user = ($newusertreshold < strtotime($u['adate'])) ? true : false;
	$class = ($new_user && $status == 1) ? 'success' : '';
	$class = ($status_class[$u['status']]) ?: $class;
	$class = ($class) ? ' class="' . $class . '"' : '';

	echo '<tr' . $class . '>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'users/view.php?id=' .$id .'">';
	echo $u['letscode'];
	echo '</a></td>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'users/view.php?id=' .$id .'">'.htmlspecialchars($u['fullname'],ENT_QUOTES);
	echo '</a></td>';

	echo '<td>';
	echo $u['accountrole'];
	echo '</td>';

	echo '<td>';
	echo $status_ary[$u['status']];
	echo '</td>';
	
	echo '<td>';
	echo render_contacts($contacts[$id]['tel']);
	echo '</td>';
	
	echo '<td>';
	echo render_contacts($contacts[$id]['gsm']);
	echo '</td>';
	
	echo '<td>' . $u['postcode'] . '</td>';
	
	echo '<td>';
	echo render_contacts($contacts[$id]['mail'], 'mail');
	echo '</td>';

	echo '<td>';
	$balance = $u['saldo'];
	$text_danger = ($balance < $u['minlimit'] || ($u['maxlimit'] != NULL && $balance > $u['maxlimit'])) ? 'text-danger ' : '';
	echo '<span class="' . $text_danger  . 'label label-default">' . $balance . '</span>';
	echo '</td>';

	echo '<td>';
	echo '<span class="label label-danger">' . $u['minlimit'] . '</span>';
	echo '</td>';

	echo '<td>';
	echo '<span class="label label-success">' . $u['maxlimit'] . '</span>';
	echo '</td>';

	echo '<td>';
	echo $u['cdate'];
	echo '</td>';

	echo '<td>';
	echo $u['adate'];
	echo '</td>';

	echo '<td>';
	echo $u['mdate'];
	echo '</td>';

	echo '<td>';
	echo $u['lastlogin'];
	echo '</td>';
	
	echo '<td>';
	echo ($u['PictureFile']) ? 'Ja' : 'Nee';
	echo '</td>';

	echo '<td>';
	echo htmlspecialchars($u['admincomment'], ENT_QUOTES);
	echo '</td>';

	echo '<td>';
	echo '<a href="' . $rootpath . 'users/edit.php?mode=edit&id=' . $id . '" class="btn btn-default btn-xs">Aanpassen</a>';
	echo '</td>';
		
	echo '</tr>';

}
echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

function render_contacts($contacts, $abbrev = null)
{
	if (count($contacts))
	{
		end($contacts);
		$end = key($contacts);

		$f = ($abbrev == 'mail') ? '<a href="mailto:%1$s">%1$s</a>' : '%1$s';

		foreach ($contacts as $key => $contact)
		{
			echo sprintf($f, htmlspecialchars($contact, ENT_QUOTES));

			if ($key == $end)
			{
				break;
			}
			echo '<br>';
		}
	}
	else
	{
		echo '&nbsp;';
	}
}
