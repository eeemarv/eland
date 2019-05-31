<?php

$app['s_logins'] = $app['session']->get('logins') ?? [];

foreach($app['s_logins'] as $sch => $uid)
{
	$app['xdb']->set('logout', $uid, ['time' => time()], $sch);
}

$app['session']->invalidate();

$app['monolog']->info('user logged out',
	['schema' => $app['tschema']]);

$app['link']->redirect('login', ['system' => $app['pp_system']], []);
