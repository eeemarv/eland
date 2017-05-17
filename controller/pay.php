<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class pay
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

	public function qr(Request $request, app $app)
	{
		$token = $app['token']->set_hyphen_chance(9)->set_length(10)->gen();


		return $app['twig']->render('pay/qr.html.twig', [

			'voucher_url'	=> 'https://omdev.be/' . $token,
			'amount'		=> 30,
			'unit'			=> 'Ant',

		]);
	}

	public function pay(Request $request, app $app)
	{
		$data = [
			'amount'				=> 0,
		];

		$builder = $app['form.factory']->createBuilder(FormType::class, $data);

		$builder->add('amount', NumberType::class)
			->add('submit',SubmitType::class);

		$form = $builder->getForm();

		$form->handleRequest($request);

		if ($form->isValid())
		{
			$data = $form->getData();

			return $app->redirect('/edit');
		}

		return $app['twig']->render('pay/pay.html.twig', [
			'form' 			=> $form->createView(),
			'number_format'	=> '0.00',
			'unit'			=> 'ANT',
			'transactions'	=> [],
		]);


//
		$token = $app['token']->set_hyphen_chance(9)->set_length(12)->gen();


		return $app['twig']->render('pay/pay.html.twig', [
			'unit'			=> 'Ant',
		]);
	}

}

