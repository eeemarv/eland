<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;

class page
{

	public function terms(Request $request, app $app)
	{
		return $app['twig']->render('page/terms.html.twig', []);
	}

}

