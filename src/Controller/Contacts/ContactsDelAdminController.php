<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
use App\Service\PageParamsService;
use App\Service\UserCacheService;

class ContactsDelAdminController extends AbstractController
{
    public function __invoke(
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
        PageParamsService $pp,
        LinkRender $link_render
    ):Response
    {
        $contact = ContactsEditAdminController::get_contact($db, $id,  $pp->schema());

        $content = self::form(
            $request,
            $contact['user_id'],
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
            $pp,
            $link_render
        );

        return $this->render('contacts/contacts_del_admin.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
        ]);
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
        PageParamsService $pp,
        LinkRender $link_render
    ):string
    {
        $errors = [];

        $contact = ContactsEditAdminController::get_contact($db, $id,  $pp->schema());

        if ($user_id !== $contact['user_id'])
        {
            throw new BadRequestHttpException(
                'Contact ' . $id . ' behoort niet tot gebruiker ' . $user_id);
        }

        if ($request->isMethod('GET'))
        {
            if ($contact['abbrev'] === 'mail'
                && $user_cache_service->is_active_user($user_id, $pp->schema()))
            {
                $count_mail = $db->fetchColumn('select count(c.*)
                    from ' . $pp->schema() . '.contact c
                    where c.id_type_contact = ?
                        and c.user_id = ?', [
                            $contact['id_type_contact'],
                            $user_id]);

                if ($count_mail === 1)
                {
                    if ($pp->is_admin())
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
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $db->delete($pp->schema() . '.contact', ['id' => $id]);

                $alert_service->success('Contact verwijderd.');

                if ($redirect_contacts)
                {
                    $link_render->redirect('contacts', $pp->ary(), []);
                }
                else
                {
                    $link_render->redirect('users_show', $pp->ary(),
                        ['id' => $user_id]);
                }
            }

            $alert_service->error($error_token);
        }

        if ($pp->is_admin())
        {
            $heading_render->add('Contact verwijderen voor ');
            $heading_render->add_raw($account_render->link($user_id, $pp->ary()));
            $heading_render->add('?');
        }
        else
        {
            $heading_render->add('Contact verwijderen?');
        }

        $out = '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';

        $out .= '<dl>';

        $out .= '<dt>Gebruiker</dt>';
        $out .= '<dd>';

        $out .= $account_render->link($user_id, $pp->ary());

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
            $out .= $link_render->btn_cancel('contacts', $pp->ary(), []);
        }
        else
        {
            $out .= $link_render->btn_cancel('users_show', $pp->ary(),
                ['id' => $user_id]);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set($redirect_contacts ? 'contacts' : 'users');

        return $out;
    }
}