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
use App\Service\ThumbprintAccountsService;
use App\Service\UserCacheService;
use App\Service\XdbService;
use Doctrine\DBAL\Connection as Db;
use Predis\Client as Predis;
use Psr\Log\LoggerInterface;

class UsersDelAdminController extends AbstractController
{
    public function users_del_admin(
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
        MenuService $menu_service
    ):Response
    {
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
            $errors = [];

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
                    $xdb_service,
                    $intersystems_service,
                    $thumbprint_accounts_service,
                    $user_cache_service
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
        XdbService $xdb_service,
        IntersystemsService $intersystems_service,
        ThumbprintAccountsService $thumbprint_accounts_service,
        UserCacheService $user_cache_service
    ):void
    {
        $user = $user_cache_service->get($id, $pp->schema());

        // remove messages

        $usr = $user['letscode'] . ' ' . $user['name'] . ' [id:' . $id . ']';
        $msgs = '';
        $st = $db->prepare('select id, content,
                id_category, msg_type
            from ' . $pp->schema() . '.messages
            where id_user = ?');

        $st->bindValue(1, $id);
        $st->execute();

        while ($row = $st->fetch())
        {
            $msgs .= $row['id'] . ': ' . $row['content'] . ', ';
        }
        $msgs = trim($msgs, '\n\r\t ,;:');

        if ($msgs)
        {
            $logger->info('Delete user ' . $usr .
                ', deleted Messages ' . $msgs,
                ['schema' => $pp->schema()]);

            $db->delete($pp->schema() . '.messages',
                ['id_user' => $id]);
        }

        // remove orphaned images.

        $rs = $db->prepare('select mp.id, mp."PictureFile"
            from ' . $pp->schema() . '.msgpictures mp
                left join ' . $pp->schema() . '.messages m on mp.msgid = m.id
            where m.id is null');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $db->delete($pp->schema() . '.msgpictures', ['id' => $row['id']]);
        }

        // update counts for each category

        $offer_count = $want_count = [];

        $rs = $db->prepare('select m.id_category, count(m.*)
            from ' . $pp->schema() . '.messages m, ' .
                $pp->schema() . '.users u
            where  m.id_user = u.id
                and u.status IN (1, 2, 3)
                and msg_type = 1
            group by m.id_category');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $offer_count[$row['id_category']] = $row['count'];
        }

        $rs = $db->prepare('select m.id_category, count(m.*)
            from ' . $pp->schema() . '.messages m, ' .
                $pp->schema() . '.users u
            where m.id_user = u.id
                and u.status IN (1, 2, 3)
                and msg_type = 0
            group by m.id_category');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $want_count[$row['id_category']] = $row['count'];
        }

        $all_cat = $db->fetchAll('select id,
                stat_msgs_offers, stat_msgs_wanted
            from ' . $pp->schema() . '.categories
            where id_parent is not null');

        foreach ($all_cat as $val)
        {
            $offers = $val['stat_msgs_offers'];
            $wants = $val['stat_msgs_wanted'];
            $cat_id = $val['id'];

            $want_count[$cat_id] = $want_count[$cat_id] ?? 0;
            $offer_count[$cat_id] = $offer_count[$cat_id] ?? 0;

            if ($want_count[$cat_id] == $wants && $offer_count[$cat_id] == $offers)
            {
                continue;
            }

            $stats = [
                'stat_msgs_offers'	=> $offer_count[$cat_id] ?? 0,
                'stat_msgs_wanted'	=> $want_count[$cat_id] ?? 0,
            ];

            $db->update($pp->schema() . '.categories',
                $stats,
                ['id' => $cat_id]);
        }

        //delete contacts

        $db->delete($pp->schema() . '.contact',
            ['id_user' => $id]);

        //delete fullname access record.

        $xdb_service->del('user_fullname_access', (string) $id, $pp->schema());

        //finally, the user

        $db->delete($pp->schema() . '.users',
            ['id' => $id]);

        $user_cache_service->clear($id, $pp->schema());

        $alert_service->success('De gebruiker is verwijderd.');

        $thumbprint_status = StatusCnst::THUMBPINT_ARY[$user['status']];
        $thumbprint_accounts_service->delete($thumbprint_status, $pp->ary(), $pp->schema());

        $intersystems_service->clear_cache($pp->schema());
    }
}
