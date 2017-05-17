<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class index
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

	public function home(Request $request, app $app)
	{
		$token = $app['token']->set_hyphen_chance(9)->set_length(12)->gen();


		return $app['twig']->render('index.html.twig', [

			'voucher_url'	=> 'https://omdev.be/' . $token,
			'amount'		=> 30,
			'unit'			=> 'Ant',
			'transactions'	=> [],
		]);
	}

	public function pay(Request $request, app $app)
	{
		$editors = $app['xdb']->get('project_editors');
		$settings = $app['xdb']->get('settings');

		$data = [
			'amount'				=> '',
		];

		$builder->add('amount', NumberType::class)
			->add('submit',SubmitType::class);


/*
			->add('editors', TextareaType::class)
			->add('default_max_projects', NumberType::class)
			->add('submit', SubmitType::class, [
				'label' => 'Save',
			])*/

		$form = $builder->getForm();

		$form->handleRequest($request);

		if ($form->isValid())
		{
			$data = $form->getData();

			return $app->redirect('/edit');
		}

		return $app['twig']->render('admin/settings.html.twig', [
			'form' 		=> $form->createView(),
		]);


//
		$token = $app['token']->set_length(12)->gen();


		return $app['twig']->render('pay/pay.html.twig', [
			'unit'			=> 'Ant',
		]);
	}

}

