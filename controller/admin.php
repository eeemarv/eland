<?php

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class admin
{
	public function settings(Request $request, app $app)
	{
		$editors = $app['xdb']->get('project_editors');
		$settings = $app['xdb']->get('settings');


		$settings = [
			'editors'				=> '',
			'max_projects_default'	=> 5,
		];

		$builder = $app['form.factory']->createBuilder(FormType::class, $settings);

		$builder->add('max_projects_default', NumberType::class)
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
			'editors'	=> $editors,
		]);
	}

	/**
	 *
	 */

	public function editor(Request $request, app $app)
	{

	}

	public function post(Request $request, app $app)
	{
		$email = $request->get('email');

		$errors = $app['validator']->validate($email, new Assert\Email());

		if ($errors > 0)
		{
			$app['monolog']->info('unvalid email: ' . $email . ' - ' . (string) $errors);

			return $app->json(['notice' => $app->trans('notice.unvalid_email')]);
		}

		$editors = $app['xdb']->get('project_editors');

		if (!isset($editors[$email]))
		{
			$app['monolog']->info('no access for email: ' . $email);

			return $app->json(['notice' => $app->trans('notice.no_access_email')]);
		}

		$token = $app['token']->set_length(12)->gen();

		$key = 'login_token_' . $token;

		$app['predis']->set($key, $email);
		$app['predis']->expire($key, 14400); // 4 hours;

		$host = $request->getHost();

		$app['mail']->queue([
			'template'	=> 'login_token',
			'to'		=> $email,
			'url'		=> $host . '/' . $token,
		]);

		return $app->json(['notice' => $app->trans('notice.token_send_email')]);
	}

	public function token(Request $request, app $app, $token)
	{
		$edit_login = $app['xdb']->get('edit_login_' . $token);

		$app['session']->set('edit_login', $edit_login);

		return $app->redirect('/edit');
	}
}

