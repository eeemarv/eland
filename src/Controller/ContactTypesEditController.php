<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\contact_types;
use Doctrine\DBAL\Connection as Db;

class ContactTypesEditController extends AbstractController
{
    public function contact_types_edit(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        $tc_prefetch = $db->fetchAssoc('select *
            from ' . $app['pp_schema'] . '.type_contact
            where id = ?', [$id]);

        if (in_array($tc_prefetch['abbrev'], contact_types::PROTECTED))
        {
            $app['alert']->warning('Beschermd contact type.');
            $app['link']->redirect('contact_types', $app['pp_ary'], []);
        }

        if($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
                $app['link']->redirect('contact_types', $app['pp_ary'], []);
            }

            $tc = [
                'name'		=> $request->request->get('name', ''),
                'abbrev'	=> $request->request->get('abbrev', ''),
                'id'		=> $id,
            ];

            $error = empty($tc['name']) ? 'Geen naam ingevuld! ' : '';
            $error .= empty($tc['abbrev']) ? 'Geen afkorting ingevuld! ' : $error;

            if (!$error)
            {
                if ($db->update($app['pp_schema'] . '.type_contact',
                    $tc,
                    ['id' => $id]))
                {
                    $app['alert']->success('Contact type aangepast.');
                    $app['link']->redirect('contact_types', $app['pp_ary'], []);
                }
                else
                {
                    $app['alert']->error('Fout bij het opslaan.');
                }
            }
            else
            {
                $app['alert']->error('Fout in één of meer velden. ' . $error);
            }
        }
        else
        {
            $tc = $tc_prefetch;
        }

        $app['heading']->add('Contact type aanpassen');
        $app['heading']->fa('circle-o-notch');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">Naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="name_addon">';
        $out .= '<span class="fa fa-circle-o-notch"></span></span>';
        $out .= '<input type="text" class="form-control" id="name" ';
        $out .= 'name="name" maxlength="20" ';
        $out .= 'value="';
        $out .= $tc['name'];
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="abbrev" class="control-label">Afkorting</label>';
        $out .= '<input type="text" class="form-control" id="abbrev" ';
        $out .= 'name="abbrev" maxlength="11" ';
        $out .= 'value="';
        $out .= $tc['abbrev'];
        $out .= '" required>';
        $out .= '</div>';

        $out .= $app['link']->btn_cancel('contact_types', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Opslaan" class="btn btn-primary btn-lg">';
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