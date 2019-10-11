<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;

class UsersImageDelController extends AbstractController
{
    public function users_image_del(
        Request $request,
        Db $db,
        AlertService $alert_service,
        AccountRender $account_render,
        HeadingRender $heading_render,
        LinkRender $link_render,
        UserCacheService $user_cache_service,
        MenuService $menu_service,
        string $env_s3_url
    ):Response
    {
        if ($app['s_id'] < 1)
        {
            $alert_service->error('Je hebt geen toegang tot deze actie');
            $link_render->redirect($app['r_users'], $app['pp_ary'], []);
        }

        return $this->users_image_del_admin(
            $request,
            $app['s_id'],
            $db,
            $alert_service,
            $account_render,
            $heading_render,
            $link_render,
            $user_cache_service,
            $menu_service,
            $env_s3_url
        );
    }

    public function users_image_del_admin(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        AccountRender $account_render,
        HeadingRender $heading_render,
        LinkRender $link_render,
        UserCacheService $user_cache_service,
        MenuService $menu_service,
        string $env_s3_url
    ):Response
    {
        $user = $user_cache_service->get($id, $app['pp_schema']);

        if (!$user)
        {
            $alert_service->error('De gebruiker bestaat niet.');
            $link_render->redirect($app['r_users'], $app['pp_ary'], []);
        }

        $file = $user['PictureFile'];

        if ($file == '' || !$file)
        {
            $alert_service->error('De gebruiker heeft geen foto.');
            $link_render->redirect($app['r_users_show'], $app['pp_ary'], ['id' => $id]);
        }

        if ($request->isMethod('POST'))
        {
            $db->update($app['pp_schema'] . '.users',
                ['"PictureFile"' => ''],
                ['id' => $id]);

            $user_cache_service->clear($id, $app['pp_schema']);

            $alert_service->success('Profielfoto verwijderd.');
            $link_render->redirect($app['r_users_show'], $app['pp_ary'], ['id' => $id]);
        }

        $heading_render->add('Profielfoto ');

        if ($app['pp_admin'])
        {
            $heading_render->add('van ');
            $heading_render->add_raw($account_render->link($id, $app['pp_ary']));
            $heading_render->add(' ');
        }

        $heading_render->add('verwijderen?');

        $out = '<div class="row">';
        $out .= '<div class="col-xs-6">';
        $out .= '<div class="thumbnail">';
        $out .= '<img src="';
        $out .= $env_s3_url . $file;
        $out .= '" class="img-rounded">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<form method="post">';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= $link_render->btn_cancel($app['r_users_show'], $app['pp_ary'], ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
