<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Render\AccountRender;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Render\LinkRender;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Component\Routing\Annotation\Route;

class ContactsDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/contacts/{id}/del',
        name: 'contacts_del_admin',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'user_id'               => 0,
            'contact_id'            => 0,
            'redirect_contacts'     => true,
            'is_self'               => false,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/{user_id}/contacts/{contact_id}/del',
        name: 'users_contacts_del_admin',
        methods: ['GET', 'POST'],
        requirements: [
            'user_id'       => '%assert.id%',
            'contact_id'    => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'id'                    => 0,
            'redirect_contacts'     => false,
            'is_self'               => false,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/contacts/{contact_id}/del',
        name: 'users_contacts_del',
        methods: ['GET', 'POST'],
        requirements: [
            'contact_id'    => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'                    => 0,
            'user_id'               => 0,
            'redirect_contacts'     => false,
            'is_self'               => true,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    public function __invoke(
        Request $request,
        int $user_id,
        int $contact_id,
        int $id,
        bool $redirect_contacts,
        bool $is_self,
        Db $db,
        AlertService $alert_service,
        UserCacheService $user_cache_service,
        FormTokenService $form_token_service,
        ItemAccessService $item_access_service,
        AccountRender $account_render,
        PageParamsService $pp,
        SessionUserService $su,
        LinkRender $link_render
    ):Response
    {
        $errors = [];

        $id = $contact_id ?: $id;

        $contact = ContactsEditController::get_contact($db, $id,  $pp->schema());

        if ($is_self)
        {
            $user_id = $su->id();
        }
        else if ($redirect_contacts)
        {
            $user_id = $contact['user_id'];
        }

        if ($user_id !== $contact['user_id'])
        {
            throw new BadRequestHttpException(
                'Contact ' . $id . ' does not belong to user ' . $user_id);
        }

        if (!$user_id)
        {
            throw new BadRequestHttpException('No user_id');
        }

        if ($request->isMethod('GET'))
        {
            if ($contact['abbrev'] === 'mail'
                && $user_cache_service->is_active_user($user_id, $pp->schema()))
            {
                $count_mail = $db->fetchOne('select count(c.*)
                    from ' . $pp->schema() . '.contact c
                    where c.id_type_contact = ?
                        and c.user_id = ?', [
                            $contact['id_type_contact'],
                            $user_id],
                            [\PDO::PARAM_INT, \PDO::PARAM_INT]);

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
                    $this->redirectToRoute('contacts', $pp->ary());
                }
                else
                {
                    $this->redirectToRoute('users_show', array_merge($pp->ary(),
                        ['id' => $user_id]));
                }
            }

            $alert_service->error($error_token);
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

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

        return $this->render('contacts/contacts_del.html.twig', [
            'content'   => $out,
            'is_self'   => $is_self,
            'user_id'   => $user_id,
        ]);
    }
}
