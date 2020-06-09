<?php declare(strict_types=1);

namespace App\Controller\Users;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\StatusCnst;
use App\Cnst\RoleCnst;
use App\Cnst\ContactInputCnst;
use App\Queue\GeocodeQueue;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Security\User;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\PasswordStrengthService;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class UsersEditAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        EncoderFactoryInterface $encoder_factory,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        GeocodeQueue $geocode_queue,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PasswordStrengthService $password_strength_service,
        SelectRender $select_render,
        SystemsService $systems_service,
        TypeaheadService $typeahead_service,
        UserCacheService $user_cache_service,
        MailAddrUserService $mail_addr_user_service,
        MailAddrSystemService $mail_addr_system_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service
    ):Response
    {
        $content = self::form(
            $request,
            $id,
            true,
            $db,
            $encoder_factory,
            $account_render,
            $alert_service,
            $assets_service,
            $config_service,
            $date_format_service,
            $form_token_service,
            $geocode_queue,
            $heading_render,
            $intersystems_service,
            $item_access_service,
            $link_render,
            $password_strength_service,
            $select_render,
            $systems_service,
            $typeahead_service,
            $user_cache_service,
            $mail_addr_user_service,
            $mail_addr_system_service,
            $mail_queue,
            $pp,
            $su,
            $vr,
            $menu_service
        );

        return $this->render('users/users_edit.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
        ]);
    }

    public static function form(
        Request $request,
        int $id,
        bool $is_edit,
        Db $db,
        EncoderFactoryInterface $encoder_factory,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        GeocodeQueue $geocode_queue,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PasswordStrengthService $password_strength_service,
        SelectRender $select_render,
        SystemsService $systems_service,
        TypeaheadService $typeahead_service,
        UserCacheService $user_cache_service,
        MailAddrUserService $mail_addr_user_service,
        MailAddrSystemService $mail_addr_system_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service
    ):string
    {
        $is_add = !$is_edit;
        $errors = [];
        $contact = [];
        $username_edit_en = false;
        $fullname_edit_en = false;
        $is_activated = false;
        $mailenabled = $config_service->get('mailenabled', $pp->schema());

        if ($is_edit)
        {
            $stored_user = $user_cache_service->get($id, $pp->schema());
            $stored_code = $stored_user['code'];
            $stored_name = $stored_user['name'];
            $is_activated = isset($stored_user['adate']);
        }

        $intersystem_code = $request->query->get('intersystem_code', '');

        $code = trim($request->request->get('code', ''));
        $name = trim($request->request->get('name', ''));
        $fullname = trim($request->request->get('fullname', ''));
        $fullname_access = $request->request->get('fullname_access', '');
        $postcode = trim($request->request->get('postcode', ''));
        $birthday = trim($request->request->get('birthday', ''));
        $hobbies = trim($request->request->get('hobbies', ''));
        $comments = trim($request->request->get('comments', ''));
        $role = $request->request->get('role', '');
        $status = $request->request->get('status', '');
        $password = trim($request->request->get('password', ''));
        $password_notify = $request->request->has('password_notify');
        $admincomment = trim($request->request->get('admincomment', ''));
        $minlimit = trim($request->request->get('minlimit', ''));
        $maxlimit = trim($request->request->get('maxlimit', ''));
        $periodic_overview_en = $request->request->has('periodic_overview_en');
        $contact = $request->request->get('contact', []);

        $is_owner = $is_edit
            && $su->is_owner($id);

        if ($pp->is_admin())
        {
            $username_edit_en = $fullname_edit_en = true;
        }
        else if ($is_owner)
        {
            $username_edit_en = $config_service->get('users_can_edit_username', $pp->schema()) ? true : false;
            $fullname_edit_en = $config_service->get('users_can_edit_fullname', $pp->schema()) ? true : false;
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if ($pp->is_admin())
            {
                $mail_unique_check_sql = 'select count(c.value)
                    from ' . $pp->schema() . '.contact c, ' .
                        $pp->schema() . '.type_contact tc, ' .
                        $pp->schema() . '.users u
                    where c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'
                        and c.value = ?
                        and c.user_id = u.id
                        and u.status in (1, 2)';

                if ($is_edit)
                {
                    $mail_unique_check_sql .= ' and u.id <> ?';
                }

                $mailadr = false;

                $st = $db->prepare($mail_unique_check_sql);

                foreach ($contact as $key => $c)
                {
                    if ($c['value'] && !isset($c['access']))
                    {
                        $errors[] = 'Vul een zichtbaarheid in.';
                        continue;
                    }
                }

                foreach ($contact as $key => $c)
                {
                    if ($c['abbrev'] == 'mail')
                    {
                        $mailadr = trim($c['value']);

                        if ($mailadr)
                        {
                            if (!filter_var($mailadr, FILTER_VALIDATE_EMAIL))
                            {
                                $errors[] =  $mailadr . ' is geen geldig email adres.';
                            }

                            $st->bindValue(1, $mailadr);

                            if ($is_edit)
                            {
                                $st->bindValue(2, $id);
                            }

                            $st->execute();

                            $row = $st->fetch();

                            $warning = 'Omdat deze gebruikers niet meer een uniek E-mail adres hebben zullen zij ';
                            $warning .= 'niet meer zelf hun paswoord kunnnen resetten of kunnen inloggen met ';
                            $warning .= 'E-mail adres. Zie ';
                            $warning .= $link_render->link_no_attr('status', $pp->ary(), [], 'Status');

                            $warning_2 = '';

                            if ($row['count'] == 1)
                            {
                                $warning_2 .= 'Waarschuwing: E-mail adres ' . $mailadr;
                                $warning_2 .= ' bestaat al onder de actieve gebruikers. ';
                            }
                            else if ($row['count'] > 1)
                            {
                                $warning_2 .= 'Waarschuwing: E-mail adres ' . $mailadr;
                                $warning_2 .= ' bestaat al ' . $row['count'];
                                $warning_2 .= ' maal onder de actieve gebruikers. ';
                            }

                            if ($warning_2)
                            {
                                $alert_service->warning($warning_2 . $warning);
                            }
                        }
                    }
                }

                if ($status === '1' || $status === '2')
                {
                    if (!$mailadr)
                    {
                        $err = 'Waarschuwing: Geen E-mail adres ingevuld. ';
                        $err .= 'De gebruiker kan geen berichten en notificaties ';
                        $err .= 'ontvangen en zijn/haar paswoord niet resetten.';
                        $alert_service->warning($err);
                    }
                }
            }

            $code_sql = 'select code
                from ' . $pp->schema() . '.users
                where code = ?';
            $code_sql_params = [$code];

            $name_sql = 'select name
                from ' . $pp->schema() . '.users
                where name = ?';
            $name_sql_params = [$name];

            $fullname_sql = 'select fullname
                from ' . $pp->schema() . '.users
                where fullname = ?';
            $fullname_sql_params = [$fullname];

            if ($is_edit)
            {
                $code_sql .= ' and id <> ?';
                $code_sql_params[] = $id;
                $name_sql .= 'and id <> ?';
                $name_sql_params[] = $id;
                $fullname_sql .= 'and id <> ?';
                $fullname_sql_params[] = $id;
            }

            if (!$fullname_access)
            {
                $errors[] = 'Vul een zichtbaarheid in voor de volledige naam.';
            }

            if ($username_edit_en)
            {
                if (!$name)
                {
                    $errors[] = 'Vul gebruikersnaam in!';
                }
                else if ($db->fetchColumn($name_sql, $name_sql_params))
                {
                    $errors[] = 'Deze gebruikersnaam is al in gebruik!';
                }
                else if (strlen($name) > 50)
                {
                    $errors[] = 'De gebruikersnaam mag maximaal 50 tekens lang zijn.';
                }
            }

            if ($fullname_edit_en)
            {
                if (!$fullname)
                {
                    $errors[] = 'Vul de Volledige Naam in!';
                }
                else if ($db->fetchColumn($fullname_sql, $fullname_sql_params))
                {
                    $errors[] = 'Deze Volledige Naam is al in gebruik!';
                }
                else if (strlen($fullname) > 100)
                {
                    $errors[] = 'De Volledige Naam mag maximaal 100 tekens lang zijn.';
                }
            }

            if ($pp->is_admin())
            {
                if (!$code)
                {
                    $errors[] = 'Vul een Account Code in!';
                }
                else if ($db->fetchColumn($code_sql, $code_sql_params))
                {
                    $errors[] = 'De Account Code bestaat al!';
                }
                else if (strlen($code) > 20)
                {
                    $errors[] = 'De Account Code mag maximaal
                        20 tekens lang zijn.';
                }

                if (!preg_match("/^[A-Za-z0-9-]+$/", $code))
                {
                    $errors[] = 'De Account Code kan enkel uit
                        letters, cijfers en koppeltekens bestaan.';
                }

                if ($minlimit
                    && filter_var($minlimit, FILTER_VALIDATE_INT) === false)
                {
                    $errors[] = 'Geef getal of niets op voor de
                        Minimum Account Limiet.';
                }

                if ($maxlimit
                    && filter_var($maxlimit, FILTER_VALIDATE_INT) === false)
                {
                    $errors[] = 'Geef getal of niets op voor de
                        Maximum Account Limiet.';
                }
            }

            if ($birthday)
            {
                $birthday = $date_format_service->reverse($birthday, $pp->schema());

                if ($birthday === '')
                {
                    $errors[] = 'Fout in formaat geboortedag.';
                }
            }

            if (strlen($comments) > 100)
            {
                $errors[] = 'Het veld Commentaar mag maximaal
                    100 tekens lang zijn.';
            }

            if (strlen($postcode) > 6)
            {
                $errors[] = 'De postcode mag maximaal 6 tekens lang zijn.';
            }

            if (strlen($hobbies) > 500)
            {
                $errors[] = 'Het veld hobbies en interesses mag
                    maximaal 500 tekens lang zijn.';
            }

            if ($pp->is_admin()
                && !$is_activated
                && $status === '1')
            {
                if (!$password)
                {
                    $errors[] = 'Gelieve een Paswoord in te vullen.';
                }
                else if (!$password_strength_service->get($password))
                {
                    $errors[] = 'Het Paswoord is niet sterk genoeg.';
                }
            }

            if (!count($errors))
            {
                $post_user = [
                    'fullname_access'       => $fullname_access,
                    'postcode'		        => $postcode,
                    'birthday'		        => $birthday === '' ? null : $birthday,
                    'hobbies'		        => $hobbies,
                    'comments'		        => $comments,
                    'periodic_overview_en'  => $periodic_overview_en ? 1 : 0,
                ];

                if ($is_add && !$su->is_master())
                {
                    $transaction['created_by'] = $su->id();
                }

                if (($is_add || ($is_edit && !$is_activated))
                    && $status === '1')
                {
                    $post_user['adate'] = gmdate('Y-m-d H:i:s');

                    $encoder = $encoder_factory->getEncoder(new User());
                    $post_user['password'] = $encoder->encodePassword($password, null);
                }
                else if ($is_add)
                {
                    $post_user['password'] = hash('sha512', sha1(random_bytes(16)));
                }

                if ($username_edit_en)
                {
                    $post_user['name'] = $name;
                }

                if ($fullname_edit_en)
                {
                    $post_user['fullname'] = $fullname;
                }

                if ($pp->is_admin())
                {
                    $post_user['code'] = $code;
                    $post_user['role'] = $role;
                    $post_user['status'] = (int) $status;
                    $post_user['admincomment'] = $admincomment;
                    $post_user['minlimit'] = $minlimit === '' ? null : (int) $minlimit;
                    $post_user['maxlimit'] = $maxlimit === '' ? null : (int) $maxlimit;
                }

                if ($is_add)
                {
                    if ($db->insert($pp->schema() . '.users', $post_user))
                    {
                        $id = (int) $db->lastInsertId($pp->schema() . '.users_id_seq');
                        $alert_service->success('Gebruiker opgeslagen.');
                    }
                    else
                    {
                        $errors[] = 'Gebruiker niet opgeslagen.';
                    }
                }

                if ($is_edit)
                {
                    if ($db->update($pp->schema() . '.users', $post_user, ['id' => $id]))
                    {
                        $alert_service->success('Gebruiker aangepast.');
                    }
                    else
                    {
                        $errors[] = 'Gebruiker niet aangepast.';
                    }
                }
            }

            if (!count($errors))
            {
                $user_cache_service->clear($id, $pp->schema());

                if ($pp->is_admin())
                {
                    $contact_types = [];

                    $rs = $db->prepare('select abbrev, id
                        from ' . $pp->schema() . '.type_contact');

                    $rs->execute();

                    while ($row = $rs->fetch())
                    {
                        $contact_types[$row['abbrev']] = $row['id'];
                    }

                    $stored_contacts = [];

                    $rs = $db->prepare('select c.id,
                            tc.abbrev, c.value, c.access
                        from ' . $pp->schema() . '.type_contact tc, ' .
                            $pp->schema() . '.contact c
                        WHERE tc.id = c.id_type_contact
                            AND c.user_id = ?');
                    $rs->bindValue(1, $id);

                    $rs->execute();

                    while ($row = $rs->fetch())
                    {
                        $stored_contacts[$row['id']] = $row;
                    }

                    foreach ($contact as $contact_ary)
                    {
                        $contact_id = $contact_ary['id'] ?? 0;
                        $stored_contact = $stored_contacts[$contact_id] ?? [];

                        if (!$contact_ary['value'])
                        {
                            if ($stored_contact)
                            {
                                $db->delete($pp->schema() . '.contact',
                                    ['user_id' => $id, 'id' => $contact_ary['id']]);
                            }
                            continue;
                        }

                        if ($stored_contact
                            && $stored_contact['abbrev'] === $contact_ary['abbrev']
                            && $stored_contact['value'] === $contact_ary['value']
                            && $stored_contact['access'] === $contact_ary['access'])
                        {
                            continue;
                        }

                        if ($contact_ary['abbrev'] === 'adr')
                        {
                            $geocode_queue->cond_queue([
                                'adr'		=> $contact_ary['value'],
                                'uid'		=> $id,
                                'schema'	=> $pp->schema(),
                            ], 0);
                        }

                        if (!isset($stored_contact) || !$stored_contact)
                        {
                            $insert = [
                                'id_type_contact'	=> $contact_types[$contact_ary['abbrev']],
                                'value'				=> trim($contact_ary['value']),
                                'access'		    => $contact_ary['access'],
                                'user_id'			=> $id,
                            ];

                            $db->insert($pp->schema() . '.contact', $insert);
                            continue;
                        }

                        $contact_update = $contact_ary;

                        unset($contact_update['id']);
                        unset($contact_update['abbrev']);

                        $db->update($pp->schema() . '.contact',
                            $contact_update,
                            ['id' => $contact_ary['id'], 'user_id' => $id]);
                    }

                    if ($status === '1' && !$is_activated)
                    {
                        if ($password_notify && $password)
                        {
                            if ($mailenabled)
                            {
                                if ($mailadr)
                                {
                                    $alert_service->success('E-mail met paswoord
                                        naar de gebruiker verstuurd.');
                                }
                                else
                                {
                                    $alert_service->warning('Er werd geen E-mail
                                        met passwoord naar de gebruiker verstuurd
                                        want er is geen E-mail adres voor deze
                                        gebruiker ingesteld.');
                                }

                                self::send_activation_mail(
                                    $mail_queue,
                                    $mail_addr_system_service,
                                    $mail_addr_user_service,
                                    $mailadr ? true : false,
                                    $password,
                                    $id,
                                    $pp->schema()
                                );
                            }
                            else
                            {
                                $alert_service->warning('De E-mail functies zijn uitgeschakeld.
                                    Geen E-mail met paswoord naar de gebruiker verstuurd.');
                            }
                        }
                        else
                        {
                            $alert_service->warning('Geen E-mail met
                                paswoord naar de gebruiker verstuurd.');
                        }
                    }
                }

                $typeahead_service->clear(TypeaheadService::GROUP_ACCOUNTS);
                $typeahead_service->clear(TypeaheadService::GROUP_USERS);

                $intersystems_service->clear_cache($su->schema());

                $link_render->redirect($vr->get('users_show'),
                    $pp->ary(), ['id' => $id]);
            }

            $alert_service->error($errors);
        }

        if ($request->isMethod('GET'))
        {
            $code = '';
            $name = '';
            $fullname = '';
            $fullname_access = '';
            $postcode = '';
            $birthday = '';
            $hobbies = '';
            $comments = '';
            $role = 'user';
            $status = '1';
            $admincomment = '';
            $minlimit = $config_service->get('preset_minlimit', $pp->schema());
            $maxlimit = $config_service->get('preset_maxlimit', $pp->schema());
            $periodic_overview_en	= true;

            $contact = $db->fetchAll('select name, abbrev,
                \'\' as value, 0 as id
                from ' . $pp->schema() . '.type_contact
                where abbrev in (\'mail\', \'adr\', \'tel\', \'gsm\')');

            if ($is_add && $intersystem_code)
            {
                if ($group = $db->fetchAssoc('select *
                    from ' . $pp->schema() . '.letsgroups
                    where localletscode = ?
                        and apimethod <> \'internal\'', [$intersystem_code]))
                {
                    $name = $fullname = $group['groupname'];

                    if ($group['url']
                        && ($systems_service->get_schema_from_legacy_eland_origin($group['url'])))
                    {
                        $remote_schema = $systems_service->get_schema_from_legacy_eland_origin($group['url']);

                        $admin_mail = $config_service->get('admin', $remote_schema);

                        foreach ($contact as $k => $c)
                        {
                            if ($c['abbrev'] == 'mail')
                            {
                                $contact[$k]['value'] = $admin_mail;
                                break;
                            }
                        }

                        // name from source is preferable
                        $name = $fullname = $config_service->get('systemname', $remote_schema);
                    }
                }

                $periodic_overview_en = false;
                $status = '7';
                $role = 'guest';
                $code = $intersystem_code;
            }

            if ($is_edit)
            {
                $code = $stored_user['code'] ?? '';
                $name = $stored_user['name'] ?? '';
                $fullname = $stored_user['fullname'] ?? '';
                $fullname_access = $stored_user['fullname_access'] ?? 'admin';
                $postcode = $stored_user['postcode'] ?? '';
                $birthday = $stored_user['birthday'] ?? '';
                $hobbies = $stored_user['hobbies'] ?? '';
                $comments = $stored_user['comments'] ?? '';
                $role = $stored_user['role'] ?? 'user';
                $status = (string) ($stored_user['status'] ?? '1');
                $admincomment = $stored_user['admincomment'] ?? '';
                $minlimit = $stored_user['minlimit'] ?? '';
                $maxlimit = $stored_user['maxlimit'] ?? '';
                $periodic_overview_en = $stored_user['periodic_overview_en'] ?? false;

                // Fetch contacts

                $contact_keys = [];

                foreach ($contact as $key => $c)
                {
                    $contact_keys[$c['abbrev']] = $key;
                }

                $st = $db->prepare('select tc.abbrev, c.value, tc.name, c.access, c.id
                    from ' . $pp->schema() . '.type_contact tc, ' .
                        $pp->schema() . '.contact c
                    where tc.id = c.id_type_contact
                        and c.user_id = ?');

                $st->bindValue(1, $id);
                $st->execute();

                while ($row = $st->fetch())
                {
                    if (isset($contact_keys[$row['abbrev']]))
                    {
                        $contact[$contact_keys[$row['abbrev']]] = $row;
                        unset($contact_keys[$row['abbrev']]);
                        continue;
                    }

                    $contact[] = $row;
                }
            }
        }

        $assets_service->add([
            'datepicker',
            'generate_password.js',
            'user_edit.js',
        ]);

        if ($is_owner && !$pp->is_admin() && $is_edit)
        {
            $heading_render->add('Je profiel aanpassen');
        }
        else
        {
            $heading_render->add('Gebruiker ');

            if ($is_edit)
            {
                $heading_render->add('aanpassen: ');
                $heading_render->add_raw($account_render->link($id, $pp->ary()));
            }
            else
            {
                $heading_render->add('toevoegen');
            }
        }

        $heading_render->fa('user');

        $out = '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';

        $out .= '<form method="post">';

        if ($pp->is_admin())
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="code" class="control-label">';
            $out .= 'Account Code';
            $out .= '</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-prepend">';
            $out .= '<span class="input-group-text">';
            $out .= '<span class="fa fa-user"></span>';
            $out .= '</span>';
            $out .= '</span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="code" name="code" ';
            $out .= 'value="';
            $out .= self::esc($code);
            $out .= '" required maxlength="20" ';
            $out .= 'data-typeahead="';

            $out .= $typeahead_service->ini()
                ->add('account_codes', [])
                ->str([
                    'render'	=> [
                        'check'	=> 10,
                        'omit'	=> $stored_code ?? '',
                    ]
                ]);

            $out .= '">';
            $out .= '</div>';
            $out .= '<span class="help-block hidden exists_query_results">';
            $out .= 'Reeds gebruikt: ';
            $out .= '<span class="query_results">';
            $out .= '</span>';
            $out .= '</span>';
            $out .= '<span class="help-block hidden exists_msg">';
            $out .= 'Deze Account Code bestaat al!';
            $out .= '</span>';
            $out .= '</div>';
        }

        if ($username_edit_en)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="name" class="control-label">';
            $out .= 'Gebruikersnaam</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-prepend">';
            $out .= '<span class="input-group-text">';
            $out .= '<span class="fa fa-user"></span>';
            $out .= '</span>';
            $out .= '</span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="name" name="name" ';
            $out .= 'value="';
            $out .= self::esc($name);
            $out .= '" required maxlength="50" ';
            $out .= 'data-typeahead="';

            $out .= $typeahead_service->ini()
                ->add('usernames', [])
                ->str([
                    'render'	=> [
                        'check'	=> 10,
                        'omit'	=> $stored_name ?? '',
                    ]
                ]);

            $out .= '">';
            $out .= '</div>';
            $out .= '<span class="help-block hidden exists_query_results">';
            $out .= 'Reeds gebruikt: ';
            $out .= '<span class="query_results">';
            $out .= '</span>';
            $out .= '</span>';
            $out .= '<span id="username_exists" ';
            $out .= 'class="help-block hidden exists_msg">';
            $out .= 'Deze Gebruikersnaam bestaat reeds!</span>';
            $out .= '</div>';
        }

        if ($fullname_edit_en)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="fullname" class="control-label">';
            $out .= 'Volledige Naam</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-prepend">';
            $out .= '<span class="input-group-text">';
            $out .= '<span class="fa fa-user"></span>';
            $out .= '</span>';
            $out .= '</span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="fullname" name="fullname" ';
            $out .= 'value="';
            $out .= self::esc($fullname);
            $out .= '" maxlength="100">';
            $out .= '</div>';
            $out .= '<p>';
            $out .= 'Voornaam en Achternaam';
            $out .= '</p>';
            $out .= '</div>';
        }

        $out .= $item_access_service->get_radio_buttons(
            'fullname_access',
            $fullname_access,
            'fullname_access',
            false,
            'Zichtbaarheid Volledige Naam'
        );

        $out .= '<div class="form-group">';
        $out .= '<label for="postcode" class="control-label">';
        $out .= 'Postcode</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<span class="fa fa-map-marker"></span>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="postcode" name="postcode" ';
        $out .= 'value="';
        $out .= self::esc($postcode);
        $out .= '" ';
        $out .= 'required maxlength="6" ';
        $out .= 'data-typeahead="';

        $out .= $typeahead_service->ini()
            ->add('postcodes', [])
            ->str();

        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="birthday" class="control-label">';
        $out .= 'Geboortedatum</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<span class="fa fa-calendar"></span>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="birthday" name="birthday" ';
        $out .= 'value="';

        if ($birthday)
        {
            $out .= $date_format_service->get($birthday, 'day', $pp->schema());
        }

        $out .= '" ';
        $out .= 'data-provide="datepicker" ';
        $out .= 'data-date-format="';
        $out .= $date_format_service->datepicker_format($pp->schema());
        $out .= '" ';
        $out .= 'data-date-default-view="2" ';
        $out .= 'data-date-end-date="';
        $out .= $date_format_service->get('', 'day', $pp->schema());
        $out .= '" ';
        $out .= 'data-date-language="nl" ';
        $out .= 'data-date-start-view="2" ';
        $out .= 'data-date-today-highlight="true" ';
        $out .= 'data-date-autoclose="true" ';
        $out .= 'data-date-immediate-updates="true" ';
        $out .= 'data-date-orientation="bottom" ';
        $out .= 'placeholder="';
        $out .= $date_format_service->datepicker_placeholder($pp->schema());
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="hobbies" class="control-label">';
        $out .= 'Hobbies, interesses</label>';
        $out .= '<textarea name="hobbies" id="hobbies" ';
        $out .= 'class="form-control" maxlength="500">';
        $out .= self::esc($hobbies);
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="comments" class="control-label">Commentaar</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<span class="fa fa-comment-o"></span>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="comments" name="comments" ';
        $out .= 'value="';
        $out .= self::esc($comments);
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        if ($pp->is_admin())
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="role" class="control-label">';
            $out .= 'Rechten / Rol</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-prepend">';
            $out .= '<span class="input-group-text">';
            $out .= '<span class="fa fa-hand-paper-o"></span>';
            $out .= '</span>';
            $out .= '</span>';
            $out .= '<select id="role" name="role" ';
            $out .= 'class="form-control">';
            $out .= $select_render->get_options(RoleCnst::LABEL_ARY, $role);
            $out .= '</select>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="form-group">';
            $out .= '<label for="status" class="control-label">';
            $out .= 'Status</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-prepend">';
            $out .= '<span class="input-group-text">';
            $out .= '<span class="fa fa-star-o"></span>';
            $out .= '</span>';
            $out .= '</span>';
            $out .= '<select id="status" name="status" class="form-control">';
            $out .= $select_render->get_options(StatusCnst::LABEL_ARY, $status);
            $out .= '</select>';
            $out .= '</div>';
            $out .= '</div>';

            if ($pp->is_admin() &&!$is_activated)
            {
                $out .= '<div id="activate" class="bg-success pan-sub">';

                $out .= '<div class="form-group">';
                $out .= '<label for="password" class="control-label">';
                $out .= 'Paswoord</label>';
                $out .= '<div class="input-group">';
                $out .= '<span class="input-group-prepend">';
                $out .= '<span class="input-group-text">';
                $out .= '<span class="fa fa-key"></span>';
                $out .= '</span>';
                $out .= '</span>';
                $out .= '<input type="text" class="form-control" ';
                $out .= 'id="password" name="password" ';
                $out .= 'value="" required>';
                $out .= '<span class="input-group-btn">';
                $out .= '<button class="btn btn-default" ';
                $out .= 'type="button" ';
                $out .= 'data-generate-password="onload" ';
                $out .= '>';
                $out .= 'Genereer</button>';
                $out .= '</span>';
                $out .= '</div>';
                $out .= '</div>';

                $out .= '<div class="form-group">';
                $out .= '<label for="password_notify" class="control-label">';
                $out .= '<input type="checkbox" name="password_notify" id="password_notify"';
                $out .= ' checked="checked"';
                $out .= '> ';
                $out .= 'Verstuur een E-mail met het ';
                $out .= 'paswoord naar de gebruiker. ';
                $out .= 'Dit kan enkel wanneer het account ';
                $out .= 'de status actief heeft en ';
                $out .= 'een E-mail adres is ingesteld.';
                $out .= '</label>';
                $out .= '</div>';

                $out .= '</div>';
            }

            $out .= '<div class="form-group">';
            $out .= '<label for="admincomment" class="control-label">';
            $out .= 'Commentaar van de admin</label>';
            $out .= '<textarea name="admincomment" id="admincomment" ';
            $out .= 'class="form-control" maxlength="200">';
            $out .= self::esc($admincomment);
            $out .= '</textarea>';
            $out .= 'Deze informatie is enkel zichtbaar voor de admins';
            $out .= '</div>';

            $out .= '<div class="pan-sub">';

            $out .= '<h2>Limieten&nbsp;';

            if ($minlimit === '' && $maxlimit === '')
            {
                $out .= '<button class="btn btn-default" ';
                $out .= 'title="Limieten instellen" data-toggle="collapse" ';
                $out .= 'data-target="#limits_pan" type="button">';
                $out .= 'Instellen</button>';
            }

            $out .= '</h2>';

            $out .= '<div id="limits_pan"';

            if ($minlimit === '' && $maxlimit === '')
            {
                $out .= ' class="collapse"';
            }

            $out .= '>';

            $out .= '<div class="form-group">';
            $out .= '<label for="minlimit" class="control-label">';
            $out .= 'Minimum Account Limiet</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-prepend">';
            $out .= '<span class="input-group-text">';
            $out .= '<span class="fa fa-arrow-down"></span> ';
            $out .= $config_service->get('currency', $pp->schema());
            $out .= '</span>';
            $out .= '</span>';
            $out .= '<input type="number" class="form-control" ';
            $out .= 'id="minlimit" name="minlimit" ';
            $out .= 'value="';
            $out .= $minlimit ?? '';
            $out .= '">';
            $out .= '</div>';
            $out .= '<p>Vul enkel in wanneer je een individueel ';
            $out .= 'afwijkende minimum limiet wil instellen ';
            $out .= 'voor dit account. Als dit veld leeg is, ';
            $out .= 'dan is de algemeen geldende ';
            $out .= $link_render->link_no_attr('config', $pp->ary(),
                ['tab' => 'balance'], 'Minimum Systeemslimiet');
            $out .= ' ';
            $out .= 'van toepassing. ';

            if ($config_service->get('minlimit', $pp->schema()) === '')
            {
                $out .= 'Er is momenteel <strong>geen</strong> algemeen ';
                $out .= 'geledende Minimum Systeemslimiet ingesteld. ';
            }
            else
            {
                $out .= 'De algemeen geldende ';
                $out .= 'Minimum Systeemslimiet bedraagt <strong>';
                $out .= $config_service->get('minlimit', $pp->schema());
                $out .= ' ';
                $out .= $config_service->get('currency', $pp->schema());
                $out .= '</strong>. ';
            }

            $out .= 'Dit veld wordt bij aanmaak van een ';
            $out .= 'gebruiker vooraf ingevuld met de "';
            $out .= $link_render->link_no_attr('config', $pp->ary(),
                ['tab' => 'balance'],
                'Preset Individuele Minimum Account Limiet');
            $out .= '" ';
            $out .= 'die gedefiniëerd is in de instellingen.';

            if ($config_service->get('preset_minlimit', $pp->schema()) !== '')
            {
                $out .= ' De Preset bedraagt momenteel <strong>';
                $out .= $config_service->get('preset_minlimit', $pp->schema());
                $out .= '</strong>.';
            }

            $out .= '</p>';
            $out .= '</div>';

            $out .= '<div class="form-group">';
            $out .= '<label for="maxlimit" class="control-label">';
            $out .= 'Maximum Account Limiet</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-prepend">';
            $out .= '<span class="input-group-text">';
            $out .= '<span class="fa fa-arrow-up"></span> ';
            $out .= $config_service->get('currency', $pp->schema());
            $out .= '</span>';
            $out .= '</span>';
            $out .= '<input type="number" class="form-control" ';
            $out .= 'id="maxlimit" name="maxlimit" ';
            $out .= 'value="';
            $out .= $maxlimit ?? '';
            $out .= '">';
            $out .= '</div>';

            $out .= '<p>Vul enkel in wanneer je een individueel ';
            $out .= 'afwijkende maximum limiet wil instellen ';
            $out .= 'voor dit account. Als dit veld leeg is, ';
            $out .= 'dan is de algemeen geldende ';
            $out .= $link_render->link_no_attr('config', $pp->ary(),
                ['tab' => 'balance'],
                'Maximum Systeemslimiet');
            $out .= ' ';
            $out .= 'van toepassing. ';

            if ($config_service->get('maxlimit', $pp->schema()) === '')
            {
                $out .= 'Er is momenteel <strong>geen</strong> algemeen ';
                $out .= 'geledende Maximum Systeemslimiet ingesteld. ';
            }
            else
            {
                $out .= 'De algemeen geldende Maximum ';
                $out .= 'Systeemslimiet bedraagt <strong>';
                $out .= $config_service->get('maxlimit', $pp->schema());
                $out .= ' ';
                $out .= $config_service->get('currency', $pp->schema());
                $out .= '</strong>. ';
            }

            $out .= 'Dit veld wordt bij aanmaak van een gebruiker ';
            $out .= 'vooraf ingevuld wanneer "';
            $out .= $link_render->link_no_attr('config', $pp->ary(),
                ['tab' => 'balance'],
                'Preset Individuele Maximum Account Limiet');
            $out .= '" ';
            $out .= 'is ingevuld in de instellingen.';

            if ($config_service->get('preset_maxlimit', $pp->schema()) !== '')
            {
                $out .= ' De Preset bedraagt momenteel <strong>';
                $out .= $config_service->get('preset_maxlimit', $pp->schema());
                $out .= '</strong>.';
            }

            $out .= '</p>';

            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="bg-warning pan-sub">';
            $out .= '<h2><i class="fa fa-map-marker"></i> Contacten</h2>';

            $out .= '<p>Meer contacten kunnen toegevoegd worden ';
            $out .= 'vanuit de profielpagina met de knop ';
            $out .= 'Toevoegen bij de contactinfo ';
            $out .= $is_edit ? '' : 'nadat de gebruiker gecreëerd is';
            $out .= '.</p>';

            foreach ($contact as $key => $c)
            {
                $c_name = 'contact[' . $key . '][value]';
                $c_abbrev = $c['abbrev'];
                $c_access_name = 'contact[' . $key . '][access]';

                $out .= '<div class="pan-sab">';

                $out .= '<div class="form-group">';
                $out .= '<label for="';
                $out .= $c_name;
                $out .= '" class="control-label">';
                $out .= ContactInputCnst::FORMAT_ARY[$c_abbrev]['lbl'] ?? $c_abbrev;
                $out .= '</label>';
                $out .= '<div class="input-group">';
                $out .= '<span class="input-group-prepend">';
                $out .= '<span class="input-group-text">';
                $out .= '<i class="fa fa-';
                $out .= ContactInputCnst::FORMAT_ARY[$c_abbrev]['fa'] ?? 'question-mark';
                $out .= '"></i>';
                $out .= '</span>';
                $out .= '</span>';
                $out .= '<input class="form-control" id="';
                $out .= $c_name;
                $out .= '" name="';
                $out .= $c_name;
                $out .= '" ';
                $out .= 'value="';
                $out .= self::esc($c['value'] ?? '');
                $out .= '" type="';
                $out .= ContactInputCnst::FORMAT_ARY[$c_abbrev]['type'] ?? 'text';
                $out .= '" ';
                $out .= isset(ContactInputCnst::FORMAT_ARY[$c_abbrev]['disabled']) ? 'disabled ' : '';
                $out .= 'data-access="';
                $out .= $c_access_name;
                $out .= '">';
                $out .= '</div>';

                if (isset(ContactInputCnst::FORMAT_ARY[$c_abbrev]['explain']))
                {
                    $out .= '<p>';
                    $out .= ContactInputCnst::FORMAT_ARY[$c_abbrev]['explain'];
                    $out .= '</p>';
                }

                $out .= '</div>';

                $out .= $item_access_service->get_radio_buttons(
                    $c_access_name,
                    $c['access'] ?? '',
                    $c_abbrev
                );

                $out .= '<input type="hidden" ';
                $out .= 'name="contact['. $key . '][id]" value="' . $c['id'] . '">';
                $out .= '<input type="hidden" ';
                $out .= 'name="contact['. $key . '][abbrev]" value="' . $c['abbrev'] . '">';

                $out .= '</div>';
            }

            $out .= '</div>';
        }

        $out .= '<div class="form-group">';
        $out .= '<label for="periodic_overview_en" class="control-label">';
        $out .= '<input type="checkbox" name="periodic_overview_en" id="periodic_overview_en"';
        $out .= $periodic_overview_en ? ' checked="checked"' : '';
        $out .= '>	';
        $out .= 'Periodieke Overzichts E-mail';
        $out .= '</label>';
        $out .= '</div>';

        if ($is_edit)
        {
            $out .= $link_render->btn_cancel($vr->get('users_show'), $pp->ary(),
                ['id' => $id]);
        }
        else
        {
            $out .= $link_render->btn_cancel($vr->get('users'), $pp->ary(), []);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Opslaan" class="btn btn-';
        $out .= $is_edit ? 'primary' : 'success';
        $out .= ' btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('users');

        return $out;
    }

    private static function send_activation_mail(
        MailQueue $mail_queue,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        bool $to_user_en,
        string $password,
        int $user_id,
        string $pp_schema
    ):void
    {
        $mail_queue->queue([
            'schema'	=> $pp_schema,
            'to' 		=> $mail_addr_system_service->get_admin($pp_schema),
            'template'	=> 'account_activation/admin',
            'vars'		=> [
                'user_id'		=> $user_id,
                'user_email'	=> $mail_addr_user_service->get($user_id, $pp_schema),
            ],
        ], 5000);

        if (!$to_user_en)
        {
            return;
        }

        $mail_queue->queue([
            'schema'	=> $pp_schema,
            'to' 		=> $mail_addr_user_service->get($user_id, $pp_schema),
            'reply_to' 	=> $mail_addr_system_service->get_support($pp_schema),
            'template'	=> 'account_activation/user',
            'vars'		=> [
                'user_id'	=> $user_id,
                'password'	=> $password,
            ],
        ], 5100);
    }

    private static function esc(string $str):string
    {
        return trim(htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8'));
    }
}
