<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\AccessCnst;
use App\Queue\GeocodeQueue;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use Symfony\Component\Routing\Annotation\Route;

class ContactsAddController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/contacts/add',
        name: 'contacts_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'redirect_contacts'     => true,
            'user_id'               => 0,
            'is_self'               => false,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/{user_id}/contacts/add',
        name: 'users_contacts_add',
        methods: ['GET', 'POST'],
        requirements: [
            'user_id'       => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'redirect_contacts'     => false,
            'is_self'               => false,
            'module'                => 'users',
            'sub_module'            => 'contacts',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/contacts/add',
        name: 'users_contacts_add_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
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
        bool $is_self,
        bool $redirect_contacts,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        ConfigService $config_service,
        LinkRender $link_render,
        GeocodeQueue $geocode_queue,
        ItemAccessService $item_access_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $errors = [];

        if ($is_self)
        {
            $user_id = $su->id();
        }

        $new_users_days = $config_service->get_int('users.new.days', $pp->schema());
        $new_users_enabled = $config_service->get_bool('users.new.enabled', $pp->schema());
        $leaving_users_enabled = $config_service->get_bool('users.leaving.enabled', $pp->schema());

        $show_new_status = $new_users_enabled;

        if ($show_new_status)
        {
            $new_users_access = $config_service->get_str('users.new.access', $pp->schema());
            $show_new_status = $item_access_service->is_visible($new_users_access);
        }

        $show_leaving_status = $leaving_users_enabled;

        if ($show_leaving_status)
        {
            $leaving_users_access = $config_service->get_str('users.leaving.access', $pp->schema());
            $show_leaving_status = $item_access_service->is_visible($leaving_users_access);
        }

        $account_code = $request->request->get('account_code', '');
        $id_type_contact = (int) $request->request->get('id_type_contact', '');
        $value = $request->request->get('value', '');
        $comments = $request->request->get('comments', '');
        $access = $request->request->get('access', '');

        if($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!$is_self && $redirect_contacts)
            {
               [$code] = explode(' ', trim($account_code));

                $user_id = $db->fetchOne('select id
                    from ' . $pp->schema() . '.users
                    where code = ?', [$code], [\PDO::PARAM_STR]);

                if (!$user_id)
                {
                    $errors[] = 'Ongeldige Account Code.';
                }
            }

            if (!$value)
            {
                $errors[] = 'Vul waarde in!';
            }

            if (!$access)
            {
                $errors[] = 'Vul zichtbaarheid in.';
            }

            if (!isset(AccessCnst::ACCESS[$access]))
            {
                throw new BadRequestHttpException('Invalid value for access');
            }

            $abbrev_type = $db->fetchOne('select abbrev
                from ' . $pp->schema() . '.type_contact
                where id = ?',
                [$id_type_contact], [\PDO::PARAM_INT]);

            if(!$abbrev_type)
            {
                throw new BadRequestHttpException('Ongeldig contact type!');
            }

            if ($abbrev_type === 'mail'
                && !filter_var($value, FILTER_VALIDATE_EMAIL))
            {
                $errors[] = 'Geen geldig E-mail adres';
            }

            if (strlen($value) > 130)
            {
                $errors[] = 'De waarde mag maximaal 130 tekens lang zijn.';
            }

            if (strlen($comments) > 50)
            {
                $errors[] = 'Commentaar mag maximaal 50 tekens lang zijn.';
            }

            $mail_type_id = $db->fetchOne('select id
                from ' . $pp->schema() . '.type_contact
                where abbrev = \'mail\'', [], []);

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

                    if ($mail_count == 1)
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

                $insert_ary = [
                    'id_type_contact'		=> $id_type_contact,
                    'value'					=> $value,
                    'comments' 				=> $comments,
                    'user_id'				=> $user_id,
                    'access'                => $access,
                ];

                if (!$su->is_master())
                {
                    $insert_ary['created_by'] = $su->id();
                }

                if ($db->insert($pp->schema() . '.contact', $insert_ary))
                {
                    $alert_service->success('Contact opgeslagen.');

                    if ($redirect_contacts)
                    {
                        return $this->redirectToRoute('contacts', $pp->ary());
                    }

                    return $this->redirectToRoute('users_show', array_merge($pp->ary(),
                        ['id' => $user_id]));

                }
                else
                {
                    $alert_service->error('Fout bij het opslaan');
                }
            }
            else
            {
                $alert_service->error($errors);
            }
        }

        $tc = [];

        $rs = $db->prepare('select id, name, abbrev
            from ' . $pp->schema() . '.type_contact');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $tc[$row['id']] = $row;

            if ($id_type_contact)
            {
                continue;
            }

            $id_type_contact = $row['id'];
        }

        $abbrev = $tc[$id_type_contact]['abbrev'];

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        if ($pp->is_admin() && $redirect_contacts)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="account_code" class="control-label">Voor</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon" id="fcode_addon">';
            $out .= '<span class="fa fa-user"></span></span>';
            $out .= '<input type="text" class="form-control" id="account_code" name="account_code" ';

            $out .= 'data-typeahead="';
            $out .= $typeahead_service->ini($pp->ary())
                ->add('accounts', ['status' => 'active'])
                ->add('accounts', ['status' => 'inactive'])
                ->add('accounts', ['status' => 'ip'])
                ->add('accounts', ['status' => 'im'])
                ->add('accounts', ['status' => 'extern'])
                ->str([
                    'filter'        => 'accounts',
                    'new_users_days'        => $new_users_days,
                    'show_new_status'       => $show_new_status,
                    'show_leaving_status'   => $show_leaving_status,
                ]);
            $out .= '" ';

            $out .= 'placeholder="Account Code" ';
            $out .= 'value="';
            $out .= $account_code;
            $out .= '" required>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '<div class="form-group">';
        $out .= '<label for="id_type_contact" class="control-label">Type</label>';
        $out .= '<select name="id_type_contact" id="id_type_contact" ';
        $out .= 'class="form-control" required>';

        foreach ($tc as $id_tc => $type)
        {
            $out .= '<option value="';
            $out .= $id_tc;
            $out .= '" ';
            $out .= 'data-abbrev="';
            $out .= $type['abbrev'];
            $out .= '" ';
            $out .= $id_tc === $id_type_contact ? ' selected="selected"' : '';
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
        $out .= ContactsEditController::FORMAT[$abbrev]['fa'] ?? 'circle-o';
        $out .= '"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="value" name="value" ';
        $out .= 'value="';
        $out .= $value;
        $out .= '" required disabled maxlength="130" ';
        $out .= 'data-contacts-format="';
        $out .= htmlspecialchars(json_encode(ContactsEditController::FORMAT));
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

        $out .= $item_access_service->get_radio_buttons('access', $access, 'contacts_add');

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

        $out .= '<input type="submit" value="Opslaan" ';
        $out .= 'name="zend" class="btn btn-success btn-lg">';

        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('contacts/contacts_add.html.twig', [
            'content'   => $out,
            'is_self'   => $is_self,
            'user_id'   => $user_id,
        ]);
    }
}
