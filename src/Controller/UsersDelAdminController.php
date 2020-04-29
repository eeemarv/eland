<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Cnst\StatusCnst;
use App\Render\AccountRender;
use App\Render\HeadingRender;
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
use App\Service\XdbService;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class UsersDelAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        LoggerInterface $logger,
        FormTokenService $form_token_service,
        AlertService $alert_service,
        AccountRender $account_render,
        HeadingRender $heading_render,
        LinkRender $link_render,
        ThumbprintAccountsService $thumbprint_accounts_service,
        UserCacheService $user_cache_service,
        IntersystemsService $intersystems_service,
        XdbService $xdb_service,
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
                'Je kan je eigen account niet verwijderen.');
        }

        if ($db->fetchColumn('select id
            from ' . $pp->schema() . '.transactions
            where id_to = ? or id_from = ?', [$id, $id]))
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
                    $logger,
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

        $heading_render->add('Gebruiker ');
        $heading_render->add_raw($account_render->link($id, $pp->ary()));
        $heading_render->add(' verwijderen?');
        $heading_render->fa('user');

        $out = '<p><font color="red">Alle Gegevens, Vraag en aanbod, ';
        $out .= 'Contacten en Afbeeldingen van ';
        $out .= $account_render->link($id, $pp->ary());
        $out .= ' worden verwijderd.</font></p>';

        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post"">';

        $out .= '<div class="form-group">';
        $out .= '<label for="id_verify">';
        $out .= '<input type="checkbox" name="verify"';
        $out .= ' value="1" id="id_verify"> ';
        $out .= ' Ik ben wis en waarachtig zeker dat ';
        $out .= 'ik deze gebruiker wil verwijderen.';
        $out .= '</label>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel($vr->get('users_show'),
            $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    private function remove_user(
        int $id,
        Db $db,
        LoggerInterface $logger,
        AlertService $alert_service,
        IntersystemsService $intersystems_service,
        ThumbprintAccountsService $thumbprint_accounts_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp
    ):void
    {
        $user = $user_cache_service->get($id, $pp->schema());

        // remove messages

        $usr = $user['code'] . ' ' . $user['name'] . ' [id:' . $id . ']';
        $msgs = '';

        $st = $db->prepare('select id, subject
            from ' . $pp->schema() . '.messages
            where user_id = ?');

        $st->bindValue(1, $id);
        $st->execute();

        while ($row = $st->fetch())
        {
            $msgs .= $row['id'] . ': ' . $row['subject'] . ', ';
        }

        $msgs = trim($msgs, '\n\r\t ,;:');

        if ($msgs)
        {
            $logger->info('Delete user ' . $usr .
                ', deleted Messages ' . $msgs,
                ['schema' => $pp->schema()]);

            $db->delete($pp->schema() . '.messages',
                ['user_id' => $id]);
        }

        //delete contacts

        $db->delete($pp->schema() . '.contact',
            ['user_id' => $id]);

        //the user

        $db->delete($pp->schema() . '.users',
            ['id' => $id]);

        $user_cache_service->clear($id, $pp->schema());

        $alert_service->success('De gebruiker is verwijderd.');

        $thumbprint_status = StatusCnst::THUMBPINT_ARY[$user['status']];
        $thumbprint_accounts_service->delete($thumbprint_status, $pp->ary(), $pp->schema());

        $intersystems_service->clear_cache($pp->schema());
    }
}
