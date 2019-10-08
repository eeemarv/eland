<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\contact_types;

class ContactTypesDelController extends AbstractController
{
    public function contact_types_del(Request $request, app $app, int $id):Response
    {
        $ct = $app['db']->fetchAssoc('select *
            from ' . $app['pp_schema'] . '.type_contact
            where id = ?', [$id]);

        if (in_array($ct['abbrev'], contact_types::PROTECTED))
        {
            $app['alert']->warning('Beschermd contact type.');
            $app['link']->redirect('contact_types', $app['pp_ary'], []);
        }

        if ($app['db']->fetchColumn('select id
            from ' . $app['pp_schema'] . '.contact
            where id_type_contact = ?', [$id]))
        {
            $app['alert']->warning('Er is ten minste één contact
                van dit contact type, dus kan het contact type
                niet verwijderd worden.');
            $app['link']->redirect('contact_types', $app['pp_ary'], []);
        }

        if($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
                $app['link']->redirect('contact_types', $app['pp_ary'], []);
            }

            if ($app['db']->delete($app['pp_schema'] . '.type_contact', ['id' => $id]))
            {
                $app['alert']->success('Contact type verwijderd.');
            }
            else
            {
                $app['alert']->error('Fout bij het verwijderen.');
            }

            $app['link']->redirect('contact_types', $app['pp_ary'], []);
        }

        $app['heading']->add('Contact type verwijderen: ' . $ct['name']);
        $app['heading']->fa('circle-o-notch');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<p>Ben je zeker dat dit contact type verwijderd mag worden?</p>';
        $out .= '<form method="post">';

        $out .= $app['link']->btn_cancel('contact_types', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';
        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('contact_types');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
