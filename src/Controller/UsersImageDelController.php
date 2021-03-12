<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;

class UsersImageDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/image/del',
        name: 'users_image_del_admin',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'is_admin'      => true,
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/image/del',
        name: 'users_image_del',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'            => 0,
            'is_admin'      => false,
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        bool $is_admin,
        Db $db,
        AlertService $alert_service,
        AccountRender $account_render,
        HeadingRender $heading_render,
        LinkRender $link_render,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service,
        string $env_s3_url
    ):Response
    {
        if (!$is_admin)
        {
            $id = $su->id();
        }

        $user = $user_cache_service->get($id, $pp->schema());

        if (!$user)
        {
            $alert_service->error('De gebruiker bestaat niet.');
            $link_render->redirect($vr->get('users'), $pp->ary(), []);
        }

        $file = $user['image_file'];

        if ($file == '' || !$file)
        {
            $alert_service->error('De gebruiker heeft geen foto.');
            $link_render->redirect('users_show', $pp->ary(), ['id' => $id]);
        }

        if ($request->isMethod('POST'))
        {
            $db->update($pp->schema() . '.users',
                ['image_file' => ''],
                ['id' => $id]);

            $user_cache_service->clear($id, $pp->schema());

            $alert_service->success('Profielfoto/afbeelding verwijderd.');
            $link_render->redirect('users_show', $pp->ary(), ['id' => $id]);
        }

        $heading_render->add('Profielfoto/afbeelding ');

        if ($pp->is_admin())
        {
            $heading_render->add('van ');
            $heading_render->add_raw($account_render->link($id, $pp->ary()));
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

        $out .= $link_render->btn_cancel('users_show', $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
