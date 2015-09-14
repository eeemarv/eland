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
			$rs = $db->Execute('SELECT id, content, id_category, msg_type
				FROM messages
				WHERE id_user = \'' .$id . '\'');
			while ($row = $rs->FetchRow())
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
			$orphan_images = $db->fetchAll('SELECT mp.id, mp."PictureFile"
				FROM msgpictures mp
				LEFT JOIN messages m ON mp.msgid = m.id
				WHERE m.id IS NULL');

			assoc($orphan_images);

			if (count($orphan_images))
			{
				foreach ($orphan_images as $msgp_id => $file)
				{
					$result = $s3->deleteObject(array(
						'Bucket' => getenv('S3_BUCKET'),
						'Key'    => $file,
					));

					$db->delete('msgpictures', array('id' => $msgp_id));
				}
			}

			// update counts for each category

			$offer_count = $db->fetchAll('SELECT m.id_category, COUNT(m.*)
				FROM messages m, users u
				WHERE  m.id_user = u.id
					AND u.status IN (1, 2, 3)
					AND msg_type = 1
				GROUP BY m.id_category');

			assoc($offer_count);

			$want_count = $db->fetchAll('SELECT m.id_category, COUNT(m.*)
				FROM messages m, users u
				WHERE  m.id_user = u.id
					AND u.status IN (1, 2, 3)
					AND msg_type = 0
				GROUP BY m.id_category');

			assoc($want_count);

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
				
				$db->AutoExecute('categories', $stats, 'UPDATE', 'id = ' . $cat_id);
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

include($rootpath."includes/inc_header.php");

echo '<p><font color="red">Alle gegevens, Vraag en aanbod, contacten en afbeeldingen van ' . $user['letscode'] . ' ' . $user['fullname'];
echo ' worden verwijderd.</font></p>';

echo '<div class="panel panel-info">';
echo '<div class="panel-heading">';

echo "<div class='border_b'>";
echo "<form method='POST'>";
echo "<table class='data'>";
echo "<tr><td>Paswoord:</td><td>";
echo '<input type="password" name="password" value="" autocomplete="off">';
echo "</td></tr>";
echo "<tr><td colspan='2'>";
echo "<input type='submit' name='cancel' value='Annuleren'>&nbsp;";
echo "<input type='submit' name='delete' value='Verwijderen'>";
echo "</td></tr>";
echo "</table></form></div>";

echo '</div>';
echo '</div>';

include $rootpath . 'includes/inc_footer.php';

