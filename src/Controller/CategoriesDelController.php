<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class CategoriesDelController extends AbstractController
{
    public function categories_del(
        Request $request,
        app $app,
        int $id,
        Db $db
    ):Response
    {
        if($request->isMethod('POST'))
        {
            if ($error_token = $app['form_token']->get_error())
            {
                $app['alert']->error($error_token);
                $app['link']->redirect('categories', $app['pp_ary'], []);
            }

            if ($db->delete($app['pp_schema'] . '.categories', ['id' => $id]))
            {
                $app['alert']->success('Categorie verwijderd.');
                $app['link']->redirect('categories', $app['pp_ary'], []);
            }

            $app['alert']->error('Categorie niet verwijderd.');
        }

        $fullname = $db->fetchColumn('select fullname
            from ' . $app['pp_schema'] . '.categories
            where id = ?', [$id]);

        $app['heading']->add('Categorie verwijderen : ');
        $app['heading']->add($fullname);
        $app['heading']->fa('clone');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= "<p><font color='#F56DB5'><strong>Ben je zeker dat deze categorie";
        $out .= " moet verwijderd worden?</strong></font></p>";
        $out .= '<form method="post">';

        $out .= $app['link']->btn_cancel('categories', $app['pp_ary'], []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $app['form_token']->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('categories');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}