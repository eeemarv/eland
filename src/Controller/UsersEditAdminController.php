<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\AccessCnst;
use App\Cnst\StatusCnst;
use App\Cnst\RoleCnst;
use App\Cnst\ContactInputCnst;
use App\Queue\GeocodeQueue;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
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
use App\Service\ThumbprintAccountsService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use App\Service\XdbService;
use Doctrine\DBAL\Connection as Db;

class UsersEditAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
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
        XdbService $xdb_service,
        ThumbprintAccountsService $thumbprint_accounts_service,
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
            $xdb_service,
            $thumbprint_accounts_service,
            $mail_addr_user_service,
            $mail_addr_system_service,
            $mail_queue,
            $pp,
            $su,
            $vr,
            $menu_service
        );

        return $this->render('base/navbar.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
        ]);
    }

    public static function form(
        Request $request,
        int $id,
        bool $is_edit,
        Db $db,
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
        XdbService $xdb_service,
        ThumbprintAccountsService $thumbprint_accounts_service,
        MailAddrUserService $mail_addr_user_service,
        MailAddrSystemService $mail_addr_system_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service
    ):string
    {
        $errors = [];
        $contact = [];

        $intersystem_code = $request->query->get('intersystem_code', '');

        $s_owner = $is_edit
            && $su->id()
            && $id === $su->id();

        if ($pp->is_admin())
        {
            $username_edit = $fullname_edit = true;
        }
        else if ($s_owner)
        {
            $username_edit = $config_service->get('users_can_edit_username', $pp->schema());
            $fullname_edit = $config_service->get('users_can_edit_fullname', $pp->schema());
        }
        else
        {
            $username_edit = $fullname_edit = false;
        }

        if ($request->isMethod('POST'))
        {
            $user = [
                'postcode'		=> trim($request->request->get('postcode', '')),
                'birthday'		=> trim($request->request->get('birthday', '')) ?: null,
                'hobbies'		=> trim($request->request->get('hobbies', '')),
                'comments'		=> trim($request->request->get('comments', '')),
                'cron_saldo'	=> $request->request->get('cron_saldo') ? 1 : 0,
                'lang'			=> 'nl'
            ];

            if ($pp->is_admin())
            {
                // hack eLAS compatibility (in eLAND limits can be null)
                $minlimit = trim($request->request->get('minlimit', ''));
                $maxlimit = trim($request->request->get('maxlimit', ''));

                $minlimit = $minlimit === '' ? -999999999 : $minlimit;
                $maxlimit = $maxlimit === '' ? 999999999 : $maxlimit;

                $user += [
                    'letscode'		=> trim($request->request->get('letscode', '')),
                    'accountrole'	=> $request->request->get('accountrole', ''),
                    'status'		=> $request->request->get('status', ''),
                    'admincomment'	=> trim($request->request->get('admincomment', '')),
                    'minlimit'		=> $minlimit,
                    'maxlimit'		=> $maxlimit,
                    'presharedkey'	=> trim($request->request->get('presharedkey', '')),
                ];

                $contact = $request->request->get('contact', []);
                $notify = $request->request->get('notify');
                $password = trim($request->request->get('password', ''));

                $mail_unique_check_sql = 'select count(c.value)
                    from ' . $pp->schema() . '.contact c, ' .
                        $pp->schema() . '.type_contact tc, ' .
                        $pp->schema() . '.users u
                    where c.id_type_contact = tc.id
                        and tc.abbrev = \'mail\'
                        and c.value = ?
                        and c.id_user = u.id
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

                    if (isset($c['access']))
                    {
                        $contact[$key]['flag_public'] = AccessCnst::TO_FLAG_PUBLIC[$c['access']];
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

                if ($user['status'] == 1 || $user['status'] == 2)
                {
                    if (!$mailadr)
                    {
                        $err = 'Waarschuwing: Geen E-mail adres ingevuld. ';
                        $err .= 'De gebruiker kan geen berichten en notificaties ';
                        $err .= 'ontvangen en zijn/haar paswoord niet resetten.';
                        $alert_service->warning($err);
                    }
                }

                $letscode_sql = 'select letscode
                    from ' . $pp->schema() . '.users
                    where letscode = ?';
                $letscode_sql_params = [$user['letscode']];
            }

            if ($username_edit)
            {
                $user['login'] = $user['name'] = trim($request->request->get('name', ''));
            }

            if ($fullname_edit)
            {
                $user['fullname'] = trim($request->request->get('fullname', ''));
            }

            $fullname_access = $request->request->get('fullname_access', '');

            $name_sql = 'select name
                from ' . $pp->schema() . '.users
                where name = ?';
            $name_sql_params = [$user['name']];

            $fullname_sql = 'select fullname
                from ' . $pp->schema() . '.users
                where fullname = ?';
            $fullname_sql_params = [$user['fullname']];

            if ($is_edit)
            {
                $letscode_sql .= ' and id <> ?';
                $letscode_sql_params[] = $id;
                $name_sql .= 'and id <> ?';
                $name_sql_params[] = $id;
                $fullname_sql .= 'and id <> ?';
                $fullname_sql_params[] = $id;

                $user_prefetch = $user_cache_service->get($id, $pp->schema());
            }

            if (!$fullname_access)
            {
                $errors[] = 'Vul een zichtbaarheid in voor de volledige naam.';
            }

            if ($username_edit)
            {
                if (!$user['name'])
                {
                    $errors[] = 'Vul gebruikersnaam in!';
                }
                else if ($db->fetchColumn($name_sql, $name_sql_params))
                {
                    $errors[] = 'Deze gebruikersnaam is al in gebruik!';
                }
                else if (strlen($user['name']) > 50)
                {
                    $errors[] = 'De gebruikersnaam mag maximaal 50 tekens lang zijn.';
                }
            }

            if ($fullname_edit)
            {
                if (!$user['fullname'])
                {
                    $errors[] = 'Vul de Volledige Naam in!';
                }

                if ($db->fetchColumn($fullname_sql, $fullname_sql_params))
                {
                    $errors[] = 'Deze Volledige Naam is al in gebruik!';
                }

                if (strlen($user['fullname']) > 100)
                {
                    $errors[] = 'De Volledige Naam mag maximaal 100 tekens lang zijn.';
                }
            }

            if ($pp->is_admin())
            {
                if (!$user['letscode'])
                {
                    $errors[] = 'Vul een Account Code in!';
                }
                else if ($db->fetchColumn($letscode_sql, $letscode_sql_params))
                {
                    $errors[] = 'De Account Code bestaat al!';
                }
                else if (strlen($user['letscode']) > 20)
                {
                    $errors[] = 'De Account Code mag maximaal
                        20 tekens lang zijn.';
                }

                if (!preg_match("/^[A-Za-z0-9-]+$/", $user['letscode']))
                {
                    $errors[] = 'De Account Code kan enkel uit
                        letters, cijfers en koppeltekens bestaan.';
                }

                if (filter_var($user['minlimit'], FILTER_VALIDATE_INT) === false)
                {
                    $errors[] = 'Geef getal of niets op voor de
                        Minimum Account Limiet.';
                }

                if (filter_var($user['maxlimit'], FILTER_VALIDATE_INT) === false)
                {
                    $errors[] = 'Geef getal of niets op voor de
                        Maximum Account Limiet.';
                }

                if (strlen($user['presharedkey']) > 80)
                {
                    $errors[] = 'De Preshared Key mag maximaal
                        80 tekens lang zijn.';
                }
            }

            if ($user['birthday'])
            {
                $user['birthday'] = $date_format_service->reverse($user['birthday'], $pp->schema());

                if ($user['birthday'] === '')
                {
                    $errors[] = 'Fout in formaat geboortedag.';
                    $user['birthday'] = '';
                }
            }

            if (strlen($user['comments']) > 100)
            {
                $errors[] = 'Het veld Commentaar mag maximaal
                    100 tekens lang zijn.';
            }

            if (strlen($user['postcode']) > 6)
            {
                $errors[] = 'De postcode mag maximaal 6 tekens lang zijn.';
            }

            if (strlen($user['hobbies']) > 500)
            {
                $errors[] = 'Het veld hobbies en interesses mag
                    maximaal 500 tekens lang zijn.';
            }

            if ($pp->is_admin()
                && ($is_edit && !$user_prefetch['adate'])
                && $user['status'] == 1)
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

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $contact_types = [];

                $rs = $db->prepare('select abbrev, id
                    from ' . $pp->schema() . '.type_contact');

                $rs->execute();

                while ($row = $rs->fetch())
                {
                    $contact_types[$row['abbrev']] = $row['id'];
                }

                if (!$is_edit)
                {
                    $user['creator'] = $su->is_master() ? 0 : $su->id();

                    $user['cdate'] = gmdate('Y-m-d H:i:s');

                    if ($user['status'] == 1)
                    {
                        $user['adate'] = gmdate('Y-m-d H:i:s');
                        $user['password'] = hash('sha512', $password);
                    }
                    else
                    {
                        $user['password'] = hash('sha512', sha1(microtime()));
                    }

                    if ($db->insert($pp->schema() . '.users', $user))
                    {
                        $id = (int) $db->lastInsertId($pp->schema() . '.users_id_seq');

                        $fullname_access_role = AccessCnst::TO_XDB[$fullname_access];

                        $xdb_service->set('user_fullname_access', (string) $id, [
                            'fullname_access' => $fullname_access_role,
                        ], $pp->schema());

                        $alert_service->success('Gebruiker opgeslagen.');

                        $user_cache_service->clear($id, $pp->schema());
                        $user = $user_cache_service->get($id, $pp->schema());

                        foreach ($contact as $contact_ary)
                        {
                            if (!$contact_ary['value'])
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

                            $insert = [
                                'value'				=> trim($contact_ary['value']),
                                'flag_public'		=> $contact_ary['flag_public'],
                                'id_type_contact'	=> $contact_types[$contact_ary['abbrev']],
                                'id_user'			=> $id,
                            ];

                            $db->insert($pp->schema() . '.contact', $insert);
                        }

                        if ($user['status'] == 1)
                        {
                            if ($notify && $password)
                            {
                                if ($config_service->get('mailenabled', $pp->schema()))
                                {
                                    if ($mailadr)
                                    {
                                        $alert_service->success('Een E-mail met paswoord is
                                            naar de gebruiker verstuurd.');
                                    }
                                    else
                                    {
                                        $alert_service->warning('Er is geen E-mail met paswoord
                                            naar de gebruiker verstuurd want er is geen E-mail
                                            adres ingesteld voor deze gebruiker.');
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
                                $alert_service->warning('Geen E-mail met paswoord naar
                                    de gebruiker verstuurd.');
                            }
                        }

                        if ($user['status'] == 2 | $user['status'] == 1)
                        {
                            $thumbprint_accounts_service->delete('active', $pp->ary(), $pp->schema());
                        }

                        if ($user['status'] == 7)
                        {
                            $thumbprint_accounts_service->delete('extern', $pp->ary(), $pp->schema());
                        }

                        $intersystems_service->clear_cache($su->schema());

                        $link_render->redirect($vr->get('users'), $pp->ary(), ['id' => $id]);
                    }
                    else
                    {
                        $alert_service->error('Gebruiker niet opgeslagen.');
                    }
                }
                else if ($is_edit)
                {
                    $user_stored = $user_cache_service->get($id, $pp->schema());

                    $user['mdate'] = gmdate('Y-m-d H:i:s');

                    if (!$user_stored['adate'] && $user['status'] == 1)
                    {
                        $user['adate'] = gmdate('Y-m-d H:i:s');

                        if ($password)
                        {
                            $user['password'] = hash('sha512', $password);
                        }
                    }

                    if ($db->update($pp->schema() . '.users', $user, ['id' => $id]))
                    {

                        $fullname_access_role = AccessCnst::TO_XDB[$fullname_access];

                        $xdb_service->set('user_fullname_access', (string) $id, [
                            'fullname_access' => $fullname_access_role,
                        ], $pp->schema());

                        $user_cache_service->clear($id, $pp->schema());
                        $user = $user_cache_service->get($id, $pp->schema());

                        $alert_service->success('Gebruiker aangepast.');

                        if ($pp->is_admin())
                        {
                            $stored_contacts = [];

                            $rs = $db->prepare('select c.id,
                                    tc.abbrev, c.value, c.flag_public
                                from ' . $pp->schema() . '.type_contact tc, ' .
                                    $pp->schema() . '.contact c
                                WHERE tc.id = c.id_type_contact
                                    AND c.id_user = ?');
                            $rs->bindValue(1, $id);

                            $rs->execute();

                            while ($row = $rs->fetch())
                            {
                                $stored_contacts[$row['id']] = $row;
                            }

                            foreach ($contact as $contact_ary)
                            {
                                $contact_id = $contact_ary['id'];
                                $stored_contact = $stored_contacts[$contact_id] ?? [];

                                if (!$contact_ary['value'])
                                {
                                    if ($stored_contact)
                                    {
                                        $db->delete($pp->schema() . '.contact',
                                            ['id_user' => $id, 'id' => $contact_ary['id']]);
                                    }
                                    continue;
                                }

                                if ($stored_contact
                                    && $stored_contact['abbrev'] == $contact_ary['abbrev']
                                    && $stored_contact['value'] == $contact_ary['value']
                                    && $stored_contact['flag_public'] == $contact_ary['flag_public'])
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

                                if (!isset($stored_contact))
                                {
                                    $insert = [
                                        'id_type_contact'	=> $contact_types[$contact_ary['abbrev']],
                                        'value'				=> trim($contact_ary['value']),
                                        'flag_public'		=> $contact_ary['flag_public'],
                                        'id_user'			=> $id,
                                    ];

                                    $db->insert($pp->schema() . '.contact', $insert);
                                    continue;
                                }

                                $contact_update = $contact_ary;

                                unset($contact_update['id'], $contact_update['abbrev'],
                                    $contact_update['access']);

                                $db->update($pp->schema() . '.contact',
                                    $contact_update,
                                    ['id' => $contact_ary['id'], 'id_user' => $id]);
                            }

                            if ($user['status'] == 1 && !$user_prefetch['adate'])
                            {
                                if ($notify && $password)
                                {
                                    if ($config_service->get('mailenabled', $pp->schema()))
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

                            if ($user['status'] == 1
                                || $user['status'] == 2
                                || $user_stored['status'] == 1
                                || $user_stored['status'] == 2)
                            {
                                $thumbprint_accounts_service->delete('active', $pp->ary(), $pp->schema());
                            }

                            if ($user['status'] == 7
                                || $user_stored['status'] == 7)
                            {
                                $thumbprint_accounts_service->delete('extern', $pp->ary(), $pp->schema());
                            }

                            $intersystems_service->clear_cache($su->schema());
                        }

                        $link_render->redirect($vr->get('users_show'), $pp->ary(),
                            ['id' => $id]);
                    }
                    else
                    {
                        $alert_service->error('Gebruiker niet aangepast.');
                    }
                }
            }
            else
            {
                $alert_service->error($errors);

                if ($is_edit)
                {
                    $user['adate'] = $user_prefetch['adate'];
                }

                $user['minlimit'] = $user['minlimit'] === -999999999 ? '' : $user['minlimit'];
                $user['maxlimit'] = $user['maxlimit'] === 999999999 ? '' : $user['maxlimit'];
            }
        }
        else
        {
            if ($is_edit)
            {
                $user = $user_cache_service->get($id, $pp->schema());
                $fullname_access = AccessCnst::FROM_XDB[$user['fullname_access']];
            }

            if ($pp->is_admin())
            {
                $contact = $db->fetchAll('select name, abbrev,
                    \'\' as value, 0 as id
                    from ' . $pp->schema() . '.type_contact
                    where abbrev in (\'mail\', \'adr\', \'tel\', \'gsm\')');
            }

            if ($is_edit && $pp->is_admin())
            {
                $contact_keys = [];

                foreach ($contact as $key => $c)
                {
                    $contact_keys[$c['abbrev']] = $key;
                }

                $st = $db->prepare('select tc.abbrev, c.value, tc.name, c.flag_public, c.id
                    from ' . $pp->schema() . '.type_contact tc, ' .
                        $pp->schema() . '.contact c
                    where tc.id = c.id_type_contact
                        and c.id_user = ?');

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
            else if ($pp->is_admin())
            {
                $user = [
                    'minlimit'		=> $config_service->get('preset_minlimit', $pp->schema()),
                    'maxlimit'		=> $config_service->get('preset_maxlimit', $pp->schema()),
                    'accountrole'	=> 'user',
                    'status'		=> '1',
                    'cron_saldo'	=> 1,
                ];

                if ($intersystem_code)
                {
                    if ($group = $db->fetchAssoc('select *
                        from ' . $pp->schema() . '.letsgroups
                        where localletscode = ?
                            and apimethod <> \'internal\'', [$intersystem_code]))
                    {
                        $user['name'] = $user['fullname'] = $group['groupname'];

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
                            $user['name'] = $user['fullname'] = $config_service->get('systemname', $remote_schema);
                        }
                    }

                    $user['cron_saldo'] = 0;
                    $user['status'] = '7';
                    $user['accountrole'] = 'interlets';
                    $user['letscode'] = $intersystem_code;
                }
                else
                {
                    $user['cron_saldo'] = 1;
                    $user['status'] = '1';
                    $user['accountrole'] = 'user';
                }
            }
        }

        if ($is_edit)
        {
            $edit_user_cached = $user_cache_service->get($id, $pp->schema());
        }

        array_walk($user, function(&$value){ $value = trim(htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')); });
        array_walk($contact, function(&$value){ $value['value'] = trim(htmlspecialchars((string) $value['value'], ENT_QUOTES, 'UTF-8')); });

        $assets_service->add([
            'datepicker',
            'generate_password.js',
            'user_edit.js',
        ]);

        if ($s_owner && !$pp->is_admin() && $is_edit)
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

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        if ($pp->is_admin())
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="letscode" class="control-label">';
            $out .= 'Account Code';
            $out .= '</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-user"></span></span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="letscode" name="letscode" ';
            $out .= 'value="';
            $out .= $user['letscode'] ?? '';
            $out .= '" required maxlength="20" ';
            $out .= 'data-typeahead="';

            $out .= $typeahead_service->ini($pp->ary())
                ->add('account_codes', [])
                ->str([
                    'render'	=> [
                        'check'	=> 10,
                        'omit'	=> $edit_user_cached['letscode'] ?? '',
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

        if ($username_edit)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="name" class="control-label">';
            $out .= 'Gebruikersnaam</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-user"></span></span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="name" name="name" ';
            $out .= 'value="';
            $out .= $user['name'] ?? '';
            $out .= '" required maxlength="50" ';
            $out .= 'data-typeahead="';

            $out .= $typeahead_service->ini($pp->ary())
                ->add('usernames', [])
                ->str([
                    'render'	=> [
                        'check'	=> 10,
                        'omit'	=> $edit_user_cached['name'] ?? '',
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

        if ($fullname_edit)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="fullname" class="control-label">';
            $out .= 'Volledige Naam</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-user"></span></span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="fullname" name="fullname" ';
            $out .= 'value="';
            $out .= $user['fullname'] ?? '';
            $out .= '" maxlength="100">';
            $out .= '</div>';
            $out .= '<p>';
            $out .= 'Voornaam en Achternaam';
            $out .= '</p>';
            $out .= '</div>';
        }

        if (!isset($fullname_access))
        {
            $fullname_access = !$is_edit && !$intersystem_code ? '' : 'admin';
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
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-map-marker"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="postcode" name="postcode" ';
        $out .= 'value="';
        $out .= $user['postcode'] ?? '';
        $out .= '" ';
        $out .= 'required maxlength="6" ';
        $out .= 'data-typeahead="';

        $out .= $typeahead_service->ini($pp->ary())
            ->add('postcodes', [])
            ->str();

        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="birthday" class="control-label">';
        $out .= 'Geboortedatum</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-calendar"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="birthday" name="birthday" ';
        $out .= 'value="';

        if (isset($user['birthday']) && !empty($user['birtday']))
        {
            $out .= $date_format_service->get($user['birthday'], 'day', $pp->schema());
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
        $out .= $user['hobbies'] ?? '';
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="comments" class="control-label">Commentaar</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-comment-o"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="comments" name="comments" ';
        $out .= 'value="';
        $out .= $user['comments'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        if ($pp->is_admin())
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="accountrole" class="control-label">';
            $out .= 'Rechten / Rol</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-hand-paper-o"></span></span>';
            $out .= '<select id="accountrole" name="accountrole" ';
            $out .= 'class="form-control">';
            $out .= $select_render->get_options(RoleCnst::LABEL_ARY, $user['accountrole']);
            $out .= '</select>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="pan-sub" id="presharedkey_panel">';
            $out .= '<div class="form-group" id="presharedkey_formgroup">';
            $out .= '<label for="presharedkey" class="control-label">';
            $out .= 'Preshared Key</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-key"></span></span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="presharedkey" name="presharedkey" ';
            $out .= 'value="';
            $out .= $user['presharedkey'] ?? '';
            $out .= '" maxlength="80">';
            $out .= '</div>';
            $out .= '<p>Vul dit enkel in voor een interSysteem Account ';
            $out .= 'van een Systeem op een eLAS-server.</p>';
            $out .= '</div>';
            $out .= '</div>';

            $out .= '<div class="form-group">';
            $out .= '<label for="status" class="control-label">';
            $out .= 'Status</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-star-o"></span></span>';
            $out .= '<select id="status" name="status" class="form-control">';
            $out .= $select_render->get_options(StatusCnst::LABEL_ARY, $user['status']);
            $out .= '</select>';
            $out .= '</div>';
            $out .= '</div>';

            if (empty($user['adate']) && $pp->is_admin())
            {
                $out .= '<div id="activate" class="bg-success pan-sub">';

                $out .= '<div class="form-group">';
                $out .= '<label for="password" class="control-label">';
                $out .= 'Paswoord</label>';
                $out .= '<div class="input-group">';
                $out .= '<span class="input-group-addon">';
                $out .= '<span class="fa fa-key"></span></span>';
                $out .= '<input type="text" class="form-control" ';
                $out .= 'id="password" name="password" ';
                $out .= 'value="';
                $out .= $password ?? '';
                $out .= '" required>';
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
                $out .= '<label for="notify" class="control-label">';
                $out .= '<input type="checkbox" name="notify" id="notify"';
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
            $out .= $user['admincomment'] ?? '';
            $out .= '</textarea>';
            $out .= 'Deze informatie is enkel zichtbaar voor de admins';
            $out .= '</div>';

            $out .= '<div class="pan-sub">';

            $out .= '<h2>Limieten&nbsp;';

            if ($user['minlimit'] === '' && $user['maxlimit'] === '')
            {
                $out .= '<button class="btn btn-default" ';
                $out .= 'title="Limieten instellen" data-toggle="collapse" ';
                $out .= 'data-target="#limits_pan" type="button">';
                $out .= 'Instellen</button>';
            }

            $out .= '</h2>';

            $out .= '<div id="limits_pan"';

            if ($user['minlimit'] === '' && $user['maxlimit'] === '')
            {
                $out .= ' class="collapse"';
            }

            $out .= '>';

            $out .= '<div class="form-group">';
            $out .= '<label for="minlimit" class="control-label">';
            $out .= 'Minimum Account Limiet</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-arrow-down"></span> ';
            $out .= $config_service->get('currency', $pp->schema());
            $out .= '</span>';
            $out .= '<input type="number" class="form-control" ';
            $out .= 'id="minlimit" name="minlimit" ';
            $out .= 'value="';
            $out .= $user['minlimit'] ?? '';
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
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-arrow-up"></span> ';
            $out .= $config_service->get('currency', $pp->schema());
            $out .= '</span>';
            $out .= '<input type="number" class="form-control" ';
            $out .= 'id="maxlimit" name="maxlimit" ';
            $out .= 'value="';
            $out .= $user['maxlimit'] ?? '';
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
                $name = 'contact[' . $key . '][value]';
                $abbrev = $c['abbrev'];
                $access_name = 'contact[' . $key . '][access]';

                $out .= '<div class="pan-sab">';

                $out .= '<div class="form-group">';
                $out .= '<label for="';
                $out .= $name;
                $out .= '" class="control-label">';
                $out .= ContactInputCnst::FORMAT_ARY[$abbrev]['lbl'] ?? $c['abbrev'];
                $out .= '</label>';
                $out .= '<div class="input-group">';
                $out .= '<span class="input-group-addon">';
                $out .= '<i class="fa fa-';
                $out .= ContactInputCnst::FORMAT_ARY[$abbrev]['fa'] ?? 'question-mark';
                $out .= '"></i>';
                $out .= '</span>';
                $out .= '<input class="form-control" id="';
                $out .= $name;
                $out .= '" name="';
                $out .= $name;
                $out .= '" ';
                $out .= 'value="';
                $out .= $c['value'] ?? '';
                $out .= '" type="';
                $out .= ContactInputCnst::FORMAT_ARY[$abbrev]['type'] ?? 'text';
                $out .= '" ';
                $out .= isset(ContactInputCnst::FORMAT_ARY[$c['abbrev']]['disabled']) ? 'disabled ' : '';
                $out .= 'data-access="';
                $out .= $access_name;
                $out .= '">';
                $out .= '</div>';

                if (isset(ContactInputCnst::FORMAT_ARY[$abbrev]['explain']))
                {
                    $out .= '<p>';
                    $out .= ContactInputCnst::FORMAT_ARY[$abbrev]['explain'];
                    $out .= '</p>';
                }

                $out .= '</div>';

                $out .= $item_access_service->get_radio_buttons(
                    $access_name,
                    $item_access_service->get_value_from_flag_public($c['flag_public'] ?? ''),
                    $abbrev
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
        $out .= '<label for="cron_saldo" class="control-label">';
        $out .= '<input type="checkbox" name="cron_saldo" id="cron_saldo"';
        $out .= $user['cron_saldo'] ? ' checked="checked"' : '';
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
}
