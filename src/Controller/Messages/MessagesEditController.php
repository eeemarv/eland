<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Cnst\BulkCnst;
use App\Cnst\MessageTypeCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Psr\Log\LoggerInterface;
use App\HtmlProcess\HtmlPurifier;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MessagesEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/add',
        name: 'messages_add',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'            => 0,
            'mode'          => 'add',
            'module'        => 'messages',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/messages/{id}/edit',
        name: 'messages_edit',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'mode'          => 'edit',
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        string $mode,
        Db $db,
        LoggerInterface $logger,
        AlertService $alert_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        UserCacheService $user_cache_service,
        HtmlPurifier $html_purifier,
        S3Service $s3_service,
        string $env_s3_url
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $errors = [];

        $edit_mode = $mode === 'edit';
        $add_mode = $mode === 'add';

        $expires_at_required = $config_service->get_bool('messages.fields.expires_at.required', $pp->schema());
        $expires_at_days_default = $config_service->get_int('messages.fields.expires_at.days_default', $pp->schema());
        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
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

        $service_stuff_enabled = $config_service->get_bool('messages.fields.service_stuff.enabled', $pp->schema());
        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $expires_at_switch_enabled = $config_service->get_bool('messages.fields.expires_at.switch_enabled', $pp->schema());
        $units_enabled = $config_service->get_bool('messages.fields.units.enabled', $pp->schema());

        $validity_days = $request->request->get('validity_days', '');
        $expires_at_switch = $request->request->get('expires_at_switch', '');
        $account_code = $request->request->get('account_code', '');
        $subject = $request->request->get('subject', '');
        $content = $request->request->get('content', '');
        $offer_want = $request->request->get('offer_want', '');
        $service_stuff = $request->request->get('service_stuff', '');
        $category_id = (int) $request->request->get('category_id', '');
        $amount = $request->request->get('amount', '');
        $units = $request->request->get('units', '');

        $image_files = $request->request->get('image_files', '[]');
        $access = $request->request->get('access', '');

        if (json_decode($image_files, true) === null)
        {
            $image_files = '[]';
        }
        else
        {
            $image_files_decoded = json_decode($image_files, true);
            $image_files = json_encode(array_values($image_files_decoded));
        }

        if ($edit_mode)
        {
            $message = MessagesShowController::get_message($db, $id, $pp->schema());

            if (!($pp->is_admin() || $su->is_owner($message['user_id'])))
            {
                throw new AccessDeniedHttpException('Je hebt onvoldoende rechten om ' .
                    $message['label']['offer_want_this'] . ' aan te passen.');
            }
        }
        else
        {
            $message = [];
        }

        if ($request->isMethod('POST'))
        {
            $content = $html_purifier->purify($content);
            $expires_at = null;

            if ($error_form = $form_token_service->get_error())
            {
                $errors[] = $error_form;
            }

            if (!in_array($offer_want, ['offer', 'want']))
            {
                throw new BadRequestHttpException('Ongeldig bericht type.');
            }

            if ($expires_at_enabled)
            {
                if (!$expires_at_required
                    && isset($expires_at_days_default)
                    && $expires_at_days_default > 0
                    && $expires_at_switch_enabled
                    && $add_mode)
                {
                    if ($expires_at_switch === '')
                    {
                        $errors[] = 'Vul een geldigheid in (tijdelijk of permanent).';
                    }
                    else if (!in_array($expires_at_switch, ['temporal', 'permanent']))
                    {
                        $errors[] = 'Foute waarde geldigheid (tijdelijk of permanent)';
                    }
                    else if ($expires_at_switch === 'temporal')
                    {
                        $expires_at_unix = time() + ((int) $expires_at_days_default * 86400);
                        $expires_at =  gmdate('Y-m-d H:i:s', $expires_at_unix);
                    }
                }
                else
                {
                    if ($validity_days === '')
                    {
                        if ($expires_at_required)
                        {
                            $errors[] = 'Vul een geldigheid in.';
                        }
                    }
                    else
                    {
                        if (!ctype_digit((string) $validity_days))
                        {
                            $errors[] = 'De geldigheid in dagen moet een positief getal zijn.';
                        }
                        else
                        {
                            $expires_at_unix = time() + ((int) $validity_days * 86400);
                            $expires_at =  gmdate('Y-m-d H:i:s', $expires_at_unix);
                        }
                    }
                }
            }

            if ($pp->is_admin())
            {
                if (!$account_code)
                {
                    $errors[] = 'Het veld Account Code is niet ingevuld.';
                }
                else
                {
                    [$account_code_expl] = explode(' ', trim($account_code));
                    $account_code_expl = trim($account_code_expl);
                    $user_id = $db->fetchOne('select id
                        from ' . $pp->schema() . '.users
                        where code = ?
                            and status in (1, 2)',
                        [$account_code_expl], [\PDO::PARAM_STR]);

                    if (!$user_id)
                    {
                        $errors[] = 'Ongeldige Account Code. ' . $account_code;
                    }
                }
            }
            else
            {
                $user_id = $su->id();
            }

            if ($intersystems_service->get_count($pp->schema()))
            {
                if (!$access)
                {
                    $errors[] = 'Vul een zichtbaarheid in.';
                }
            }
            else if ($add_mode)
            {
                $access = 'user';
            }

            if (!count($errors) && !in_array($access, ['user', 'guest']))
            {
                throw new BadRequestHttpException('Ongeldige zichtbaarheid.');
            }

            if (!ctype_digit((string) $amount) && $amount !== '')
            {
                $err = 'De richtprijs in ';
                $err .= $currency;
                $err .= ' moet nul of een positief getal zijn.';
                $errors[] = $err;
            }

            if ($service_stuff_enabled)
            {
                if (!$service_stuff)
                {
                    $errors[] = 'Kies "diensten" of "spullen".';
                }

                if (!in_array($service_stuff, ['service', 'stuff']))
                {
                    throw new BadRequestHttpException('Unvalid service_stuff selection');
                }
            }

            if ($category_enabled)
            {
                if (!$category_id)
                {
                    $errors[] = 'Geieve een categorie te selecteren.';
                }
                else
                {
                    $category = $db->fetchAssociative('select *
                        from ' . $pp->schema() . '.categories
                        where id = ?',
                        [$category_id],
                        [\PDO::PARAM_INT]
                    );

                    if (!$category)
                    {
                        throw new BadRequestHttpException('Category with id ' . $category_id . ' does not exist!');
                    }

                    if (($category['left_id'] + 1) !== $category['right_id'])
                    {
                        throw new BadRequestException('A category containing sub-categories can not contain messages. (id: ' . $category_id . ')');
                    }
                }
            }

            if (!$subject)
            {
                $errors[] = 'De titel ontbreekt.';
            }

            if(strlen($subject) > 200)
            {
                $errors[] = 'De titel mag maximaal 200 tekens lang zijn.';
            }

            if(strlen($content) > 2000)
            {
                $errors[] = 'De omschrijving mag maximaal 2000 tekens lang zijn.';
            }

            if(strlen($units) > 15)
            {
                $errors[] = '"Per (uur, stuk, ...)" mag maximaal 15 tekens lang zijn.';
            }

            if(!($db->fetchOne('select id
                from ' . $pp->schema() . '.users
                where id = ? and status in (1, 2)',
                [$user_id], [\PDO::PARAM_INT])))
            {
                $errors[] = 'Gebruiker bestaat niet of is niet actief.';
            }

            if (!count($errors))
            {
                $post_message = [
                    'subject'           => $subject,
                    'content'           => $content,
                    'offer_want'        => $offer_want,
                    'user_id'           => $user_id,
                    'access'            => $access,
                    'image_files'       => $image_files,
                ];

                if ($service_stuff_enabled)
                {
                    $post_message['service_stuff'] = $service_stuff;
                }

                if ($category_enabled)
                {
                    $post_message['category_id'] = $category_id;
                }

                if ($expires_at_enabled)
                {
                    $post_message['expires_at'] = $expires_at;
                }

                if ($units_enabled)
                {
                    $post_message['amount'] = $amount;
                    $post_message['units'] = $units;

                    if (empty($amount))
                    {
                        unset($post_message['amount']);
                    }
                }
            }

            if ($add_mode && !count($errors))
            {
                if (!$su->is_master())
                {
                    $post_message['created_by'] = $su->id();
                }

                $db->insert($pp->schema() . '.messages', $post_message);

                $id = (int) $db->lastInsertId($pp->schema() . '.messages_id_seq');

                $logger->debug('#msg add message with id ' . $id . ' ' . json_encode($post_message), ['schema' => $pp->schema()]);

                $images = array_values(json_decode($image_files, true) ?? []);
                $new_image_files = [];
                $update_image_files = false;

                foreach ($images as $img)
                {
                    [$img_schema, $img_type, $img_msg_id, $img_file_name] = explode('_', $img);
                    [$img_id, $img_ext] = explode('.', $img_file_name);

                    $img_msg_id = (int) $img_msg_id;

                    if ($img_schema !== $pp->schema())
                    {
                        $logger->debug('Schema does not fit image (not inserted): ' . $img,
                            ['schema' => $pp->schema()]);
                        $update_image_files = true;
                        continue;
                    }

                    if ($img_type !== 'm')
                    {
                        $logger->debug('Type does not fit image message (not inserted): ' . $img,
                            ['schema' => $pp->schema()]);

                        $update_image_files = true;
                        continue;
                    }

                    if ($img_msg_id !== $id)
                    {
                        $new_filename = $pp->schema() . '_m_' . $id . '_';
                        $new_filename .= sha1(random_bytes(16)) . '.' . $img_ext;

                        $err = $s3_service->copy($img, $new_filename);

                        if (isset($err))
                        {
                            $logger->error('message-picture renaming and storing in db ' .
                                $img .  ' not succeeded. ' . $err,
                                ['schema' => $pp->schema()]);
                        }
                        else
                        {
                            $logger->info('renamed ' . $img . ' to ' .
                                $new_filename, ['schema' => $pp->schema()]);

                            $new_image_files[] = $new_filename;
                        }

                        $update_image_files = true;
                        continue;
                    }

                    $new_image_files[] = $img;
                }

                if ($update_image_files)
                {
                    $image_files = json_encode(array_values($new_image_files));

                    $db->update($pp->schema() . '.messages', ['image_files' => $image_files], ['id' => $id]);
                }

                $alert_service->success('Nieuw vraag of aanbod toegevoegd.');

                return $this->redirectToRoute('messages_show', array_merge($pp->ary(), ['id' => $id]));
            }
            else if ($edit_mode && !count($errors))
            {
                $db->update($pp->schema() . '.messages', $post_message, ['id' => $id]);
                $logger->debug('#msg update message with id ' . $id . ' ' . json_encode($post_message), ['schema' => $pp->schema()]);

                $alert_service->success('Vraag/aanbod aangepast');

                return $this->redirectToRoute('messages_show', array_merge($pp->ary(), ['id' => $id]));
            }
            else if (!count($errors))
            {
                throw new HttpException(500, 'Onbekende modus');
            }

            $alert_service->error($errors);
        }

        if ($request->isMethod('GET'))
        {
            if ($edit_mode)
            {
                $content = $message['content'];
                $subject = $message['subject'];
                $amount = $message['amount'] ?? '';
                $units = $message['units'] ?? '';
                $category_id = $message['category_id'] ?? '';
                $offer_want = $message['offer_want'] ?? '';
                $service_stuff = $message['service_stuff'] ?? '';
                $access = $message['access'];
                $image_files = $message['image_files'];

                if (isset($message['expires_at']))
                {
                    $validity_days = (int) round((strtotime($message['expires_at'] . ' UTC') - time()) / 86400);
                    $validity_days = $validity_days < 1 ? 0 : $validity_days;
                }
                else
                {
                    $validity_days = '';
                }

                $user = $user_cache_service->get($message['user_id'], $pp->schema());

                $account_code = $user['code'] . ' ' . $user['name'];
            }

            if ($add_mode)
            {
                $content = '';
                $subject = '';
                $amount = '';
                $units = '';
                $category_id = '';
                $offer_want = '';
                $validity_days = $expires_at_days_default ?? '';
                $expires_at_switch = '';
                $account_code = '';
                $access = '';
                $image_files = '[]';

                if ($pp->is_admin())
                {
                    $uid = (int) $request->query->get('uid');

                    if ($uid > 0)
                    {
                        $uid_user = $user_cache_service->get($uid, $pp->schema());
                    }

                    if (!isset($uid_user) || !$uid_user)
                    {
                        $uid_user = $su->user();
                    }

                    $account_code = $uid_user['code'] ?? '';
                    $account_code .= ' ';
                    $account_code .= $uid_user['name'] ?? '';
                    $account_code = trim($account_code);
                }
            }
        }

        if ($category_enabled)
        {
            $cat_ary = [
                '' => [
                    'name'  => '',
                    'count' => 0,
                ],
            ];

            $rs = $db->prepare('select c.*, count(m.*)
                from ' . $pp->schema() . '.categories c
                left join ' . $pp->schema() . '.messages m
                    on m.category_id = c.id
                group by c.id
                order by c.left_id asc');

            $rs->execute();

            while ($row = $rs->fetch())
            {
                $cat_id = $row['id'];
                $parent_id = $row['parent_id'];

                if (isset($parent_id))
                {
                    $cat_ary[$parent_id]['children'][$cat_id] = $row;
                    continue;
                }

                $cat_ary[$cat_id] = $row;
                $cat_ary[$cat_id]['children'] = [];
            }
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        if($pp->is_admin())
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="account_code" class="control-label">';
            $out .= '<span class="label label-info">Admin</span> ';
            $out .= 'Gebruiker</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<i class="fa fa-user"></i>';
            $out .= '</span>';
            $out .= '<input type="text" class="form-control" ';
            $out .= 'id="account_code" name="account_code" ';

            $out .= 'data-typeahead="';
            $out .= $typeahead_service->ini($pp)
                ->add('accounts', ['status' => 'active'])
                ->str([
                    'filter'        => 'accounts',
                    'new_users_days'        => $new_users_days,
                    'show_new_status'       => $show_new_status,
                    'show_leaving_status'   => $show_leaving_status,
                ]);
            $out .= '" ';

            $out .= 'value="';
            $out .= self::format($account_code);
            $out .= '" required>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '<div class="form-group">';
        $out .= '<div class="custom-radio">';

        foreach (MessageTypeCnst::OFFER_WANT_TPL_ARY as $key => $render_data)
        {
            $out .= strtr(BulkCnst::TPL_RADIO_INLINE,[
                '%name%'    => 'offer_want',
                '%value%'   => $key,
                '%attr%'    => ' required' . ($offer_want === $key ? ' checked' : ''),
                '%label%'   => '<span class="btn btn-' . $render_data['btn_class'] . '">' . $render_data['label'] . '</span>',
            ]);
        }

        $out .= '</div>';
        $out .= '</div>';

        if ($service_stuff_enabled)
        {
            $out .= '<div class="form-group">';
            $out .= '<div class="custom-radio">';

            foreach (MessageTypeCnst::SERVICE_STUFF_TPL_ARY as $key => $render_data)
            {
                if ($key === 'null-service-stuff')
                {
                    continue;
                }

                $out .= strtr(BulkCnst::TPL_RADIO_INLINE,[
                    '%name%'    => 'service_stuff',
                    '%value%'   => $key,
                    '%attr%'    => ' required' . ($service_stuff === $key ? ' checked' : ''),
                    '%label%'   => '<span class="btn btn-' . $render_data['btn_class'] . '">' . $render_data['label'] . '</span>',
                ]);
            }

            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '<div class="form-group">';
        $out .= '<label for="subject" class="control-label">';
        $out .= 'Titel</label>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="subject" name="subject" ';
        $out .= 'value="';
        $out .= self::format((string) $subject);
        $out .= '" maxlength="200" required>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="content" class="control-label">';
        $out .= 'Omschrijving</label>';
        $out .= '<textarea name="content" ';
        $out .= 'class="form-control summernote" id="content" rows="4" maxlength="2000">';
        $out .= self::format((string) $content);
        $out .= '</textarea>';
        $out .= '</div>';

        if ($category_enabled)
        {
            $out .= '<div class="form-group">';
            $out .= '<label for="category_id" class="control-label">';
            $out .= 'Categorie</label>';
            $out .= '<div class="input-group">';
            $out .= '<span class="input-group-addon">';
            $out .= '<i class="fa fa-clone"></i>';
            $out .= '</span>';
            $out .= '<select name="category_id" id="category_id" class="form-control" required>';

            foreach ($cat_ary as $cat_id => $cat_data)
            {
                if (isset($cat_data['children']) && count($cat_data['children']))
                {
                    $out .= '<optgroup label="';
                    $out .= $cat_data['name'];
                    $out .= '">';

                    foreach ($cat_data['children'] as $sub_cat_id => $sub_cat_data)
                    {
                        $out .= '<option value="';
                        $out .= $sub_cat_id;
                        $out .= '"';
                        $out .= $sub_cat_id === $category_id ? '  selected' : '';
                        $out .= '>';
                        $out .= $sub_cat_data['name'];
                        $out .= $sub_cat_data['count'] ? ' (' . $sub_cat_data['count'] . ')' : '';
                        $out .= '</option>';
                    }
                    $out .= '</optgroup>';
                    continue;
                }

                $out .= '<option value="';
                $out .= $cat_id;
                $out .= '"';
                $out .= $cat_id === $category_id ? ' selected' : '';
                $out .= '>';
                $out .= $cat_data['name'];
                $out .= $cat_data['count'] ? ' (' . $cat_data['count'] . ')' : '';
                $out .= '</option>';
            }

            $out .= '</select>';
            $out .= '</div>';
            $out .= '</div>';
        }

        if ($expires_at_enabled)
        {
            if (!$expires_at_required
                && isset($expires_at_days_default)
                && $expires_at_days_default > 0
                && $expires_at_switch_enabled
                && $add_mode)
            {
                $expires_at_switch_tpl_ary = [
                    'temporal'  => 'Tijdelijk',
                    'permanent' => 'Permanent',
                ];

                $out .= '<div class="form-group">';
                $out .= '<label for="expires_at_switch" ';
                $out .= 'class="control-label">Geldigheid</label>';
                $out .= '<div class="custom-radio">';
                foreach ($expires_at_switch_tpl_ary as $val => $lbl)
                {
                    $class = 'btn btn-default';
                    $class .= $val === 'permanent' ? '-2' : '';

                    $out .= strtr(BulkCnst::TPL_RADIO_INLINE,[
                        '%name%'    => 'expires_at_switch',
                        '%value%'   => $val,
                        '%attr%'    => ' required' . ($expires_at_switch === $val ? ' checked' : ''),
                        '%label%'    => '<span class="' . $class . '">' . $lbl . '</span>',
                    ]);
                }
                $out .= '</div>';
                $out .= '<p>Een tijdelijk aanbod of vraag blijft <strong>';
                $out .= $expires_at_days_default;
                $out .= '</strong> dagen geldig en kan nog verlengd worden.</p>';
                $out .= '</div>';
            }
            else
            {
                $attr_val = ' min="1"';
                $attr_val .= $expires_at_required ?  ' required' : '';

                $explain_val = $expires_at_required ? '' : 'Vul niets in voor een permanent vraag of aanbod.';

                $out .= strtr(BulkCnst::TPL_INPUT_ADDON, [
                    '%name%'    => 'validity_days',
                    '%value%'   => self::format((string) $validity_days),
                    '%type%'    => 'number',
                    '%label%'   => 'Geldigheid',
                    '%addon%'   => 'dagen',
                    '%explain%' => $explain_val,
                    '%attr%'    => $attr_val,
                ]);
            }
        }

        if ($units_enabled)
        {
            $out .= strtr(BulkCnst::TPL_INPUT_ADDON, [
                '%name%'    => 'amount',
                '%value%'   => self::format((string) $amount),
                '%type%'    => 'number',
                '%label%'   => 'Richtprijs',
                '%addon%'   => $currency,
                '%explain%' => '',
                '%attr%'    => ' min="0"',
            ]);

            $out .= strtr(BulkCnst::TPL_INPUT_FA, [
                '%name%'    => 'units',
                '%value%'   => self::format((string) $units),
                '%type%'    => 'text',
                '%label%'   => 'Per eenheid (uur, stuk, ...)',
                '%fa%'      => 'hourglass-half',
                '%explain%' => '',
                '%attr%'    => '',
            ]);
        }

        $out .= '<div class="form-group">';
        $out .= '<label for="fileupload" class="control-label">';
        $out .= 'Afbeeldingen</label>';
        $out .= '<div class="row sortable">';

        $out .= '<div class="col-sm-3 col-md-2 thumbnail-col hidden" ';
        $out .= 'id="thumbnail_model" ';
        $out .= 'data-s3-url="';
        $out .= $env_s3_url;
        $out .= '">';
        $out .= '<div class="thumbnail">';
        $out .= '<img src="" alt="afbeelding">';
        $out .= '<div class="caption">';

        $out .= '<p><span class="btn btn-danger img-delete" role="button">';
        $out .= '<i class="fa fa-times"></i></span></p>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $images = array_values(json_decode($image_files ?? '[]', true));

        foreach ($images as $img)
        {
            $out .= '<div class="col-sm-3 col-md-2 thumbnail-col" ';
            $out .= 'data-file="' . $img . '">';
            $out .= '<div class="thumbnail">';
            $out .= '<img src="';
            $out .= $env_s3_url . $img;
            $out .= '" alt="afbeelding">';
            $out .= '<div class="caption">';

            $out .= '<p><span class="btn btn-danger img-delete" role="button">';
            $out .= '<i class="fa fa-times"></i></span></p>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '</div>';

        $upload_img_param = [
            'form_token' 	=> $form_token_service->get(),
        ];

        if ($edit_mode)
        {
            $upload_img_param['id'] = $id;
            $upload_img_route = 'messages_edit_images_upload';
        }
        else
        {
            $upload_img_route = 'messages_add_images_upload';
        }

        $out .= '<span class="btn btn-default fileinput-button">';
        $out .= '<i class="fa fa-plus" id="img_plus"></i> Opladen';
        $out .= '<input type="file" name="images[]" ';
        $out .= 'data-url="';

        $out .= $link_render->context_path($upload_img_route, $pp->ary(),
            $upload_img_param);

        $out .= '" multiple ';
        $out .= 'data-fileupload ';
        $out .= 'data-message-file-type-not-allowed="Bestandstype is niet toegelaten." ';
        $out .= 'data-message-max-file-size="Het bestand is te groot." ';
        $out .= 'data-message-min-file-size="Het bestand is te klein." ';
        $out .= 'data-message-uploaded-bytes="Het bestand is te groot." ';
        $out .= '></span>&nbsp;';

        $out .= '<p>Toegestane formaten: jpg/jpeg, png, gif, svg. ';
        $out .= 'Je kan ook afbeeldingen hierheen ';
        $out .= 'verslepen. ';
        $out .= 'De volgorde kan veranderd worden door te verslepen.</p>';
        $out .= '</div>';

        $out .= '<input type="hidden" name="image_files" value="';
        $out .= htmlspecialchars($image_files ?? '[]');
        $out .= '">';

        if ($intersystems_service->get_count($pp->schema()))
        {
            $out .= $item_access_service->get_radio_buttons('access', $access, 'messages', true);
        }
        else if ($edit_mode)
        {
            $out .= '<input type="hidden" name="access" value="' . $access . '">';
        }

        if ($add_mode)
        {
            $out .= $link_render->btn_cancel($vr->get('messages'), $pp->ary(), []);
        }
        else
        {
            $out .= $link_render->btn_cancel('messages_show', $pp->ary(), ['id' => $id]);
        }

        $btn_class = $edit_mode ? 'primary' : 'success';

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" name="zend" class="btn btn-' . $btn_class . ' btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $template = 'messages/messages_';
        $template .= $edit_mode ? 'edit' : 'add';
        $template .= '.html.twig';

        return $this->render($template, [
            'content'   => $out,
            'message'   => $message,
        ]);
    }

    public static function format(string $value):string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    static public function get_radio(
        array $radio_ary,
        string $name,
        string $selected,
        bool $required
    ):string
    {
        $out = '<div class="custom-radio">';

        foreach ($radio_ary as $value => $label)
        {
            $attr = (string) $value === $selected ? ' checked' : '';
            $attr .= $required ? ' required' : '';

            $out .= strtr(BulkCnst::TPL_RADIO_INLINE,[
                '%name%'    => $name,
                '%value%'   => $value,
                '%attr%'    => $attr,
                '%label%'   => '<span class="btn btn-default">' . $label . '</span>',
            ]);
        }

        $out .= '</div>';

        return $out;
    }
}
