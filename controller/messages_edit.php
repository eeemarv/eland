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
        $deleted_images = $request->request->get('deleted_images', []);
        $uploaded_images = $request->request->get('uploaded_images', []);
        $access = $request->request->get('access', []);

        if ($edit_mode)
        {
            $message = messages_show::get_message($app['db'], $id, $app['tschema']);

            $s_owner = !$app['s_guest']
                && $app['s_system_self']
                && $app['s_id'] === $message['id_user']
                && $message['id_user'];

            if (!($app['s_admin'] || $s_owner))
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

            if ($app['s_admin'])
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
                        from ' . $app['tschema'] . '.users
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

            if ($app['intersystems']->get_count($app['tschema']))
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
                $err .= $app['config']->get('currency', $app['tschema']);
                $err .= ' moet nul of een positief getal zijn.';
                $errors[] = $err;
            }

            if (!$id_category)
            {
                $errors[] = 'Geieve een categorie te selecteren.';
            }
            else if(!$app['db']->fetchColumn('select id
                from ' . $app['tschema'] . '.categories
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
                from ' . $app['tschema'] . '.users
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
                ];

                if (empty($amount))
                {
                    unset($post_message['amount']);
                }
            }

            if ($add_mode && !count($errors))
            {
                $post_message['cdate'] = gmdate('Y-m-d H:i:s');

                $app['db']->insert($app['tschema'] . '.messages', $post_message);

                $id = (int) $app['db']->lastInsertId($app['tschema'] . '.messages_id_seq');

                self::adjust_category_stats($type,
                    (int) $id_category, 1, $app['db'], $app['tschema']);

                self::add_images_to_db($uploaded_images, $id, true,
                    $app['db'], $app['monolog'], $app['alert'],
                    $app['s3'], $app['tschema']);

                $app['alert']->success('Nieuw vraag of aanbod toegevoegd.');
                $app['link']->redirect('messages_show', $app['pp_ary'], ['id' => $id]);
            }
            else if ($edit_mode && !count($errors))
            {
                $post_message['mdate'] = gmdate('Y-m-d H:i:s');

                $app['db']->beginTransaction();

                $app['db']->update($app['tschema'] . '.messages', $post_message, ['id' => $id]);

                if ($type !== $message['type']
                    || $id_category !== $message['id_category'])
                {
                    self::adjust_category_stats($message['type'],
                        $message['id_category'], -1, $app['db'], $app['tschema']);

                    self::adjust_category_stats($type,
                        (int) $id_category, 1, $app['db'], $app['tschema']);
                }

                self::delete_images_from_db($deleted_images, $id,
                    $app['db'], $app['monolog'], $app['tschema']);

                self::add_images_to_db($uploaded_images, $id, false,
                    $app['db'], $app['monolog'], $app['alert'],
                    $app['s3'], $app['tschema']);

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

                $user = $app['user_cache']->get($message['id_user'], $app['tschema']);

                $account_code = $user['letscode'] . ' ' . $user['name'];

                $access = cnst_access::FROM_LOCAL[$message['local']] ?? 'user';
            }

            if ($add_mode)
            {
                $description = '';
                $content = '';
                $amount = '';
                $units = '';
                $id_category = '';
                $type = '';
                $validity_days = (int) $app['config']->get('msgs_days_default', $app['tschema']);
                $account_code = '';
                $access = '';

                if ($app['s_admin'])
                {
                    $account_code = $app['session_user']['letscode'] . ' ' . $app['session_user']['name'];
                 }
            }
        }

        $render_images = [];

        if ($edit_mode)
        {
            $st = $app['db']->prepare('select "PictureFile"
                from ' . $app['tschema'] . '.msgpictures
                where msgid = ?', [$id]);

            $st->bindValue(1, $id);
            $st->execute();

            while($row = $st->fetch())
            {
                $render_images[$row['PictureFile']] = true;
            }
        }

        foreach ($deleted_images as $del_img)
        {
            unset($render_images[$del_img]);
        }

        foreach ($uploaded_images as $upl_img)
        {
            $render_images[$upl_img] = true;
        }

        $cat_list = ['' => ''];

        $rs = $app['db']->prepare('select id, fullname, id_parent
            from ' . $app['tschema'] . '.categories
            where leafnote = 1
            order by fullname');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $cat_list[$row['id']] = $row['fullname'];
        }

        $app['assets']->add([
            'fileupload',
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

        if($app['s_admin'])
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
                    'newuserdays'   => $app['config']->get('newuserdays', $app['tschema']),
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
        $out .= $app['config']->get('currency', $app['tschema']);
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
        $out .= '<div class="row">';

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

        foreach ($render_images as $img => $dummy)
        {
            $out .= '<div class="col-sm-3 col-md-2 thumbnail-col">';
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
        $out .= 'data-accept-file-types="/(\.|\/)(jpe?g)$/i" ';
        $out .= 'data-max-file-size="999000" ';
        $out .= 'multiple></span>&nbsp;';

        $out .= '<p>Afbeeldingen moeten in het jpg/jpeg formaat zijn. ';
        $out .= 'Je kan ook afbeeldingen hierheen ';
        $out .= 'verslepen.</p>';
        $out .= '</div>';

        if ($app['intersystems']->get_count($app['tschema']))
        {
            $out .= $app['item_access']->get_radio_buttons('access', $access, 'messages', true);
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
        $out .= '<input type="submit" value="Opslaan" name="zend" class="btn btn-' . $btn_class . '">';
        $out .= $app['form_token']->get_hidden_input();

        foreach ($uploaded_images as $img)
        {
            $out .= '<input type="hidden" name="uploaded_images[]" value="' . $img . '">';
        }

        foreach ($deleted_images as $img)
        {
            $out .= '<input type="hidden" name="deleted_images[]" value="' . $img . '">';
        }

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('messages');

        return $app['tpl']->get();
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

    public static function delete_images_from_db(
        array $deleted_images,
        int $id,
        db $db,
        monolog $monolog,
        string $schema
    ):void
    {
        if (!count($deleted_images))
        {
            return;
        }

        foreach ($deleted_images as $img)
        {
            if ($db->delete($schema . '.msgpictures', [
                'msgid'		        => $id,
                '"PictureFile"'	    => $img,
            ]))
            {
                $monolog->info('message-picture ' . $img .
                    ' deleted from db.', ['schema' => $schema]);
            }
        }
    }

    public static function add_images_to_db(
        array $uploaded_images,
        int $id,
        bool $fix_id,
        db $db,
        monolog $monolog,
        alert $alert,
        s3 $s3,
        string $schema
    ):void
    {
        if (!count($uploaded_images))
        {
            return;
        }

        foreach ($uploaded_images as $img)
        {
            $img_errors = [];

            [$img_schema, $img_type, $img_msg_id, $hash] = explode('_', $img);

            $img_msg_id = (int) $img_msg_id;

            if ($img_schema !== $schema)
            {
                $img_errors[] = 'Schema stemt niet overeen voor afbeelding ' . $img;
            }

            if ($img_type !== 'm')
            {
                $img_errors[] = 'Type stemt niet overeen voor afbeelding ' . $img;
            }

            if ($img_msg_id !== $id && !$fix_id)

            if (count($img_errors))
            {
                $alert->error($img_errors);

                continue;
            }

            if ($img_msg_id === $id)
            {
                if ($db->insert($schema . '.msgpictures', [
                    '"PictureFile"' => $img,
                    'msgid'			=> $id,
                ]))
                {
                    $monolog->info('message-picture ' . $img .
                        ' inserted in db.', ['schema' => $schema]);

                    continue;
                }

                $monolog->error('error message-picture ' . $img .
                    ' not inserted in db.', ['schema' => $schema]);

                continue;
            }

            $new_filename = $schema . '_m_' . $id . '_';
            $new_filename .= sha1(random_bytes(16)) . '.jpg';

            $err = $s3->copy($img, $new_filename);

            if (isset($err))
            {
                $monolog->error('message-picture renaming and storing in db ' . $img .
                    ' not succeeded. ' . $err, ['schema' => $schema]);
            }
            else
            {
                $monolog->info('renamed ' . $img . ' to ' .
                    $new_filename, ['schema' => $schema]);

                if ($db->insert($schema . '.msgpictures', [
                    '"PictureFile"'		=> $new_filename,
                    'msgid'				=> $id,
                ]))
                {
                    $monolog->info('message-picture ' . $new_filename .
                        ' inserted in db.', ['schema' => $schema]);

                    continue;
                }

                $monolog->error('error: message-picture ' . $new_filename .
                    ' not inserted in db.', ['schema' => $schema]);
            }
        }
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
