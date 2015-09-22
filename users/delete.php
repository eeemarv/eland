<?php
ob_start();
$rootpath = "../";
$role = 'admin';
require_once($rootpath."includes/inc_default.php");

if (isset($_POST['cancel']))
{
	header('Location: ' . $rootpath . 'users/overview.php');
	exit;
}

if (!isset($_GET["id"]))
{
	$alert->error('User id niet bepaald.');
	header('Location: overview.php');
	exit;
}

$id = $_GET['id'];

if ($db->fetchColumn('select id from transactions where id_to = ? or id_from = ?', array($id, $id)))
{
	$alert->error('Een gebruiker met transacties kan niet worden verwijderd.');
	header('Location: overview.php');
	exit;
}

if ($s_id == $id)
{
	$alert->error('Je kan jezelf niet verwijderen.');
	header('Location: overview.php');
	exit;
}

$user = readuser($id);

if (!$user)
{
	$alert->error('Gebruiker bestaat niet.');
	header('Location: overview.php');
	exit;
}


if(isset($_POST['delete']))
{
	$password = $_POST['password'];

	if ($password)
	{
		$sha512 = hash('sha512', $password);

		if ($sha512 == $db->fetchColumn('select password from users where id = ?', array($s_id)))
		{
			$s3 = Aws\S3\S3Client::factory(array(
				'signature'	=> 'v4',
				'region'	=> 'eu-central-1',
				'version'	=> '2006-03-01',
			));

			$usr = $user['letscode'] . ' ' . $user['fullname'] . ' [id:' . $id . ']';
			$msgs = '';
			$st = $db->prepare('SELECT id, content, id_category, msg_type
				FROM messages
				WHERE id_user = ?');

			$st->bindValue(1, $id);
			$st->execute();

			while ($row = $st->fetch())
			{
				$msgs .= $row['id'] . ': ' . $row['content'] . ', ';
			}
			$msgs = trim($msgs, '\n\r\t ,;:');

			if ($msgs)
			{
				log_event('','user','Delete user ' . $usr . ', deleted Messages ' . $msgs);

				$db->delete('messages', array('id_user' => $id));
			}

			// remove orphaned images.

			$rs = $db->prepare('SELECT mp.id, mp."PictureFile"
				FROM msgpictures mp
				LEFT JOIN messages m ON mp.msgid = m.id
				WHERE m.id IS NULL');

			$rs->execute();

			while ($row = $rs->fetch())
			{
				$result = $s3->deleteObject(array(
					'Bucket' => getenv('S3_BUCKET'),
					'Key'    => $row['PictureFile'],
				));

				$db->delete('msgpictures', array('id' => $row['id']));
			}

			// update counts for each category

			$offer_count = $want_count = array();

			$rs = $db->prepare('SELECT m.id_category, count(m.*)
				FROM messages m, users u
				WHERE  m.id_user = u.id
					AND u.status IN (1, 2, 3)
					AND msg_type = 1
				GROUP BY m.id_category');

			$rs->execute();

			while ($row = $rs->fetch())
			{
				$offer_count[$row['id_category']] = $row['count'];
			}

			$rs = $db->prepare('SELECT m.id_category, count(m.*)
				FROM messages m, users u
				WHERE  m.id_user = u.id
					AND u.status IN (1, 2, 3)
					AND msg_type = 0
				GROUP BY m.id_category');

			$rs->execute();

			while ($row = $rs->fetch())
			{
				$want_count[$row['id_category']] = $row['count'];
			}

			$all_cat = $db->fetchAll('SELECT id, stat_msgs_offers, stat_msgs_wanted
				FROM categories
				WHERE id_parent IS NOT NULL');

			foreach ($all_cat as $val)
			{
				$offers = $val['stat_msgs_offers'];
				$wants = $val['stat_msgs_wanted'];
				$cat_id = $val['id'];

				$want_count[$cat_id] = (isset($want_count[$cat_id])) ? $want_count[$cat_id] : 0;
				$offer_count[$cat_id] = (isset($offer_count[$cat_id])) ? $offer_count[$cat_id] : 0;

				if ($want_count[$cat_id] == $wants && $offer_count[$cat_id] == $offers)
				{
					continue;
				}

				$stats = array(
					'stat_msgs_offers'	=> ($offer_count[$cat_id]) ?: 0,
					'stat_msgs_wanted'	=> ($want_count[$cat_id]) ?: 0,
				);
				
				$db->update('categories', $stats, array('id' => $cat_id));
			}

			//delete contacts
			$db->delete('contact', array('id_user' => $id));

			//delete userimage from bucket;
			if (isset($user['PictureFile']))
			{
				$result = $s3->deleteObject(array(
					'Bucket' => getenv('S3_BUCKET'),
					'Key'    => $user['PictureFile'],
				));
			}

			//finally, the user
			$db->delete('users', array('id' => $id));
			$redis->expire($schema . '_user_' . $id, 0);

			$alert->success('De gebruiker is verwijderd.');
			header('Location: overview.php');
			exit;
		}
		else
		{
			$alert->error('Paswoord is niet correct.');
		}
	}
	else
	{
		$alert->error('Paswoord is niet ingevuld.');
	}
}

$h1 = 'Gebruiker ' . $user['letscode'] . ' ' . $user['fullname'] . ' verwijderen?';
$fa = 'user';

include $rootpath . 'includes/inc_header.php';

echo '<p><font color="red">Alle gegevens, Vraag en aanbod, contacten en afbeeldingen van ' . $user['letscode'] . ' ' . $user['fullname'];
echo ' worden verwijderd.</font></p>';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo '<form method="post" class="form-horizontal">';

echo '<div class="form-group">';
echo '<label for="password" class="col-sm-2 control-label">Paswoord</label>';
echo '<div class="col-sm-10">';
echo '<input type="password" class="form-control" id="password" name="password" ';
echo 'value="" required autocomplete="off">';
echo '</div>';
echo '</div>';

echo '<a href="' . $rootpath . 'users/view.php?id=' . $id . '" class="btn btn-default">Annuleren</a>&nbsp;';
echo '<input type="submit" value="Verwijderen" name="delete" class="btn btn-danger">';

echo '</form>';

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';
