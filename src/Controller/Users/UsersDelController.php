<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cnst\BulkCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Cnst\StatusCnst;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\ThumbprintAccountsService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;

class UsersDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/del',
        name: 'users_del',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        FormTokenService $form_token_service,
        AlertService $alert_service,
        AccountRender $account_render,
        LinkRender $link_render,
        ThumbprintAccountsService $thumbprint_accounts_service,
        UserCacheService $user_cache_service,
        IntersystemsService $intersystems_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service
    ):Response
    {
        $errors = [];

        if ($su->id() === $id)
        {
            throw new AccessDeniedHttpException(
                'You can not remove your own user account.');
        }

        if ($db->fetchOne('select id
            from ' . $pp->schema() . '.transactions
            where id_to = ? or id_from = ?',
            [$id, $id], [\PDO::PARAM_INT, \PDO::PARAM_INT]))
        {
            throw new AccessDeniedHttpException('Een gebruiker met transacties
                kan niet worden verwijderd.');
        }

        $user = $user_cache_service->get($id, $pp->schema());

        if (!$user)
        {
            throw new NotFoundHttpException(
                'De gebruiker met id ' . $id . ' bestaat niet.');
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            $verify = $request->request->get('verify', '') ? true : false;

            if (!$verify)
            {
                $errors[] = 'Het controle nazichts-vakje
                    is niet aangevinkt.';
            }

            if (count($errors))
            {
                $alert_service->error($errors);
            }
            else
            {
                $this->remove_user(
                    $id,
                    $db,
                    $alert_service,
                    $intersystems_service,
                    $thumbprint_accounts_service,
                    $user_cache_service,
                    $pp
                );

                $status = StatusCnst::THUMBPINT_ARY[$user['status']];

                $link_render->redirect($vr->get('users'), $pp->ary(),
                    ['status' => $status]);
            }
        }

        $out = '<p><font color="red">Alle Gegevens, Vraag en aanbod, ';
        $out .= 'Contacten en Afbeeldingen van ';
        $out .= $account_render->link($id, $pp->ary());
        $out .= ' worden verwijderd.</font></p>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post"">';

        $verify_lbl = 'Ik ben wis en waarachtig zeker dat ';
        $verify_lbl .= 'ik deze gebruiker wil verwijderen.';

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'        => 'verify',
            '%label%'       => $verify_lbl,
            '%attr%'        => '',
        ]);

        $out .= $link_render->btn_cancel('users_show',
            $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('users');

        return $this->render('users/users_del.html.twig', [
            'content'   => $out,
            'id'        => $id,
        ]);
    }

    private function remove_user(
        int $id,
        Db $db,
        AlertService $alert_service,
        IntersystemsService $intersystems_service,
        ThumbprintAccountsService $thumbprint_accounts_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp
    ):void
    {
        //delete contacts

        $db->delete($pp->schema() . '.contact',
            ['user_id' => $id]);

        //the user

        $db->delete($pp->schema() . '.users',
            ['id' => $id]);

        $user_cache_service->clear($id, $pp->schema());

        $alert_service->success('De gebruiker is verwijderd.');

        $thumbprint_accounts_service->delete($pp->ary(), $pp->schema());
        $intersystems_service->clear_cache();
    }
}
