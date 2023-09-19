<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cnst\BulkCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\StatusCnst;
use App\Cnst\RoleCnst;
use App\Cnst\ContactInputCnst;
use App\Queue\GeocodeQueue;
use App\Queue\MailQueue;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Repository\AccountRepository;
use App\Security\User;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\PasswordStrengthService;
use App\Service\ResponseCacheService;
use App\Service\SessionUserService;
use App\Service\SystemsService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/add',
        name: 'users_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'id'            => 0,
            'is_add'        => true,
            'is_self'       => false,
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/{id}/edit',
        name: 'users_edit',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'is_add'        => false,
            'is_self'       => false,
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/edit-self',
        name: 'users_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'            => 0,
            'is_add'        => false,
            'is_self'       => true,
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        bool $is_add,
        bool $is_self,
        Db $db,
        AccountRepository $account_repository,
        PasswordHasherFactoryInterface $password_hasher_factory,
        AlertService $alert_service,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        GeocodeQueue $geocode_queue,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        PasswordStrengthService $password_strength_service,
        SelectRender $select_render,
        SystemsService $systems_service,
        ResponseCacheService $response_cache_service,
        TypeaheadService $typeahead_service,
        UserCacheService $user_cache_service,
        MailAddrUserService $mail_addr_user_service,
        MailAddrSystemService $mail_addr_system_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr
    ):Response
    {
        $full_name_enabled = $config_service->get_bool('users.fields.full_name.enabled', $pp->schema());
        $postcode_enabled = $config_service->get_bool('users.fields.postcode.enabled', $pp->schema());
        $birthday_enabled = $config_service->get_bool('users.fields.birthday.enabled', $pp->schema());
        $hobbies_enabled = $config_service->get_bool('users.fields.hobbies.enabled', $pp->schema());
        $comments_enabled = $config_service->get_bool('users.fields.comments.enabled', $pp->schema());
        $admin_comments_enabled = $config_service->get_bool('users.fields.admin_comments.enabled', $pp->schema());
        $periodic_mail_enabled = $config_service->get_bool('periodic_mail.enabled', $pp->schema());

        $is_edit = !$is_add;
        $errors = [];
        $contact = [];
        $username_edit_en = false;
        $full_name_edit_en = false;
        $is_activated = false;

        if ($is_self && $is_add)
        {
            throw new BadRequestHttpException('is_add && is_self not possible');
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $template = 'users/users_';
        $template .= $is_edit ? 'edit' : 'add';
        $template .= '.html.twig';

        $transactions_enabled = $config_service->get_bool('transactions.enabled', $pp->schema());
        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
        $mail_enabled = $config_service->get_bool('mail.enabled', $pp->schema());
        $limits_enabled = $config_service->get_bool('accounts.limits.enabled', $pp->schema());
        $system_min_limit = $config_service->get_int('accounts.limits.global.min', $pp->schema());
        $system_max_limit = $config_service->get_int('accounts.limits.global.max', $pp->schema());

        $stored_min_limit = null;
        $stored_max_limit = null;

        if ($is_edit)
        {
            $stored_user = $user_cache_service->get($id, $pp->schema());
            $stored_code = $stored_user['code'];
            $stored_name = $stored_user['name'];
            $is_activated = isset($stored_user['activated_at']);

            if ($transactions_enabled && $limits_enabled)
            {
                $stored_min_limit = $account_repository->get_min_limit($id, $pp->schema());
                $stored_max_limit = $account_repository->get_max_limit($id, $pp->schema());
            }
        }

        $intersystem_code = $request->query->get('intersystem_code', '');

        $code = trim($request->request->get('code', ''));
        $name = trim($request->request->get('name', ''));
        $full_name = trim($request->request->get('full_name', ''));
        $full_name_access = $request->request->get('full_name_access', '');
        $postcode = trim($request->request->get('postcode', ''));
        $birthday = trim($request->request->get('birthday', ''));
        $hobbies = trim($request->request->get('hobbies', ''));
        $comments = trim($request->request->get('comments', ''));
        $role = $request->request->get('role', '');
        $status = $request->request->get('status', '');
        $password = trim($request->request->get('password', ''));
        $password_notify = $request->request->has('password_notify');
        $admin_comments = trim($request->request->get('admin_comments', ''));
        $min_limit = trim($request->request->get('min_limit', ''));
        $max_limit = trim($request->request->get('max_limit', ''));
        $periodic_overview_en = $request->request->has('periodic_overview_en');
        $contact = $request->request->all('contact');

        $is_owner = $is_edit
            && $su->is_owner($id);

        if ($pp->is_admin())
        {
            $username_edit_en = true;
            $full_name_edit_en = true;
        }
        else if ($is_owner)
        {
            $username_edit_en = $config_service->get_bool('users.fields.username.self_edit', $pp->schema());
            $full_name_edit_en = $config_service->get_bool('users.fields.full_name.self_edit', $pp->schema());
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
                        and u.is_active
                        and u.remote_schema is null
                        and u.remote_email is null';

                if ($is_edit)
                {
                    $mail_unique_check_sql .= ' and u.id <> ?';
                }

                $mailadr = false;

                $stmt = $db->prepare($mail_unique_check_sql);

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

                            $stmt->bindValue(1, $mailadr);

                            if ($is_edit)
                            {
                                $stmt->bindValue(2, $id);
                            }

                            $res = $stmt->executeQuery();

                            $row = $res->fetchAssociative();

                            $warning = 'Omdat deze gebruikers niet meer een uniek E-mail adres hebben zullen zij ';
                            $warning .= 'niet meer zelf hun paswoord kunnnen resetten of kunnen inloggen met ';
                            $warning .= 'E-mail adres.';

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

            $code_sql = [];
            $name_sql = [];
            $full_name_sql = [];

            $code_sql['query'] = 'select code
                from ' . $pp->schema() . '.users
                where code = ?';
            $code_sql['params'] = [$code];
            $code_sql['types'] = [\PDO::PARAM_STR];

            $name_sql['query'] = 'select name
                from ' . $pp->schema() . '.users
                where name = ?';
            $name_sql['params'] = [$name];
            $name_sql['types'] = [\PDO::PARAM_STR];

            $full_name_sql['query'] = 'select full_name
                from ' . $pp->schema() . '.users
                where full_name = ?';
            $full_name_sql['params'] = [$full_name];
            $full_name_sql['types'] = [\PDO::PARAM_STR];

            if ($is_edit)
            {
                $code_sql['query'] .= ' and id <> ?';
                $code_sql['params'][] = $id;
                $code_sql['types'][] = \PDO::PARAM_INT;
                $name_sql['query'] .= 'and id <> ?';
                $name_sql['params'][] = $id;
                $name_sql['types'][] = \PDO::PARAM_INT;
                $full_name_sql['query'] .= 'and id <> ?';
                $full_name_sql['params'][] = $id;
                $full_name_sql['types'][] = \PDO::PARAM_INT;
            }

            if (!$full_name_access && $full_name_enabled)
            {
                $errors[] = 'Vul een zichtbaarheid in voor de volledige naam.';
            }

            if ($username_edit_en)
            {
                if (!$name)
                {
                    $errors[] = 'Vul gebruikersnaam in!';
                }
                else if ($db->fetchOne($name_sql['query'], $name_sql['params'], $name_sql['types']) !== false)
                {
                    $errors[] = 'Deze gebruikersnaam is al in gebruik!';
                }
                else if (strlen($name) > 50)
                {
                    $errors[] = 'De gebruikersnaam mag maximaal 50 tekens lang zijn.';
                }
            }

            if ($full_name_edit_en && $full_name_enabled)
            {
                if (!$full_name)
                {
                    $errors[] = 'Vul de Volledige Naam in!';
                }
                else if ($db->fetchOne($full_name_sql['query'], $full_name_sql['params'], $full_name_sql['types']) !== false)
                {
                    $errors[] = 'Deze Volledige Naam is al in gebruik!';
                }
                else if (strlen($full_name) > 100)
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
                else if ($db->fetchOne($code_sql['query'], $code_sql['params'], $code_sql['types']) !== false)
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

                if ($transactions_enabled && $limits_enabled)
                {
                    if ($min_limit !== ''
                        && filter_var($min_limit, FILTER_VALIDATE_INT) === false)
                    {
                        $errors[] = 'Geef getal of niets op voor de
                            Minimum Account Limiet.';
                    }

                    if ($max_limit !== ''
                        && filter_var($max_limit, FILTER_VALIDATE_INT) === false)
                    {
                        $errors[] = 'Geef getal of niets op voor de
                            Maximum Account Limiet.';
                    }
                }
            }

            if ($birthday && $birthday_enabled)
            {
                $birthday = $date_format_service->reverse($birthday, $pp->schema());

                if ($birthday === '')
                {
                    $errors[] = 'Fout in formaat geboortedag.';
                }
            }

            if ((strlen($comments) > 100) && $comments_enabled)
            {
                $errors[] = 'Het veld Commentaar mag maximaal
                    100 tekens lang zijn.';
            }

            if ((strlen($postcode) > 6) && $postcode_enabled)
            {
                $errors[] = 'De postcode mag maximaal 6 tekens lang zijn.';
            }

            if ((strlen($hobbies) > 500) && $hobbies_enabled)
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
                $post_user = [];

                if ($periodic_mail_enabled)
                {
                    $post_user['periodic_overview_en'] = $periodic_overview_en ? 1 : 0;
                }

                if ($full_name_enabled)
                {
                    $post_user['full_name_access'] = $full_name_access;
                }

                if ($postcode_enabled)
                {
                    $post_user['postcode'] = $postcode;
                }

                if ($birthday_enabled)
                {
                    $post_user['birthday'] = $birthday === '' ? null : $birthday;
                }

                if ($hobbies_enabled)
                {
                    $post_user['hobbies'] = $hobbies;
                }

                if ($comments_enabled)
                {
                    $post_user['comments'] = $comments;
                }

                if ($is_add && !$su->is_master())
                {
                    $post_user['created_by'] = $su->id();
                }

                if (($is_add || ($is_edit && !$is_activated))
                    && $status === '1')
                {
                    $post_user['activated_at'] = gmdate('Y-m-d H:i:s');

                    $password_hasher = $password_hasher_factory->getPasswordHasher(new User());
                    $post_user['password'] = $password_hasher->hash($password);
                }
                else if ($is_add)
                {
                    $post_user['password'] = hash('sha512', sha1(random_bytes(16)));
                }

                if ($username_edit_en)
                {
                    $post_user['name'] = $name;
                }

                if ($full_name_edit_en && $full_name_enabled)
                {
                    $post_user['full_name'] = $full_name;
                }

                if ($pp->is_admin())
                {
                    $post_user['code'] = $code;
                    $post_user['role'] = $role;
                    $post_user['status'] = (int) $status;

                    if ($admin_comments_enabled)
                    {
                        $post_user['admin_comments'] = $admin_comments;
                    }
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

            if (!count($errors)
                && $pp->is_admin()
                && $transactions_enabled
                && $limits_enabled
            )
            {
                $min_to_store = $min_limit;
                $min_to_store = $min_to_store === '' ? null : (int) $min_to_store;

                if ($stored_min_limit !== $min_to_store)
                {
                    $account_repository->update_min_limit($id, $min_to_store, $su->id(), $pp->schema());
                }

                $max_to_store = $max_limit;
                $max_to_store = $max_to_store === '' ? null : (int) $max_to_store;

                if ($stored_max_limit !== $max_to_store)
                {
                    $account_repository->update_max_limit($id, $max_to_store, $su->id(), $pp->schema());
                }
            }

            if (!count($errors))
            {
                $user_cache_service->clear($id, $pp->schema());

                if ($pp->is_admin())
                {
                    $contact_types = [];

                    $stmt = $db->prepare('select abbrev, id
                        from ' . $pp->schema() . '.type_contact');

                    $res = $stmt->executeQuery();

                    while ($row = $res->fetchAssociative())
                    {
                        $contact_types[$row['abbrev']] = $row['id'];
                    }

                    $stored_contacts = [];

                    $stmt = $db->prepare('select c.id,
                            tc.abbrev, c.value, c.access
                        from ' . $pp->schema() . '.type_contact tc, ' .
                            $pp->schema() . '.contact c
                        WHERE tc.id = c.id_type_contact
                            AND c.user_id = ?');

                    $stmt->bindValue(1, $id);

                    $res = $stmt->executeQuery();

                    while ($row = $res->fetchAssociative())
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
                            if ($mail_enabled)
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

                $response_cache_service->clear_cache($pp->schema());

                $intersystems_service->clear_cache();

                if ($is_self)
                {
                    return $this->redirectToRoute('users_show_self',
                        $pp->ary());
                }

                return $this->redirectToRoute('users_show', [
                    ...$pp->ary(),
                    'id' => $id,
                ]);
            }

            $alert_service->error($errors);
        }

        if ($request->isMethod('GET'))
        {
            $code = '';
            $name = '';
            $full_name = '';
            $full_name_access = '';
            $postcode = '';
            $birthday = '';
            $hobbies = '';
            $comments = '';
            $role = 'user';
            $status = '1';
            $admin_comments = '';
            $min_limit = '';
            $max_limit = '';
            $periodic_overview_en	= true;

            $contact = $db->fetchAllAssociative('select name, abbrev,
                \'\' as value, 0 as id
                from ' . $pp->schema() . '.type_contact
                where abbrev in (\'mail\', \'adr\', \'tel\', \'gsm\')');

            if ($is_add && $intersystem_code)
            {
                if ($group = $db->fetchAssociative('select *
                    from ' . $pp->schema() . '.letsgroups
                    where localletscode = ?
                        and apimethod <> \'internal\'',
                        [$intersystem_code], [\PDO::PARAM_STR]))
                {
                    $name = $full_name = $group['groupname'];

                    if ($group['url']
                        && ($systems_service->get_schema_from_legacy_eland_origin($group['url'])))
                    {
                        $remote_schema = $systems_service->get_schema_from_legacy_eland_origin($group['url']);

                        $admin_mail = $config_service->get_ary('mail.addresses.admin', $remote_schema)[0];

                        foreach ($contact as $k => $c)
                        {
                            if ($c['abbrev'] == 'mail')
                            {
                                $contact[$k]['value'] = $admin_mail;
                                break;
                            }
                        }

                        // name from source is preferable
                        $name = $full_name = $config_service->get_str('system.name', $remote_schema);
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
                $full_name = $stored_user['full_name'] ?? '';
                $full_name_access = $stored_user['full_name_access'] ?? 'admin';
                $postcode = $stored_user['postcode'] ?? '';
                $birthday = $stored_user['birthday'] ?? '';
                $hobbies = $stored_user['hobbies'] ?? '';
                $comments = $stored_user['comments'] ?? '';
                $role = $stored_user['role'] ?? 'user';
                $status = (string) ($stored_user['status'] ?? '1');
                $admin_comments = $stored_user['admin_comments'] ?? '';
                $min_limit = $stored_min_limit ?? '';
                $max_limit = $stored_max_limit ?? '';
                $periodic_overview_en = $stored_user['periodic_overview_en'] ?? false;

                // Fetch contacts

                $contact_keys = [];

                foreach ($contact as $key => $c)
                {
                    $contact_keys[$c['abbrev']] = $key;
                }

                $stmt = $db->prepare('select tc.abbrev, c.value, tc.name, c.access, c.id
                    from ' . $pp->schema() . '.type_contact tc, ' .
                        $pp->schema() . '.contact c
                    where tc.id = c.id_type_contact
                        and c.user_id = ?');

                $stmt->bindValue(1, $id);

                $res = $stmt->executeQuery();

                while ($row = $res->fetchAssociative())
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

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        if ($pp->is_admin())
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="code" class="control-label">';
            $out .= 'Account Code';
            $out .= '</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-user"></span></span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="code" name="code" ';
            $out .= 'value="';
            $out .= self::esc($code);
            $out .= '" required maxlength="20" ';
            $out .= 'data-typeahead="';

            $out .= $typeahead_service->ini($pp)
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
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-user"></span></span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="name" name="name" ';
            $out .= 'value="';
            $out .= self::esc($name);
            $out .= '" required maxlength="50" ';
            $out .= 'data-typeahead="';

            $out .= $typeahead_service->ini($pp)
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

        if ($full_name_edit_en && $full_name_enabled)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="full_name" class="control-label">';
            $out .= 'Volledige Naam</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-user"></span></span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="full_name" name="full_name" ';
            $out .= 'value="';
            $out .= self::esc($full_name);
            $out .= '" maxlength="100">';
            $out .= '</div>';
            $out .= '<p>';
            $out .= 'Voornaam en Achternaam';
            $out .= '</p>';
            $out .= '</div>';
        }

        if ($full_name_enabled)
        {
            $out .= $item_access_service->get_radio_buttons(
                'full_name_access',
                $full_name_access,
                'full_name_access',
                false,
                'Zichtbaarheid Volledige Naam'
            );
        }

        if ($postcode_enabled)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="postcode" class="control-label">';
            $out .= 'Postcode</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-map-marker"></span></span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="postcode" name="postcode" ';
            $out .= 'value="';
            $out .= self::esc($postcode);
            $out .= '" ';
            $out .= 'maxlength="6" ';
            $out .= 'data-typeahead="';

            $out .= $typeahead_service->ini($pp)
                ->add('postcodes', [])
                ->str();

            $out .= '">';
            $out .= '</div>';
            $out .= '</div>';
        }

        if ($birthday_enabled)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="birthday" class="control-label">';
            $out .= 'Geboortedatum</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-calendar"></span></span>';
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
        }

        if ($hobbies_enabled)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="hobbies" class="control-label">';
            $out .= 'Hobbies, interesses</label>';
            $out .= '<textarea name="hobbies" id="hobbies" ';
            $out .= 'class="form-control" maxlength="500">';
            $out .= self::esc($hobbies);
            $out .= '</textarea>';
            $out .= '</div>';
        }

        if ($comments_enabled)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="comments" class="control-label">Commentaar</label>';
            $out .= '<textarea class="form-control" ';
            $out .= 'id="comments" name="comments">';
            $out .= self::esc($comments);
            $out .= '</textarea>';
            $out .= '</div>';
        }

        if ($pp->is_admin())
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="role" class="control-label">';
            $out .= 'Rechten / Rol</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-hand-paper-o"></span></span>';
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
            $out .= '<span class="input-group-addon">';
            $out .= '<span class="fa fa-star-o"></span></span>';
            $out .= '<select id="status" name="status" class="form-control">';
            $out .= $select_render->get_options(StatusCnst::LABEL_ARY, $status);
            $out .= '</select>';
            $out .= '</div>';
            $out .= '</div>';

            if (!$is_activated)
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

            if ($admin_comments_enabled)
            {
                $out .= '<div class="form-group">';
                $out .= '<label for="admin_comments" class="control-label">';
                $out .= 'Commentaar van de admin</label>';
                $out .= '<textarea name="admin_comments" id="admin_comments" ';
                $out .= 'class="form-control" maxlength="200">';
                $out .= self::esc($admin_comments);
                $out .= '</textarea>';
                $out .= 'Deze informatie is enkel zichtbaar voor de admins';
                $out .= '</div>';
            }

            if ($transactions_enabled && $limits_enabled)
            {
                $out .= '<div class="pan-sub">';

                $out .= '<h2>Limieten&nbsp;';

                if ($min_limit === '' && $max_limit === '')
                {
                    $out .= '<button class="btn btn-default" ';
                    $out .= 'title="Limieten instellen" data-toggle="collapse" ';
                    $out .= 'data-target="#limits_pan" type="button">';
                    $out .= 'Instellen</button>';
                }

                $out .= '</h2>';

                $out .= '<div id="limits_pan"';

                if ($min_limit === '' && $max_limit === '')
                {
                    $out .= ' class="collapse"';
                }

                $out .= '>';

                $out .= '<div class="form-group">';
                $out .= '<label for="min_limit" class="control-label">';
                $out .= 'Minimum Account Limiet</label>';
                $out .= '<div class="input-group">';
                $out .= '<span class="input-group-addon">';
                $out .= '<span class="fa fa-arrow-down"></span> ';
                $out .= $currency;
                $out .= '</span>';
                $out .= '<input type="number" class="form-control" ';
                $out .= 'id="min_limit" name="min_limit" ';
                $out .= 'value="';
                $out .= $min_limit ?? '';
                $out .= '">';
                $out .= '</div>';
                $out .= '<p>Vul enkel in wanneer je een individueel ';
                $out .= 'afwijkende minimum limiet wil instellen ';
                $out .= 'voor dit account. Als dit veld leeg is, ';
                $out .= 'dan is de algemeen geldende ';
                $out .= $link_render->link_no_attr('transactions_system_limits', $pp->ary(),
                    [], 'Minimum Systeemslimiet');
                $out .= ' ';
                $out .= 'van toepassing. ';

                if (!isset($system_min_limit))
                {
                    $out .= 'Er is momenteel <strong>geen</strong> algemeen ';
                    $out .= 'geledende Minimum Systeemslimiet ingesteld. ';
                }
                else
                {
                    $out .= 'De algemeen geldende ';
                    $out .= 'Minimum Systeemslimiet bedraagt ';
                    $out .= '<span class="label label-default">';
                    $out .= $system_min_limit;
                    $out .= '</span> ';
                    $out .= $currency;
                    $out .= '.';
                }

                $out .= '</p>';
                $out .= '</div>';

                $out .= '<div class="form-group">';
                $out .= '<label for="max_limit" class="control-label">';
                $out .= 'Maximum Account Limiet</label>';
                $out .= '<div class="input-group">';
                $out .= '<span class="input-group-addon">';
                $out .= '<span class="fa fa-arrow-up"></span> ';
                $out .= $currency;
                $out .= '</span>';
                $out .= '<input type="number" class="form-control" ';
                $out .= 'id="max_limit" name="max_limit" ';
                $out .= 'value="';
                $out .= $max_limit ?? '';
                $out .= '">';
                $out .= '</div>';

                $out .= '<p>Vul enkel in wanneer je een individueel ';
                $out .= 'afwijkende maximum limiet wil instellen ';
                $out .= 'voor dit account. Als dit veld leeg is, ';
                $out .= 'dan is de algemeen geldende ';
                $out .= $link_render->link_no_attr('transactions_system_limits', $pp->ary(),
                    ['tab' => 'balance'],
                    'Maximum Systeemslimiet');
                $out .= ' ';
                $out .= 'van toepassing. ';

                if (!isset($system_max_limit))
                {
                    $out .= 'Er is momenteel <strong>geen</strong> algemeen ';
                    $out .= 'geledende Maximum Systeemslimiet ingesteld. ';
                }
                else
                {
                    $out .= 'De algemeen geldende Maximum ';
                    $out .= 'Systeemslimiet bedraagt ';
                    $out .= '<span class="label label-default">';
                    $out .= $system_max_limit;
                    $out .= '</span> ';
                    $out .= $currency;
                    $out .= '.';
                }

                $out .= '</p>';

                $out .= '</div>';
                $out .= '</div>';
                $out .= '</div>';
            }

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
                $out .= '<span class="input-group-addon">';
                $out .= '<i class="fa fa-';
                $out .= ContactInputCnst::FORMAT_ARY[$c_abbrev]['fa'] ?? 'question-mark';
                $out .= '"></i>';
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

        if ($periodic_mail_enabled)
        {
            $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                '%name%'        => 'periodic_overview_en',
                '%label%'       => 'Periodieke Overzichts E-mail',
                '%attr%'        => $periodic_overview_en ? ' checked' : '',
            ]);
        }

        if ($is_self)
        {
            $out .= $link_render->btn_cancel('users_show_self', $pp->ary(),
                []);
        }
        else if ($is_edit)
        {
            $out .= $link_render->btn_cancel('users_show', $pp->ary(),
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

        return $this->render($template, [
            'content'   => $out,
            'id'        => $id,
            'is_self'   => $is_self,
        ]);
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
