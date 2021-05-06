<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use App\Queue\GeocodeQueue;
use App\Render\AccountRender;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AssetsService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\Routing\Annotation\Route;

class ContactsEditController extends AbstractController
{
    const FORMAT = [
        'adr'	=> [
            'fa'		=> 'map-marker',
            'lbl'		=> 'Adres',
            'explain'	=> 'Voorbeeldstraat 23, 4520 Voorbeeldgemeente',
        ],
        'gsm'	=> [
            'fa'		=> 'mobile',
            'lbl'		=> 'GSM',
        ],
        'tel'	=> [
            'fa'		=> 'phone',
            'lbl'		=> 'Telefoon',
        ],
        'mail'	=> [
            'fa'		=> 'envelope-o',
            'lbl'		=> 'E-mail',
            'type'		=> 'email',
        ],
        'web'	=> [
            'fa'		=> 'link',
            'lbl'		=> 'Website',
            'type'		=> 'url',
        ],
    ];

    #[Route(
        '/{system}/{role_short}/contacts/{id}/edit',
        name: 'contacts_edit_admin',
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
            'is_admin'              => true,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/{user_id}/contacts/{contact_id}/edit',
        name: 'users_contacts_edit_admin',
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
            'is_admin'              => true,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/contacts/{contact_id}/edit',
        name: 'users_contacts_edit',
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
            'is_admin'              => false,
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
        bool $is_admin,
        Db $db,
        FormTokenService $form_token_service,
        AlertService $alert_service,
        AssetsService $assets_service,
        MenuService $menu_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        HeadingRender $heading_render,
        AccountRender $account_render,
        PageParamsService $pp,
        SessionUserService $su,
        GeocodeQueue $geocode_queue
    ):Response
    {
        $errors = [];

        $id = $contact_id ?: $id;

        $contact = self::get_contact($db, $id, $pp->schema());

        if (!$is_admin)
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

        $id_type_contact = $contact['id_type_contact'];
        $value = $contact['value'];
        $comments = $contact['comments'];
        $access = $contact['access'];

        if($request->isMethod('POST'))
        {
            $id_type_contact = (int) $request->request->get('id_type_contact', '');
            $value = $request->request->get('value', '');
            $comments = $request->request->get('comments', '');
            $access = $request->request->get('access', '');

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in!';
            }

            $abbrev_type = $db->fetchOne('select abbrev
                from ' . $pp->schema() . '.type_contact
                where id = ?', [$id_type_contact], [\PDO::PARAM_INT]);

            if ($abbrev_type === 'mail'
                && !filter_var($value, FILTER_VALIDATE_EMAIL))
            {
                $errors[] = 'Geen geldig E-mail adres';
            }

            if (!$value)
            {
                $errors[] = 'Vul waarde in!';
            }

            if (strlen($value) > 130)
            {
                $errors[] = 'De waarde mag maximaal 130 tekens lang zijn.';
            }

            if (strlen($comments) > 50)
            {
                $errors[] = 'Commentaar mag maximaal 50 tekens lang zijn.';
            }

            if(!$abbrev_type)
            {
                $errors[] = 'Contact type bestaat niet!';
            }

            $mail_type_id = $db->fetchOne('select id
                from ' . $pp->schema() . '.type_contact
                where abbrev = \'mail\'', [], []);

            $count_mail = $db->fetchOne('select count(*)
                from ' . $pp->schema() . '.contact
                where user_id = ?
                    and id_type_contact = ?',
                [$user_id, $mail_type_id],
                [\PDO::PARAM_INT, \PDO::PARAM_INT]
            );

            $mail_id = $db->fetchOne('select id
                from ' . $pp->schema() . '.contact
                where user_id = ?
                    and id_type_contact = ?',
                [$user_id, $mail_type_id],
                [\PDO::PARAM_INT, \PDO::PARAM_INT]
            );

            if ($id === $mail_id
                && $count_mail === 1
                && $id_type_contact !== $mail_type_id)
            {
                $alert_service->warning('Waarschuwing: de gebruiker heeft
                    geen E-mail adres.');
            }

            if ($id_type_contact === $mail_type_id)
            {
                $mailadr = $value;

                $mail_count = $db->fetchOne('select count(c.*)
                    from ' . $pp->schema() . '.contact c, ' .
                        $pp->schema() . '.type_contact tc, ' .
                        $pp->schema() . '.users u
                    where c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'
                        and c.user_id = u.id
                        and u.status in (1, 2)
                        and u.id <> ?
                        and c.value = ?',
                        [$user_id, $mailadr],
                        [\PDO::PARAM_INT, \PDO::PARAM_STR]
                    );

                if ($mail_count && $pp->is_admin())
                {
                    $warning = 'Omdat deze gebruikers niet meer ';
                    $warning .= 'een uniek E-mail adres hebben zullen zij ';
                    $warning .= 'niet meer zelf hun paswoord kunnnen resetten ';
                    $warning .= 'of kunnen inloggen met ';
                    $warning .= 'E-mail adres.';

                    if ($mail_count === 1)
                    {
                        $warning_2 = 'Waarschuwing: E-mail adres ' . $mailadr;
                        $warning_2 .= ' bestaat al onder de actieve gebruikers.';
                    }
                    else if ($mail_count > 1)
                    {
                        $warning_2 = 'Waarschuwing: E-mail adres ' . $mailadr;
                        $warning_2 .= ' bestaat al ' . $mail_count;
                        $warning_2 .= ' maal onder de actieve gebruikers.';
                    }

                    $alert_service->warning($warning_2 . ' ' . $warning);
                }
                else if ($mail_count)
                {
                    $errors[] = 'Dit E-mail adres komt reeds voor onder
                        de actieve gebruikers.';
                }
            }

            $update_ary = [
                'id_type_contact'   => $id_type_contact,
                'value'             => $value,
                'comments'          => $comments,
                'access'            => $access,
            ];

            if(!count($errors))
            {
                if ($abbrev_type === 'adr')
                {
                    $geocode_queue->cond_queue([
                        'adr'		=> $value,
                        'uid'		=> $user_id,
                        'schema'	=> $pp->schema(),
                    ], 0);
                }

                $db->update($pp->schema() . '.contact',
                    $update_ary, ['id' => $id]);

                $alert_service->success('Contact aangepast.');

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

            $alert_service->error($errors);
        }

        $type_contact_ary = [];

        $rs = $db->prepare('select id, name, abbrev
            from ' . $pp->schema() . '.type_contact');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $type_contact_ary[$row['id']] = $row;
        }

        $assets_service->add(['contacts_edit.js']);

        $abbrev = $type_contact_ary[$id_type_contact]['abbrev'];

        $heading_render->add('Contact aanpassen');

        if ($pp->is_admin())
        {
            $heading_render->add(' voor ');
            $heading_render->add_raw($account_render->link($user_id, $pp->ary()));
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="id_type_contact" class="control-label">Type</label>';
        $out .= '<select name="id_type_contact" id="id_type_contact" ';
        $out .= 'class="form-control" required>';

        foreach ($type_contact_ary as $tc_id => $type)
        {
            $out .= '<option value="';
            $out .= $tc_id;
            $out .= '" ';
            $out .= 'data-abbrev="';
            $out .= $type['abbrev'];
            $out .= '" ';
            $out .= $tc_id === $id_type_contact ? ' selected="selected"' : '';
            $out .= '>';
            $out .= $type['name'];
            $out .= '</option>';
        }

        $out .= "</select>";
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="value" class="control-label">';
        $out .= 'Waarde</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon" id="value_addon">';
        $out .= '<i class="fa fa-';
        $out .= self::FORMAT[$abbrev]['fa'] ?? 'circle-o';
        $out .= '"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="value" name="value" ';
        $out .= 'value="';
        $out .= $value;
        $out .= '" required disabled maxlength="130" ';
        $out .= 'data-contacts-format="';
        $out .= htmlspecialchars(json_encode(self::FORMAT));
        $out .= '">';
        $out .= '</div>';
        $out .= '<p id="contact-explain">';

        $out .= '</p>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="comments" class="control-label">';
        $out .= 'Commentaar</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-comment-o"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="comments" name="comments" ';
        $out .= 'value="';
        $out .= $comments;
        $out .= '" maxlength="50">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $item_access_service->get_radio_buttons('access', $access);

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

        $out .= '<input type="submit" value="Aanpassen" ';
        $out .= 'name="zend" class="btn btn-primary btn-lg">';

        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set($redirect_contacts ? 'contacts' : 'users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    public static function get_contact(
        Db $db,
        int $contact_id,
        string $schema
    ):array
    {
        $contact = $db->fetchAssociative('select c.*, tc.abbrev
            from ' . $schema . '.contact c,
                ' . $schema . '.type_contact tc
            where c.id = ?
                and tc.id = c.id_type_contact',
            [$contact_id], [\PDO::PARAM_INT]);

        if (!$contact)
        {
            throw new NotFoundHttpException(
                'Het contact met id ' . $contact_id . ' bestaat niet.');
        }

        return $contact;
    }
}
