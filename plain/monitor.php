<?php

if (!$app['s_anonymous'])
{
	exit;
}

echo '<html><head></head><body>';

$app['monitor_process']->monitor();

echo '</body>';
