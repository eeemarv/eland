<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\ContactsEditController;
use App\Render\AccountRender;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\ItemAccessService;
use App\Service\UserCacheService;

class ContactsDelController extends AbstractController
{
    public function contacts_del_admin(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        UserCacheService $user_cache_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        ItemAccessService $item_access_service,
        HeadingRender $heading_render,
        AccountRender $account_render,
        LinkRender $link_render
    ):Response
    {
        $contact = contacts_edit::get_contact($db, $id,  $app['pp_schema']);

        return self::form(
            $request,
            $contact['id_user'],
            $id,
            true,
            $db,
            $alert_service,
            $user_cache_service,
            $form_token_service,
            $menu_service,
            $item_access_service,
            $heading_render,
            $account_render,
            $link_render
        );
    }

    public static function form(
        Request $request,
        int $user_id,
        int $id,
        bool $redirect_contacts,
        Db $db,
        AlertService $alert_service,
        UserCacheService $user_cache_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        ItemAccessService $item_access_service,
        HeadingRender $heading_render,
        AccountRender $account_render,
        LinkRender $link_render
    ):Response
    {
        $contact = ContactsEditController::get_contact($db, $id,  $app['pp_schema']);

        if ($user_id !== $contact['id_user'])
        {
            throw new BadRequestHttpException(
                'Contact ' . $id . ' behoort niet tot gebruiker ' . $user_id);
        }

        if ($request->isMethod('GET'))
        {
            if ($contact['abbrev'] === 'mail'
                && $user_cache_service->is_active_user($user_id, $app['pp_schema']))
            {
                $count_mail = $db->fetchColumn('select count(c.*)
                    from ' . $app['pp_schema'] . '.contact c
                    where c.id_type_contact = ?
                        and c.id_user = ?', [
                            $contact['id_type_contact'],
                            $user_id]);

                if ($count_mail === 1)
                {
                    if ($app['pp_admin'])
                    {
                        $alert_service->warning(
                            'Waarschuwing: dit is het enige E-mail adres
                            van een actieve gebruiker');
                    }
                    else
                    {
                        $alert_service->warning(
                            'Waarschuwing: dit is je enige E-mail adres.');
                    }
                }
            }
        }

        if ($request->isMethod('POST'))
        {
            $errors = [];

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $db->delete($app['pp_schema'] . '.contact', ['id' => $id]);

                $alert_service->success('Contact verwijderd.');

                if ($redirect_contacts)
                {
                    $link_render->redirect('contacts', $app['pp_ary'], []);
                }
                else
                {
                    $link_render->redirect('users_show', $app['pp_ary'],
                        ['id' => $user_id]);
                }
            }

            $alert_service->error($error_token);
        }

        if ($app['pp_admin'])
        {
            $heading_render->add('Contact verwijderen voor ');
            $heading_render->add_raw($account_render->link($user_id, $app['pp_ary']));
            $heading_render->add('?');
        }
        else
        {
            $heading_render->add('Contact verwijderen?');
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl>';

        $out .= '<dt>Gebruiker</dt>';
        $out .= '<dd>';

        $out .= $account_render->link($user_id, $app['pp_ary']);

        $out .= '</dd>';

        $out .= '<dt>Type</dt>';
        $out .= '<dd>';
        $out .= $contact['abbrev'];
        $out .= '</dd>';
        $out .= '<dt>Waarde</dt>';
        $out .= '<dd>';
        $out .= $contact['value'];
        $out .= '</dd>';
        $out .= '<dt>Commentaar</dt>';
        $out .= '<dd>';
        $out .= $contact['comments'] ?: '<i class="fa fa-times"></i>';
        $out .= '</dd>';
        $out .= '<dt>Zichtbaarheid</dt>';
        $out .= '<dd>';

        $out .= $item_access_service->get_label($contact['access']);

        $out .= '</dd>';
        $out .= '</dl>';

        $out .= '<form method="post" class="form-horizontal">';

        if ($redirect_contacts)
        {
            $out .= $link_render->btn_cancel('contacts', $app['pp_ary'], []);
        }
        else
        {
            $out .= $link_render->btn_cancel('users_show', $app['pp_ary'],
                ['id' => $user_id]);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set($redirect_contacts ? 'contacts' : 'users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }
}
