<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;

class vote
{
	public function vote(Request $request, app $app)
	{

	}

	/**
	 *
	 */

	public function token(Request $request, app $app, $token)
	{
		$ticket = json_decode($app['xdb']->get('ticket_' . $token), true);

		$app['session']->set('ticket', $ticket);

		return $app->redirect('/vote');
	}

	public function edit__dd(Request $request, app $app)
	{

	}
}

