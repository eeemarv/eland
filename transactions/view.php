<?php
ob_start();
$rootpath = '../';
$role = 'user';
require_once $rootpath . 'includes/inc_default.php';

if (!isset($_GET['id']))
{
	$alert->warning('Geen id opgegeven.');
	header('Location: ' . $rootpath . 'transactions/overview.php');
	exit;
}

$id = $_GET["id"];
$transaction = $db->GetRow('select t.*,
	fu.letscode as from_letscode, fu.fullname as from_fullname,
	tu.letscode as to_letscode, tu.fullname as to_fullname
	from transactions t, users fu, users tu
	where t.id = ' . $id . '
		and fu.id = t.id_from
		and tu.id = t.id_to');

$currency = readconfigfromdb('currency');

$top_buttons = '<a href="' . $rootpath . 'transactions/add.php" class="btn btn-success"';
$top_buttons .= ' title="Transactie toevoegen"><i class="fa fa-plus"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Toevoegen</span></a>';

$top_buttons .= '<a href="' . $rootpath . 'transactions/alltrans.php" class="btn btn-default"';
$top_buttons .= ' title="Transactielijst"><i class="fa fa-exchange"></i>';
$top_buttons .= '<span class="hidden-xs hidden-sm"> Lijst</span></a>';

$h1 = 'Transactie';
$fa = 'exchange';

include $rootpath . 'includes/inc_header.php';

echo '<dl class="dl-horizontal">';
echo '<dt>Tijdstip</dt>';
echo '<dd>';
echo $transaction['date'];
echo '</dd>';

echo '<dt>Creatietijdstip</dt>';
echo '<dd>';
echo $transaction['cdate'];
echo '</dd>';

echo '<dt>Transactie ID</dt>';
echo '<dd>';
echo $transaction['transid'];
echo '</dd>';

echo '<dt>Van account</dt>';
echo '<dd>';
echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $transaction['id_from'] . '">';
echo $transaction['from_letscode'] . ' ' . $transaction['from_fullname'];
echo '</a>';
echo '</dd>';

if ($transaction['real_from'])
{
	echo '<dt>Van remote gebruiker</dt>';
	echo '<dd>';
	echo $transaction['real_from'];
	echo '</dd>';
}

echo '<dt>Naar account</dt>';
echo '<dd>';
echo '<a href="' . $rootpath . 'memberlist_view.php?id=' . $transaction['id_to'] . '">';
echo $transaction['to_letscode'] . ' ' . $transaction['to_fullname'];
echo '</a>';
echo '</dd>';

if ($transaction['real_to'])
{
	echo '<dt>Naar remote gebruiker</dt>';
	echo '<dd>';
	echo $transaction['real_to'];
	echo '</dd>';
}

echo '<dt>Waarde</dt>';
echo '<dd>';
echo $transaction['amount'] . ' ' . $currency;
echo '</dd>';

echo '<dt>Omschrijving</dt>';
echo '<dd>';
echo $transaction['description'];
echo '</dd>';

echo '</dl>';

include $rootpath . 'includes/inc_footer.php';
