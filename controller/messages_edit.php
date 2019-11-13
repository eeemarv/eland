<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Doctrine\DBAL\Connection as db;
use Monolog\Logger as monolog;
use service\alert;
use service\s3;
use controller\messages_show;
use cnst\message_type as cnst_message_type;
use cnst\access as cnst_access;

class messages_edit
{
    public function messages_add(Request $request, app $app):Response
    {
        return $this->messages_form($request, $app, 0, 'add');
    }

    public function messages_edit(Request $request, app $app, int $id):Response
    {
        return $this->messages_form($request, $app, $id, 'edit');
    }

    public function messages_form(
        Request $request,
        app $app,
        int $id,
        string $mode
    ):Response
    {
        $edit_mode = $mode === 'edit';
        $add_mode = $mode === 'add';

        $errors = [];

        $validity_days = $request->request->get('validity_days', '');
        $account_code = $request->request->get('account_code', '');
        $content = $request->request->get('content', '');
        $description = $request->request->get('description', '');
        $type = $request->request->get('type', '');
        $id_category = $request->request->get('id_category', '');
        $amount = $request->request->get('amount', '');
        $units = $request->request->get('units', '');
/*
        $deleted_images = $request->request->get('deleted_images', []);
        $uploaded_images = $request->request->get('uploaded_images', []);
*/
        $image_files = $request->request->get('image_files', '') ?: '[]';
        $access = $request->request->get('access', '');

        if (json_decode($image_files, true) === null)
        {
            $image_files = '[]';
        }

        if ($edit_mode)
        {
            $message = messages_show::get_message($app['db'], $id, $app['pp_schema']);

            $s_owner = !$app['pp_guest']
                && $app['s_system_self']
                && $app['s_id'] === $message['id_user']
                && $message['id_user'];

            if (!($app['pp_admin'] || $s_owner))
            {
                throw new AccessDeniedHttpException('Je hebt onvoldoende rechten om ' .
                    $message['label']['type_this'] . ' aan te passen.');
            }
        }

        if ($request->isMethod('POST'))
        {
            if ($error_form = $app['form_token']->get_error())
            {
                $errors[] = $error_form;
            }

            if (!isset(cnst_message_type::TO_DB[$type]))
            {
                throw new BadRequestHttpException('Ongeldig bericht type.');
            }

            if (!ctype_digit((string) $validity_days))
            {
                $errors[] = 'De geldigheid in dagen moet een positief getal zijn.';
            }

            $validity = time() + ((int) $validity_days * 86400);
            $validity =  gmdate('Y-m-d H:i:s', $validity);

            if ($app['pp_admin'])
            {
                if (!$account_code)
                {
                    $errors[] = 'Het vald Account Code is niet ingevuld.';
                }
                else
                {
                    [$account_code_expl] = explode(' ', trim($account_code));
                    $account_code_expl = trim($account_code_expl);
                    $user_id = $app['db']->fetchColumn('select id
                        from ' . $app['pp_schema'] . '.users
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
                $user_id = $app['s_id'];
            }

            if ($app['intersystems']->get_count($app['pp_schema']))
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

            if (!isset(cnst_access::TO_LOCAL[$access]))
            {
                throw new BadRequestHttpException('Ongeldige zichtbaarheid.');
            }

            if (!ctype_digit((string) $amount) && $amount !== '')
            {
                $err = 'De (richt)prijs in ';
                $err .= $app['config']->get('currency', $app['pp_schema']);
                $err .= ' moet nul of een positief getal zijn.';
                $errors[] = $err;
            }

            if (!$id_category)
            {
                $errors[] = 'Geieve een categorie te selecteren.';
            }
            else if(!$app['db']->fetchColumn('select id
                from ' . $app['pp_schema'] . '.categories
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

            if(!($app['db']->fetchColumn('select id
                from ' . $app['pp_schema'] . '.users
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
                    'msg_type'          => cnst_message_type::TO_DB[$type],
                    'id_user'           => $user_id,
                    'id_category'       => $id_category,
                    'amount'            => $amount,
                    'units'             => $units,
                    'local'             => cnst_access::TO_LOCAL[$access],
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

                $app['db']->insert($app['pp_schema'] . '.messages', $post_message);

                $id = (int) $app['db']->lastInsertId($app['pp_schema'] . '.messages_id_seq');

                self::adjust_category_stats($type,
                    (int) $id_category, 1, $app['db'], $app['pp_schema']);

                $images = json_decode($image_files, true);
                $new_image_files = [];
                $update_image_files = false;

                foreach ($images as $img)
                {
                    [$img_schema, $img_type, $img_msg_id, $img_file_name] = explode('_', $img);
                    [$img_id, $img_ext] = explode('.', $img_file_name);

                    $img_msg_id = (int) $img_msg_id;

                    if ($img_schema !== $app['pp_schema'])
                    {
                        $app['monolog']->debug('Schema does not fit image (not inserted): ' . $img,
                            ['schema' => $app['pp_schema']]);
                        $update_image_files = true;
                        continue;
                    }

                    if ($img_type !== 'm')
                    {
                        $app['monolog']->debug('Type does not fit image message (not inserted): ' . $img,
                            ['schema' => $app['pp_schema']]);

                        $update_image_files = true;
                        continue;
                    }

                    if ($img_msg_id !== $id)
                    {
                        $new_filename = $app['pp_schema'] . '_m_' . $id . '_';
                        $new_filename .= sha1(random_bytes(16)) . '.' . $img_ext;

                        $err = $app['s3']->copy($img, $new_filename);

                        if (isset($err))
                        {
                            $app['monolog']->error('message-picture renaming and storing in db ' .
                                $img .  ' not succeeded. ' . $err,
                                ['schema' => $app['pp_schema']]);
                        }
                        else
                        {
                            $app['monolog']->info('renamed ' . $img . ' to ' .
                                $new_filename, ['schema' => $app['pp_schema']]);

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

                    $app['db']->update($app['pp_schema'] . '.messages', ['image_files' => $image_files], ['id' => $id]);
                }

                $app['alert']->success('Nieuw vraag of aanbod toegevoegd.');
                $app['link']->redirect('messages_show', $app['pp_ary'], ['id' => $id]);
            }
            else if ($edit_mode && !count($errors))
            {
                $post_message['mdate'] = gmdate('Y-m-d H:i:s');

                $app['db']->beginTransaction();

                $app['db']->update($app['pp_schema'] . '.messages', $post_message, ['id' => $id]);

                if ($type !== $message['type']
                    || $id_category !== $message['id_category'])
                {
                    self::adjust_category_stats($message['type'],
                        $message['id_category'], -1, $app['db'], $app['pp_schema']);

                    self::adjust_category_stats($type,
                        (int) $id_category, 1, $app['db'], $app['pp_schema']);
                }

                $app['db']->commit();
                $app['alert']->success('Vraag/aanbod aangepast');
                $app['link']->redirect('messages_show', $app['pp_ary'], ['id' => $id]);
            }
            else if (!count($errors))
            {
                throw new HttpException(500, 'Onbekende modus');
            }

            $app['alert']->error($errors);
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

                $validity_days = (int) round((strtotime($message['validity'] . ' UTC') - time()) / 86400);
                $validity_days = $validity_days < 1 ? 0 : $validity_days;

                $user = $app['user_cache']->get($message['id_user'], $app['pp_schema']);

                $account_code = $user['letscode'] . ' ' . $user['name'];

                $access = cnst_access::FROM_LOCAL[$message['local']] ?? 'user';
                $image_files = $message['image_files'];
            }

            if ($add_mode)
            {
                $description = '';
                $content = '';
                $amount = '';
                $units = '';
                $id_category = '';
                $type = '';
                $validity_days = (int) $app['config']->get('msgs_days_default', $app['pp_schema']);
                $account_code = '';
                $access = '';
                $image_files = '[]';

                if ($app['pp_admin'])
                {
                    $account_code = $app['session_user']['letscode'] . ' ' . $app['session_user']['name'];
                 }
            }
        }

        $cat_list = ['' => ''];

        $rs = $app['db']->prepare('select id, fullname, id_parent
            from ' . $app['pp_schema'] . '.categories
            where leafnote = 1
            order by fullname');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $cat_list[$row['id']] = $row['fullname'];
        }

        $app['assets']->add([
            'fileupload',
            'sortable',
            'messages_edit_images_upload.js',
        ]);

        if ($add_mode)
        {
            $app['heading']->add('Nieuw Vraag of Aanbod toevoegen');
        }
        else
        {
            $app['heading']->add('Vraag of Aanbod aanpassen');
        }

        $app['heading']->fa('newspaper-o');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        if($app['pp_admin'])
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
            $out .= $app['typeahead']->ini($app['pp_ary'])
                ->add('accounts', ['status' => 'active'])
                ->str([
                    'filter'        => 'accounts',
                    'newuserdays'   => $app['config']->get('newuserdays', $app['pp_schema']),
                ]);
            $out .= '" ';

            $out .= 'value="';
            $out .= self::format($account_code);
            $out .= '" required>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '<div class="form-group">';
        $out .= self::get_radio(cnst_message_type::TO_LABEL, 'type', $type, true);
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
        $out .= $app['select']->get_options($cat_list, (string) $id_category);
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
        $out .= $app['config']->get('currency', $app['pp_schema']);
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
        $out .= $app['s3_url'];
        $out .= '">';
        $out .= '<div class="thumbnail">';
        $out .= '<img src="" alt="afbeelding">';
        $out .= '<div class="caption">';

        $out .= '<p><span class="btn btn-danger img-delete" role="button">';
        $out .= '<i class="fa fa-times"></i></span></p>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $images = json_decode($image_files, true);

        foreach ($images as $img)
        {
            $out .= '<div class="col-sm-3 col-md-2 thumbnail-col" ';
            $out .= 'data-file="' . $img . '">';
            $out .= '<div class="thumbnail">';
            $out .= '<img src="';
            $out .= $app['s3_url'] . $img;
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
            'form_token' 	=> $app['form_token']->get(),
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

        $out .= $app['link']->context_path($upload_img_route, $app['pp_ary'],
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
        $out .= htmlspecialchars($image_files);
        $out .= '">';

        if ($app['intersystems']->get_count($app['pp_schema']))
        {
            $out .= $app['item_access']->get_radio_buttons('access', $access, 'messages', true);
        }
        else if ($edit_mode)
        {
            $out .= '<input type="hidden" name="access" value="' . $access . '">';
        }

        if ($add_mode)
        {
            $out .= $app['link']->btn_cancel($app['r_messages'], $app['pp_ary'], []);
        }
        else
        {
            $out .= $app['link']->btn_cancel('messages_show', $app['pp_ary'], ['id' => $id]);
        }

        $btn_class = $edit_mode ? 'primary' : 'success';

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" name="zend" class="btn btn-' . $btn_class . ' btn-lg">';
        $out .= $app['form_token']->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['menu']->set('messages');

        return $app->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $app['pp_schema'],
        ]);
    }

    public static function format(string $value):string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function adjust_category_stats(
        string $message_type, int $id_category, int $adj,
        db $db, string $schema
    ):void
    {
        if ($adj === 0)
        {
            return;
        }

        $adj_str = $adj < 0 ? '- ' . abs($adj) : '+ ' . $adj;

        $column = cnst_message_type::TO_CAT_STAT_COLUMN[$message_type];

        $db->executeUpdate('update ' . $schema . '.categories
            set ' . $column . ' = ' . $column . ' ' . $adj_str . '
            where id = ?', [$id_category]);
    }

    static public function get_radio(
        array $radio_ary,
        string $name,
        string $selected,
        bool $required):string
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
