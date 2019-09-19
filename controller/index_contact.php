<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class index_contact
{
    public function index_contact(Request $request, app $app):Response
    {
        $mail = $request->request->get('mail', '');
        $message = $request->request->get('message', '');





        $app['menu']->set('index_contact');

        return $app->render('base/index_contact.html.twig', [
            'mail'          => $mail,
            'message'       => $message,
            'form_token'    => $app['form_token']->get(),
            'captcha'       => $app['captcha']->get_form_field(),
        ]);
    }
}
