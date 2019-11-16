<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Psr\Log\LoggerInterface;
use App\Controller\MessagesShowController;
use App\Cnst\MessageTypeCnst;
use App\Cnst\AccessCnst;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;

class MessagesEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        LoggerInterface $logger,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        SelectRender $select_render,
        LinkRender $link_render,
        MenuService $menu_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        UserCacheService $user_cache_service,
        S3Service $s3_service,
        string $env_s3_url
    ):Response
    {
        $content = self::messages_form(
            $request,
            $id,
            'edit',
            $db,
            $logger,
            $alert_service,
            $assets_service,
            $config_service,
            $form_token_service,
            $heading_render,
            $intersystems_service,
            $item_access_service,
            $select_render,
            $link_render,
            $menu_service,
            $typeahead_service,
            $pp,
            $su,
            $vr,
            $user_cache_service,
            $s3_service,
            $env_s3_url
        );

        return $this->render('base/navbar.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
        ]);
    }

    public static function messages_form(
        Request $request,
        int $id,
        string $mode,
        Db $db,
        LoggerInterface $logger,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        SelectRender $select_render,
        LinkRender $link_render,
        MenuService $menu_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        UserCacheService $user_cache_service,
        S3Service $s3_service,
        string $env_s3_url
    ):string
    {
        $errors = [];

        $edit_mode = $mode === 'edit';
        $add_mode = $mode === 'add';

        $validity_days = $request->request->get('validity_days', '');
        $account_code = $request->request->get('account_code', '');
        $content = $request->request->get('content', '');
        $description = $request->request->get('description', '');
        $type = $request->request->get('type', '');
        $id_category = $request->request->get('id_category', '');
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

            $s_owner = !$pp->is_guest()
                && $su->is_system_self()
                && $su->id() === $message['id_user']
                && $message['id_user'];

            if (!($pp->is_admin() || $s_owner))
            {
                throw new AccessDeniedHttpException('Je hebt onvoldoende rechten om ' .
                    $message['label']['type_this'] . ' aan te passen.');
            }
        }

        if ($request->isMethod('POST'))
        {
            if ($error_form = $form_token_service->get_error())
            {
                $errors[] = $error_form;
            }

            if (!isset(MessageTypeCnst::TO_DB[$type]))
            {
                throw new BadRequestHttpException('Ongeldig bericht type.');
            }

            if (!ctype_digit((string) $validity_days))
            {
                $errors[] = 'De geldigheid in dagen moet een positief getal zijn.';
            }

            $validity = time() + ((int) $validity_days * 86400);
            $validity =  gmdate('Y-m-d H:i:s', $validity);

            if ($pp->is_admin())
            {
                if (!$account_code)
                {
                    $errors[] = 'Het vald Account Code is niet ingevuld.';
                }
                else
                {
                    [$account_code_expl] = explode(' ', trim($account_code));
                    $account_code_expl = trim($account_code_expl);
                    $user_id = $db->fetchColumn('select id
                        from ' . $pp->schema() . '.users
                        where letscode = ?
                            and status in (1, 2)', [$account_code_expl]);

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
                $err = 'De (richt)prijs in ';
                $err .= $config_service->get('currency', $pp->schema());
                $err .= ' moet nul of een positief getal zijn.';
                $errors[] = $err;
            }

            if (!$id_category)
            {
                $errors[] = 'Geieve een categorie te selecteren.';
            }
            else if(!$db->fetchColumn('select id
                from ' . $pp->schema() . '.categories
                where id = ?', [$id_category]))
            {
                throw new BadRequestHttpException('Categorie bestaat niet!');
            }

            if (!$content)
            {
                $errors[] = 'De titel ontbreekt.';
            }

            if(strlen($content) > 200)
            {
                $errors[] = 'De titel mag maximaal 200 tekens lang zijn.';
            }

            if(strlen($description) > 2000)
            {
                $errors[] = 'De omschrijving mag maximaal 2000 tekens lang zijn.';
            }

            if(strlen($units) > 15)
            {
                $errors[] = '"Per (uur, stuk, ...)" mag maximaal 15 tekens lang zijn.';
            }

            if(!($db->fetchColumn('select id
                from ' . $pp->schema() . '.users
                where id = ? and status in (1, 2)', [$user_id])))
            {
                $errors[] = 'Gebruiker bestaat niet of is niet actief.';
            }

            if (!count($errors))
            {
                $post_message = [
                    'validity'          => $validity,
                    'content'           => $content,
                    '"Description"'     => $description,
                    'msg_type'          => MessageTypeCnst::TO_DB[$type],
                    'id_user'           => $user_id,
                    'id_category'       => $id_category,
                    'amount'            => $amount,
                    'units'             => $units,
                    'access'            => $access,
                    'image_files'       => $image_files,
                ];

                if (empty($amount))
                {
                    unset($post_message['amount']);
                }
            }

            if ($add_mode && !count($errors))
            {
                $post_message['cdate'] = gmdate('Y-m-d H:i:s');

                $db->insert($pp->schema() . '.messages', $post_message);

                $id = (int) $db->lastInsertId($pp->schema() . '.messages_id_seq');

                self::adjust_category_stats(
                    $type,
                    (int) $id_category,
                    1,
                    $db,
                    $pp->schema()
                );

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
                $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);
            }
            else if ($edit_mode && !count($errors))
            {
                $post_message['mdate'] = gmdate('Y-m-d H:i:s');

                $db->beginTransaction();

                $db->update($pp->schema() . '.messages', $post_message, ['id' => $id]);

                if ($type !== $message['type']
                    || $id_category !== $message['id_category'])
                {
                    self::adjust_category_stats($message['type'],
                        $message['id_category'], -1, $db, $pp->schema());

                    self::adjust_category_stats($type,
                        (int) $id_category, 1, $db, $pp->schema());
                }

                $db->commit();
                $alert_service->success('Vraag/aanbod aangepast');
                $link_render->redirect('messages_show', $pp->ary(), ['id' => $id]);
            }
            else if (!count($errors))
            {
                throw new HttpException(500, 'Onbekende modus');
            }

            $alert_service->error($errors);
        }
        else if ($request->isMethod('GET'))
        {
            if ($edit_mode)
            {
                $description = $message['Description'];
                $content = $message['content'];
                $amount = $message['amount'];
                $units = $message['units'];
                $id_category = $message['id_category'];
                $type = $message['type'];
                $access = $message['access'];
                $image_files = $message['image_files'];

                $validity_days = (int) round((strtotime($message['validity'] . ' UTC') - time()) / 86400);
                $validity_days = $validity_days < 1 ? 0 : $validity_days;

                $user = $user_cache_service->get($message['id_user'], $pp->schema());

                $account_code = $user['letscode'] . ' ' . $user['name'];
            }

            if ($add_mode)
            {
                $description = '';
                $content = '';
                $amount = '';
                $units = '';
                $id_category = '';
                $type = '';
                $validity_days = (int) $config_service->get('msgs_days_default', $pp->schema());
                $account_code = '';
                $access = '';
                $image_files = '[]';

                if ($pp->is_admin())
                {
                    $account_code = $su->user()['letscode'] ?? '';
                    $account_code .= ' ';
                    $account_code .= $su->user()['name'] ?? '';
                    $account_code = trim($account_code);
                }
            }
        }

        $cat_list = ['' => ''];

        $rs = $db->prepare('select id, fullname, id_parent
            from ' . $pp->schema() . '.categories
            where leafnote = 1
            order by fullname');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $cat_list[$row['id']] = $row['fullname'];
        }

        $assets_service->add([
            'fileupload',
            'sortable',
            'messages_edit_images_upload.js',
        ]);

        if ($add_mode)
        {
            $heading_render->add('Nieuw Vraag of Aanbod toevoegen');
        }
        else
        {
            $heading_render->add('Vraag of Aanbod aanpassen');
        }

        $heading_render->fa('newspaper-o');

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
            $out .= $typeahead_service->ini($pp->ary())
                ->add('accounts', ['status' => 'active'])
                ->str([
                    'filter'        => 'accounts',
                    'newuserdays'   => $config_service->get('newuserdays', $pp->schema()),
                ]);
            $out .= '" ';

            $out .= 'value="';
            $out .= self::format($account_code);
            $out .= '" required>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '<div class="form-group">';
        $out .= self::get_radio(MessageTypeCnst::TO_LABEL, 'type', $type, true);
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="content" class="control-label">';
        $out .= 'Titel</label>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="content" name="content" ';
        $out .= 'value="';
        $out .= self::format($content);
        $out .= '" maxlength="200" required>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="description" class="control-label">';
        $out .= 'Omschrijving</label>';
        $out .= '<textarea name="description" class="form-control" id="description" rows="4" maxlength="2000">';
        $out .= self::format($description);
        $out .= '</textarea>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="id_category" class="control-label">';
        $out .= 'Categorie</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-clone"></i>';
        $out .= '</span>';
        $out .= '<select name="id_category" id="id_category" class="form-control" required>';
        $out .= $select_render->get_options($cat_list, (string) $id_category);
        $out .= "</select>";
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="validity_days" class="control-label">';
        $out .= 'Geldigheid</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= 'dagen';
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" ';
        $out .= 'id="validity_days" name="validity_days" min="1" ';
        $out .= 'value="';
        $out .= self::format((string) $validity_days);
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="amount" class="control-label">';
        $out .= 'Aantal';
        $out .= '</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= $config_service->get('currency', $pp->schema());
        $out .= '</span>';
        $out .= '<input type="number" class="form-control" ';
        $out .= 'id="amount" name="amount" min="0" ';
        $out .= 'value="';
        $out .= self::format((string) $amount);
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="units" class="control-label">';
        $out .= 'Per (uur, stuk, ...)</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-hourglass-half"></span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="units" name="units" ';
        $out .= 'value="';
        $out .= self::format($units);
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

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
        $out .= '<input id="fileupload" type="file" name="images[]" ';
        $out .= 'data-url="';

        $out .= $link_render->context_path($upload_img_route, $pp->ary(),
            $upload_img_param);

        $out .= '" ';
        $out .= 'data-data-type="json" data-auto-upload="true" ';
        $out .= 'data-accept-file-types="/(\.|\/)(jpe?g|png|gif)$/i" ';
        $out .= 'data-max-file-size="999000" ';
        $out .= 'multiple></span>&nbsp;';

        $out .= '<p>Toegestane formaten: jpg/jpeg, png, gif. ';
        $out .= 'Je kan ook afbeeldingen hierheen ';
        $out .= 'verslepen.</p>';
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

        $menu_service->set('messages');

        return $out;
    }

    public static function format(string $value):string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function adjust_category_stats(
        string $message_type,
        int $id_category,
        int $adj,
        Db $db,
        string $schema
    ):void
    {
        if ($adj === 0)
        {
            return;
        }

        $adj_str = $adj < 0 ? '- ' . abs($adj) : '+ ' . $adj;

        $column = MessageTypeCnst::TO_CAT_STAT_COLUMN[$message_type];

        $db->executeUpdate('update ' . $schema . '.categories
            set ' . $column . ' = ' . $column . ' ' . $adj_str . '
            where id = ?', [$id_category]);
    }

    static public function get_radio(
        array $radio_ary,
        string $name,
        string $selected,
        bool $required
    ):string
    {
        $out = '';

        foreach ($radio_ary as $value => $label)
        {
            $out .= '<label class="radio-inline">';
            $out .= '<input type="radio" name="' . $name . '" ';
            $out .= 'value="' . $value . '"';
            $out .= (string) $value === $selected ? ' checked' : '';
            $out .= $required ? ' required' : '';
            $out .= '>&nbsp;';
            $out .= '<span class="btn btn-default">';
            $out .= $label;
            $out .= '</span>';
            $out .= '</label>';
        }

        return $out;
    }
}
