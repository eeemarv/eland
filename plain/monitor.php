<?php declare(strict_types=1);

if (!$app['pp_anonymous'])
{
	exit;
}

echo '<html><head></head><body>';

$app['monitor_process']->monitor();

echo '</body>';
