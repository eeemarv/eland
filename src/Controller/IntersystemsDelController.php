<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class IntersystemsDelController extends AbstractController
{
    public function intersystems_del(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        $group = $db->fetchAssoc('select *
            from ' . $app['pp_schema'] . '.letsgroups
            where id = ?', [$id]);

        if (!$group)
        {
            $alert_service->error('Systeem niet gevonden.');
            $link_render->redirect('intersystems', $app['pp_ary'], []);
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
                $link_render->redirect('intersystems', $app['pp_ary'], []);
            }

            if($db->delete($app['pp_schema'] . '.letsgroups', ['id' => $id]))
            {
                $alert_service->success('InterSysteem verwijderd.');

                $intersystems_service->clear_cache($app['pp_schema']);

                $link_render->redirect('intersystems', $app['pp_ary'], []);
            }

            $alert_service->error('InterSysteem niet verwijderd.');
        }

        $heading_render->add('InterSysteem verwijderen: ' . $group['groupname']);
        $heading_render->fa('share-alt');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<p class="text-danger">Ben je zeker dat dit interSysteem ';
        $out .= 'moet verwijderd worden?</p>';
        $out .= '<div><p>';
        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('intersystems', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form></p>';
        $out .= '</div>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('intersystems');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
