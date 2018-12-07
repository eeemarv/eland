<?php

$page_access = 'admin';
require_once __DIR__ . '/include/web.php';

$status_msgs = false;
$tschema = $app['this_group']->get_schema();

$non_unique_mail = $app['db']->fetchAll('select c.value, count(c.*)
	from ' . $tschema . '.contact c, ' .
		$tschema . '.type_contact tc, ' .
		$tschema . '.users u
	where c.id_type_contact = tc.id
		and tc.abbrev = \'mail\'
		and c.id_user = u.id
		and u.status in (1, 2)
	group by value
	having count(*) > 1');

if (count($non_unique_mail))
{
	$st = $app['db']->prepare('select id_user
		from ' . $tschema . '.contact c
		where c.value = ?');

	foreach ($non_unique_mail as $key => $ary)
	{
		$st->bindValue(1, $ary['value']);
		$st->execute();

		while ($row = $st->fetch())
		{
			$non_unique_mail[$key]['users'][$row['id_user']] = true;
		}
	}

	$status_msgs = true;
}

//

$non_unique_letscode = $app['db']->fetchAll('select letscode, count(*)
	from ' . $tschema . '.users
	where letscode <> \'\'
	group by letscode
	having count(*) > 1');

if (count($non_unique_letscode))
{
	$st = $app['db']->prepare('select id
		from ' . $tschema . '.users
		where letscode = ?');

	foreach ($non_unique_letscode as $key => $ary)
	{
		$st->bindValue(1, $ary['letscode']);
		$st->execute();

		while ($row = $st->fetch())
		{
			$non_unique_letscode[$key]['users'][$row['id']] = true;
		}
	}

	$status_msgs = true;
}

//

$non_unique_name = $app['db']->fetchAll('select name, count(*)
	from ' . $tschema . '.users
	where name <> \'\'
	group by name
	having count(*) > 1');

if (count($non_unique_name))
{
	$st = $app['db']->prepare('select id
		from ' . $tschema . '.users
		where name = ?');

	foreach ($non_unique_name as $key => $ary)
	{
		$st->bindValue(1, $ary['name']);
		$st->execute();

		while ($row = $st->fetch())
		{
			$non_unique_name[$key]['users'][$row['id']] = true;
		}
	}

	$status_msgs = true;
}

//

$unvalid_mail = $app['db']->fetchAll('select c.id, c.value, c.id_user
	from ' . $tschema . '.contact c, ' .
		$tschema . '.type_contact tc
	where c.id_type_contact = tc.id
		and tc.abbrev = \'mail\'
		and c.value !~ \'^[A-Za-z0-9!#$%&*+/=?^_`{|}~.-]+@[A-Za-z0-9.-]+[.][A-Za-z]+$\'');

//
$no_mail = array();

$st = $app['db']->prepare(' select u.id
	from ' . $tschema . '.users u
	where u.status in (1, 2)
		and not exists (select c.id
			from ' . $tschema . '.contact c, ' . $tschema . '.type_contact tc
			where c.id_user = u.id
				and c.id_type_contact = tc.id
				and tc.abbrev = \'mail\')');

$st->execute();

while ($row = $st->fetch())
{
	$no_mail[] = $row['id'];
	$status_msgs = true;
}

$empty_letscode = $app['db']->fetchAll('select id
	from ' . $tschema . '.users
	where status in (1, 2) and letscode = \'\'');

$empty_name = $app['db']->fetchAll('select id
	from ' . $tschema . '.users
	where name = \'\'');

if ($unvalid_mail || $empty_letscode || $empty_name)
{
	$status_msgs = true;
}

$no_msgs_users = $app['db']->fetchAll('select id, letscode, name, saldo, status
	from ' . $tschema . '.users u
	where status in (1, 2)
		and not exists (select 1 from ' . $tschema . '.messages m where m.id_user = u.id)');

if (count($no_msgs_users))
{
	$status_msgs = true;
}

$h1 = 'Status';
$fa = 'exclamation-triangle';

include __DIR__ . '/include/header.php';

if ($status_msgs)
{
	echo '<div class="panel panel-danger">';

	echo '<ul class="list-group">';

	if (count($non_unique_mail))
	{
		echo '<li class="list-group-item">';

		if (count($non_unique_mail) == 1)
		{
			echo 'Een E-mail adres komt meer dan ';
			echo 'eens voor onder de actieve accounts ';
			echo 'in dit Systeem. ';
			echo 'Gebruikers met dit E-mail adres ';
			echo 'kunnen niet inloggen met E-mail adres. ';
		}
		else
		{
			echo 'Meerdere E-mail adressen komen meer ';
			echo 'dan eens voor onder de actieve ';
			echo 'Accounts in dit Systeem. ';
			echo 'Gebruikers met een E-mail adres ';
			echo 'dat meer dan eens voorkomt, ';
			echo 'kunnen niet inloggen met E-mail adres.';
		}

		echo '<ul>';

		foreach ($non_unique_mail as $ary)
		{
			echo '<li>';
			echo $ary['value'] . ' (' . $ary['count'] . '): ';

			$user_ary = array();

			foreach($ary['users'] as $user_id => $dummy)
			{
				$user_ary[] = link_user($user_id, $tschema);
			}

			echo implode(', ', $user_ary);
			echo '</li>';
		}

		echo '</ul>';
		echo '</li>';
	}

	if (count($non_unique_letscode))
	{
		echo '<li class="list-group-item">';

		if (count($non_unique_letscode) == 1)
		{
			echo 'Een Account Code komt meer ';
			echo 'dan eens voor in dit Systeem. ';
			echo 'Actieve gebruikers met deze ';
			echo 'accounts kunnen niet inloggen met Account Code ';
			echo 'en kunnen geen transacties ';
			echo 'doen of transacties ontvangen. ';
		}
		else
		{
			echo 'Meerdere Account Codes komen ';
			echo 'meer dan eens voor in dit Systeem. ';
			echo 'Gebruikers met deze accounts ';
			echo 'kunnen niet inloggen met de Account Code ';
			echo 'en kunnen geen transacties ';
			echo 'doen of transacties ontvangen.';
		}

		echo '<ul>';
		foreach ($non_unique_letscode as $ary)
		{
			echo '<li>';
			echo $ary['letscode'] . ' (' . $ary['count'] . '): ';

			$user_ary = array();

			foreach($ary['users'] as $user_id => $dummy)
			{
				$user_ary[] = link_user($user_id, $tschema);
			}

			echo implode(', ', $user_ary);
			echo '</li>';
		}
		echo '</ul>';
		echo '</li>';
	}

	if (count($non_unique_name))
	{
		echo '<li class="list-group-item">';

		if (count($non_unique_name) == 1)
		{
			echo 'Een gebruikersnaam komt meer ';
			echo 'dan eens voor in dit Systeem. ';
			echo 'Actieve gebruikers met deze ';
			echo 'gebruikersnaam kunnen niet ';
			echo 'inloggen met gebruikersnaam. ';
		}
		else
		{
			echo 'Meerdere gebruikersnamen komen meer dan eens voor in dit Systeem.';
			echo 'Actieve gebruikers met een gebruikersnaam die meer dan eens voorkomt, kunnen niet inloggen met gebruikersnaam.';
		}

		echo '<ul>';
		foreach ($non_unique_name as $ary)
		{
			echo '<li>';
			echo $ary['name'] . ' (' . $ary['count'] . '): ';

			$user_ary = array();

			foreach($ary['users'] as $user_id => $dummy)
			{
				$user_ary[] = link_user($user_id, $tschema);
			}

			echo implode(', ', $user_ary);
			echo '</li>';
		}
		echo '</ul>';
		echo '</li>';
	}

	if (count($unvalid_mail))
	{
		echo '<li class="list-group-item">';
		if (count($unvalid_mail) == 1)
		{
			echo 'Dit Systeem bevat een fout ';
			echo 'geformateerd E-mail adres. ';
			echo 'Pas het aan of verwijder het!';
		}
		else
		{
			echo 'Dit Systeem bevat fout geformateerde ';
			echo 'E-mail adressen. ';
			echo 'Verwijder deze of pas deze aan!';
		}

		echo '<ul>';
		foreach ($unvalid_mail as $ary)
		{
			echo '<li>';
			echo $ary['value'] .  ' ';
			echo aphp('contacts', ['edit' => $ary['id']], 'Aanpassen', 'btn btn-default btn-xs') . ' ';
			echo aphp('contacts', ['del' => $ary['id']], 'Verwijderen', 'btn btn-danger btn-xs') . ' ';
			echo ' : ';
			echo link_user($ary['id_user'], $tschema);
			echo '</li>';
		}

		echo '</ul>';
		echo '</li>';
	}

	if (count($no_mail))
	{
		echo '<li class="list-group-item">';
		if (count($no_mail) == 1)
		{
			echo 'Eén actieve gebruiker heeft geen E-mail adres.';
		}
		else
		{
			echo count($no_mail) . ' actieve gebruikers hebben geen E-mail adres.';
		}

		echo '<ul>';
		foreach ($no_mail as $user_id)
		{
			echo '<li>';
			echo link_user($user_id, $tschema);
			echo '</li>';
		}

		echo '</ul>';
		echo '</li>';
	}

	if (count($empty_name))
	{
		echo '<li class="list-group-item">';
		if (count($empty_name) == 1)
		{
			echo 'Eén gebruiker heeft geen gebruikersnaam.';
		}
		else
		{
			echo count($empty_name) . ' gebruikers hebben geen gebruikersnaam.';
		}

		echo '<ul>';
		foreach ($empty_name as $ary)
		{
			echo '<li>';
			echo link_user($ary['id'], $tschema);
			echo '</li>';
		}

		echo '</ul>';
		echo '</li>';
	}

	if (count($empty_letscode))
	{
		echo '<li class="list-group-item">';
		if (count($empty_letscode) == 1)
		{
			echo 'Eén actieve gebruiker heeft geen Account Code.';
		}
		else
		{
			echo count($empty_letscode) . ' actieve gebruikers hebben geen Account Code.';
		}

		echo '<ul>';
		foreach ($empty_letscode as $ary)
		{
			echo '<li>';
			echo link_user($ary['id'], $tschema);
			echo '</li>';
		}

		echo '</ul>';
		echo '</li>';
	}

	if (count($no_msgs_users))
	{
		echo '<li class="list-group-item">';
		if (count($no_msgs_users) == 1)
		{
			echo 'Eén actieve gebruiker heeft geen vraag of aanbod.';
		}
		else
		{
			echo count($no_msgs_users) . ' actieve gebruikers hebben geen vraag of aanbod.';
		}

		echo '<ul>';

		$currency = $app['config']->get('currency', $tschema);

		foreach ($no_msgs_users as $u)
		{
			echo '<li>';
			echo link_user($u['id'], $tschema);
			echo ($u['status'] == 2) ? ' <span class="text-danger">Uitstapper</span>' : '';
			echo ', saldo: ';
			echo $u['saldo'];
			echo ' ';
			echo $currency;
			echo '</li>';
		}

		echo '</ul>';
		echo '</li>';
	}

	echo '</ul>';
	echo '</div>';
}
else
{
	echo '<div class="panel panel-info">';
	echo '<div class="panel-body">';
	echo '<p>Geen bijzonderheden</p>';
	echo '</div>';
	echo '</div>';
}

include __DIR__ . '/include/footer.php';
