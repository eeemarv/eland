<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use util\app;

$app = require_once __DIR__ . '/../app.php';

// disable for now
exit;

$app->match('/contact', 'controller\\contact::contact')->bind('contact');
$app->get('/contact-confirm/{token}', 'controller\\contact::contact_confirm')->bind('contact_confirm');

$app->match('/newsletter', 'controller\\contact::newsletter_subscribe')
	->bind('newsletter_subscribe');
$app->get('/newsletter/{token}', 'controller\\contact::newsletter_subscribe_confirm')
	->bind('newsletter_subscribe_confirm');

$app->get('/', function (Request $request, app $app)
{
    return $app['twig']->render('index.html.twig', []);
})->bind('index');

$app->run();
