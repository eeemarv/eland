<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Doctrine\DBAL\Connection as Db;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Cnst\BulkCnst;
use App\Cnst\MessageTypeCnst;
use App\Command\Messages\MessagesFilterCommand;
use App\Form\Type\Messages\MessagesFilterType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesListController extends AbstractController
{
    const COLUMNS_DEF_ARY = [
        'offer'  => [
            'lbl'   => 'V/A',
            'sort'  => ['col' => ['m.offer_want'], 'dir' => 'desc'],
            'enabled'   => true,
        ],
        'subject'   => [
            'lbl'   => 'Wat',
            'sort'  => ['col' => ['m.subject'], 'dir' => 'desc'],
            'enabled'   => true,
        ],
        'user'  => [
            'lbl'       => 'Wie',
            'sort'      => ['col' => ['u.name'], 'dir' => 'desc'],
            'hide'      => ['phone', 'tablet'],
            'enabled'   => true,
        ],
        'postcode'  => [
            'lbl'   => 'Postcode',
            'sort'  => ['col' => ['u.postcode'], 'dir' => 'desc'],
            'hide'  => ['phone', 'tablet'],
            'enabled'   => true,
        ],
        'category'  => [
            'lbl'   => 'Categorie',
            'sort'  => ['col' => ['cp.name', 'c.name'], 'dir' => 'desc'],
            'hide'  => ['phone', 'tablet'],
            'enabled'   => true,
        ],
        'expires'   => [
            'lbl'   => 'Geldig tot',
            'sort'  => ['col' => ['m.expires_at'], 'dir' => 'desc'],
            'hide'  => ['phone', 'tablet'],
            'enabled'   => true,
        ],
        'created'   => [
            'lbl'   => 'Gecreëerd',
            'sort'  => ['col' => ['m.created_at'], 'dir' => 'desc'],
            'hide'  => ['phone', 'tablet'],
            'enabled'   => false,
        ],
        'access'    => [
            'lbl'   => 'Zichtbaar',
            'hide'  => ['phone', 'tablet'],
            'sort'  => ['col' => ['m.access'], 'dir' => 'desc'],
            'enabled'   => true,
        ],
    ];

    #[Route(
        '/{system}/{role_short}/messages',
        name: 'messages_list',
        methods: ['GET', 'POST'],
        priority: 20,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'is_self'       => false,
            'module'        => 'messages',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/messages/self',
        name: 'messages_list_self',
        methods: ['GET', 'POST'],
        priority: 20,
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'is_self'       => true,
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        bool $is_self,
        FormTokenService $form_token_service,
        AccountRender $account_render,
        AlertService $alert_service,
        DateFormatService $date_format_service,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        SelectRender $select_render,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $vr_route = $vr->get('messages' . ($is_self ? '_self' : ''));

        $errors = [];

        $service_stuff_enabled = $config_service->get_bool('messages.fields.service_stuff.enabled', $pp->schema());
        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $postcode_enabled = $config_service->get_bool('users.fields.postcode.enabled', $pp->schema());
        $intersytem_en = $config_service->get_intersystem_en($pp->schema());
        $bulk_actions_enabled = $category_enabled || $expires_at_enabled || $intersytem_en;

        $selected_messages = $request->request->all('sel');
        $bulk_field = $request->request->all('bulk_field');
        $bulk_verify = $request->request->all('bulk_verify');
        $bulk_submit = $request->request->all('bulk_submit');

        if ($request->isMethod('POST')
            && !$pp->is_guest()
            && count($bulk_submit)
            && $bulk_actions_enabled)
        {
            if (count($bulk_submit) > 1)
            {
                throw new BadRequestHttpException('Invalid form. More than one submit.');
            }

            if (count($bulk_field) > 1)
            {
                throw new BadRequestHttpException('Invalid form. More than one bulk field.');
            }

            if (count($bulk_verify) > 1)
            {
                throw new BadRequestHttpException('Invalid form. More than one bulk verify checkbox.');
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($selected_messages))
            {
                $errors[] = 'Selecteer ten minste één vraag of aanbod voor deze actie.';
            }

            if (count($bulk_verify) !== 1)
            {
                $errors[] = 'Het controle nazichts-vakje is niet aangevinkt.';
            }

            $bulk_submit_action = array_key_first($bulk_submit);
            $bulk_verify_action = array_key_first($bulk_verify);
            $bulk_field_action = array_key_first($bulk_field);

            if (isset($bulk_verify_action)
                && $bulk_verify_action !== $bulk_submit_action)
            {
                throw new BadRequestHttpException('Invalid form. Not matching verify checkbox to bulk action.');
            }

            if (isset($bulk_field_action)
                && $bulk_field_action !== $bulk_submit_action)
            {
                throw new BadRequestHttpException('Invalid form. Not matching field to bulk action.');
            }

            if (!isset($bulk_field_action))
            {
                throw new BadRequestHttpException('Invalid form. Missing value.');
            }

            $bulk_field_value = $bulk_field[$bulk_field_action];

            if (!isset($bulk_field_value) || !$bulk_field_value)
            {
                $errors[] = 'Bulk actie waarde-veld niet ingevuld.';
            }

            $update_msgs_ary  = [];

            $res = $db->executeQuery('select user_id, id, expires_at,
                    category_id
                from ' . $pp->schema() . '.messages
                where id in (?)',
                [array_keys($selected_messages)],
                [Db::PARAM_INT_ARRAY]);

            while ($row = $res->fetchAssociative())
            {
                if (!$pp->is_admin() && !$su->is_owner($row['user_id']))
                {
                    throw new AccessDeniedHttpException('You are not the owner of this message: ' .
                        $row['subject'] . ' ( ' . $row['id'] . ')');
                }

                $update_msgs_ary[$row['id']] = $row;
            }

            if ($bulk_submit_action === 'extend' && !count($errors))
            {
                if (!$expires_at_enabled)
                {
                    throw new BadRequestHttpException('Message expiration sub-module not enabled.');
                }

                foreach ($update_msgs_ary as $id => $row)
                {
                    $expires_at = $row['expires_at'] ?? gmdate('Y-m-d H:i:s');
                    $expires_at = gmdate('Y-m-d H:i:s', strtotime($expires_at . ' UTC') + (86400 * (int) $bulk_field_value));

                    $msg_update = [
                        'expires_at'    => $expires_at,
                        'exp_user_warn'	=> 'f',
                    ];

                    $db->update($pp->schema() . '.messages',
                        $msg_update, ['id' => $id]);
                }

                if (count($update_msgs_ary) > 1)
                {
                    $alert_service->success('De berichten zijn verlengd.');
                }
                else
                {
                    $alert_service->success('Het bericht is verlengd.');
                }

                return $this->redirectToRoute($vr_route, $pp->ary());
            }

            if ($bulk_submit_action === 'service_stuff' && !count($errors))
            {
                if (!$service_stuff_enabled)
                {
                    throw new BadRequestHttpException('Service/stuff sub-module not enabled.');
                }

                if (!in_array($bulk_field_value, ['service', 'stuff', 'null-service-stuff']))
                {
                    throw new BadRequestHttpException('Unvalid value: ' . $bulk_field_value);
                }

                if ($bulk_field_value === 'null-service-stuff')
                {
                    $bulk_field_value = null;
                }

                $msg_update = [
                    'service_stuff'   => $bulk_field_value,
                ];

                foreach ($update_msgs_ary as $id => $row)
                {
                    $db->update($pp->schema() . '.messages', $msg_update, ['id' => $id]);
                }

                if (count($selected_messages) > 1)
                {
                    $alert_service->success('De berichten zijn aangepast.');
                }
                else
                {
                    $alert_service->success('Het bericht is aangepast.');
                }

                return $this->redirectToRoute($vr_route, $pp->ary());
            }

            if ($bulk_submit_action === 'category' && !count($errors))
            {
                if (!$category_enabled)
                {
                    throw new BadRequestHttpException('Categories sub-module not enabled.');
                }

                $to_category_id = (int) $bulk_field_value;

                $test_category = $db->fetchAssociative('select *
                    from ' . $pp->schema() . '.categories
                    where id = ?',
                    [$to_category_id],
                    [\PDO::PARAM_INT]);

                if (!$test_category)
                {
                    throw new BadRequestHttpException('Non existing category. Id: ' . $to_category_id);
                }

                if (($test_category['left_id'] + 1) !== $test_category['right_id'])
                {
                    throw new BadRequestHttpException('A category with sub-categories cannot contain messages. Id: ' . $to_category_id);
                }

                $msg_update = [
                    'category_id'   => $to_category_id,
                ];

                foreach ($update_msgs_ary as $id => $row)
                {
                    $db->update($pp->schema() . '.messages', $msg_update, ['id' => $id]);
                }

                if (count($selected_messages) > 1)
                {
                    $alert_service->success('De categorie van de berichten is aangepast.');
                }
                else
                {
                    $alert_service->success('De categorie van het bericht is aangepast.');
                }

                return $this->redirectToRoute($vr_route, $pp->ary());
            }

            if ($bulk_submit_action === 'access' && !count($errors))
            {
                if (!$intersytem_en)
                {
                    throw new BadRequestHttpException('Bulk access not enabled when intersystem functionality is not enabledd.');
                }

                $msg_update = [
                    'access'    => $bulk_field_value,
                ];

                foreach ($update_msgs_ary as $id => $row)
                {
                    $db->update($pp->schema() . '.messages', $msg_update, ['id' => $id]);
                }

                if (count($selected_messages) > 1)
                {
                    $alert_service->success('De zichtbaarheid van de berichten is aangepast.');
                }
                else
                {
                    $alert_service->success('De zichtbaarheid van het bericht is aangepast.');
                }

                return $this->redirectToRoute($vr_route, $pp->ary());
            }

            $alert_service->error($errors);
        }

        $fetch_and_filter = $this->fetch_and_filter(
            $request,
            $db,
            $is_self,
            $config_service,
            $pp,
            $su
        );

        $messages = $fetch_and_filter['messages'];
        $row_count = $fetch_and_filter['row_count'];
        $filter_form = $fetch_and_filter['filter_form'];
        $filter_command = $fetch_and_filter['filter_command'];
        $uid = $fetch_and_filter['uid'];
        $filtered = $fetch_and_filter['filtered'];
        $filter_collapse = $fetch_and_filter['filter_collapse'];
        $is_owner = $fetch_and_filter['is_owner'];
        $count_ary = $fetch_and_filter['count_ary'];
        $cat_count_ary = $fetch_and_filter['cat_count_ary'];
        $sort_dir = $fetch_and_filter['sort_dir'];
        $sort_col = $fetch_and_filter['sort_col'];

        $all_params = $request->query->all();

        $categories = [];

        if ($category_enabled)
        {
            $categories_move_options = ['' => ['name' => '']];

            $cat_params  = [];

            if (isset($cat_count_ary['null']))
            {
                $categories['null'] = '** zonder categorie **';
                $cat_params['null'] = $all_params;
                $cat_params['null']['f']['cat'] = 'null';
            }

            $parent_name = '***';

            $res = $db->executeQuery('select *
                from ' . $pp->schema() . '.categories
                order by left_id asc');

            while ($row = $res->fetchAssociative())
            {
                $name = $row['name'];
                $cat_id = $row['id'];
                $parent_id = $row['parent_id'];

                $cat_params[$cat_id] = $all_params;
                $cat_params[$cat_id]['f']['cat'] = $cat_id;

                $count_str = isset($cat_count_ary[$cat_id]) ? ' (' . $cat_count_ary[$cat_id] . ')' : '';

                if (isset($parent_id))
                {
                    $categories[$cat_id] = $parent_name . ' > ' . $name;

                    $categories_move_options[$parent_id]['children'][$cat_id] = [
                        'name'  => $name . $count_str,
                    ];
                }
                else
                {
                    $parent_name = $name;
                    $categories[$cat_id] = $parent_name;

                    $categories_move_options[$cat_id] = [
                        'name'          => $name . $count_str,
                        'children'      => [],
                    ];
                }
            }
        }

        $out = '<div class="panel panel-info printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-striped ';
        $out .= 'table-bordered table-hover footable csv" ';
        $out .= 'id="msgs" data-sort="false">';

        $out .= '<thead>';
        $out .= '<tr>';

        $th_params = $all_params;

        $column_ary = self::COLUMNS_DEF_ARY;

        if (isset($uid))
        {
            unset($column_ary['user']);
            unset($column_ary['postcode']);
        }

        if (!$postcode_enabled)
        {
            unset($column_ary['postcode']);
        }

        if (isset($filter_command->cat) || !$category_enabled)
        {
            unset($column_ary['category']);
        }

        if (!$expires_at_enabled)
        {
            unset($column_ary['expires']);
        }

        $show_visibility_column = !$pp->is_guest() && $intersystems_service->get_count($pp->schema());

        if (!$show_visibility_column)
        {
            unset($column_ary['access']);
        }

        foreach ($column_ary as $col => $data)
        {
            if (!isset(self::COLUMNS_DEF_ARY[$col]))
            {
                continue;
            }

            if (!$data['enabled'])
            {
                continue;
            }

            $out .= '<th';

            if (isset($data['hide']))
            {
                $out .= ' data-hide="';
                $out .= implode(',', $data['hide']);
                $out .= '"';
            }

            $out .= '>';

            if (isset($data['no_sort']))
            {
                $out .= $data['lbl'];
            }
            else
            {
                $fa = 'sort';
                $dir = $data['sort']['dir'];

                if ($col === $sort_col)
                {
                    $dir = $sort_dir === 'asc' ? 'desc' : 'asc';
                    $fa = $sort_dir === 'asc' ? 'sort-asc' : 'sort-desc';
                }

                $th_params['s'] = [
                    'col'	    => $col,
                    'dir' 		=> $dir,
                ];

                $out .= $link_render->link_fa($vr_route, $pp->ary(),
                    $th_params, $data['lbl'], [], $fa);
            }

            $out .= '</th>';
        }

        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach($messages as $msg)
        {
            $out .= '<tr';

            if ($expires_at_enabled)
            {
                $out .= isset($msg['expires_at']) && strtotime($msg['expires_at']) < time() ? ' class="danger"' : '';
            }

            $out .= '>';

            $out .= '<td>';

            if ($bulk_actions_enabled && ($pp->is_admin() || $is_owner))
            {
                $out .= strtr(BulkCnst::TPL_CHECKBOX_ITEM, [
                    '%id%'      => $msg['id'],
                    '%attr%'    => isset($selected_messages[$msg['id']]) ? ' checked' : '',
                    '%label%'   => ucfirst($msg['label']['offer_want']),
                ]);
            }
            else
            {
                $out .= ucfirst($msg['label']['offer_want']);
            }

            $out .= '</td>';

            $out .= '<td>';

            $out .= $link_render->link_no_attr('messages_show', $pp->ary(),
                ['id' => $msg['id']], $msg['subject']);

            $out .= '</td>';

            if (!isset($uid))
            {
                $out .= '<td>';
                $out .= $account_render->link($msg['user_id'], $pp->ary());
                $out .= '</td>';
            }

            if ($postcode_enabled && !isset($uid))
            {
                $out .= '<td>';
                $out .= $msg['postcode'] ?? '';
                $out .= '</td>';
            }

            if ($category_enabled && !($uid ?? false))
            {
                $out .= '<td>';

                $out .= $link_render->link_no_attr($vr_route, $pp->ary(),
                    $cat_params[$msg['category_id'] ?? 'null'],
                    $categories[$msg['category_id'] ?? 'null']);

                $out .= '</td>';
            }

            if ($expires_at_enabled)
            {
                $out .= '<td>';

                if (isset($msg['expires_at']))
                {
                    $out .= $date_format_service->get($msg['expires_at'], 'day', $pp->schema());
                }
                else
                {
                    $out .= '&nbsp;';
                }

                $out .= '</td>';
            }

            if ($show_visibility_column)
            {
                $out .= '<td>';
                $out .= $item_access_service->get_label($msg['access']);
                $out .= '</td>';
            }

            $out .= '</tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';

        $out .= '</div>';
        $out .= '</div>';

        if ($bulk_actions_enabled && ($pp->is_admin() || $is_owner) && count($messages))
        {
            $extend_options = [
                '7'		=> '1 week',
                '14'	=> '2 weken',
                '30'	=> '1 maand',
                '60'	=> '2 maanden',
                '180'	=> '6 maanden',
                '365'	=> '1 jaar',
                '730'	=> '2 jaar',
                '1825'	=> '5 jaar',
            ];

            if ($category_enabled){
                $cat_options = '';

                foreach ($categories_move_options as $cat_id => $cat_data)
                {
                    if (isset($cat_data['children']) && count($cat_data['children']))
                    {
                        $cat_options .= '<optgroup label="';
                        $cat_options .= $cat_data['name'];
                        $cat_options .= '">';

                        foreach ($cat_data['children'] as $sub_cat_id => $sub_cat_data)
                        {
                            $cat_options .= '<option value="';
                            $cat_options .= $sub_cat_id;
                            $cat_options .= '">';
                            $cat_options .= $sub_cat_data['name'];
                            $cat_options .= '</option>';
                        }
                        $cat_options .= '</optgroup>';
                        continue;
                    }

                    $cat_options .= '<option value="';
                    $cat_options .= $cat_id;
                    $cat_options .= '">';
                    $cat_options .= $cat_data['name'];
                    $cat_options .= '</option>';
                }
            }

            $blk = BulkCnst::TPL_SELECT_BUTTONS;

            $blk .= '<h3>Bulk acties met geselecteerd vraag en aanbod</h3>';

            $blk .= '<div class="panel panel-info">';
            $blk .= '<div class="panel-heading">';

            $blk .= '<ul class="nav nav-tabs" role="tablist">';

            if ($expires_at_enabled)
            {
                $blk .= '<li class="active"><a href="#extend_tab" ';
                $blk .= 'data-toggle="tab">Verlengen</a></li>';
            }

            if ($service_stuff_enabled)
            {
                $blk .= '<li><a href="#service_stuff_tab" ';
                $blk .= 'data-toggle="tab">Diensten / Spullen</a></li>';
            }

            if ($category_enabled)
            {
                $blk .= '<li><a href="#category_tab" ';
                $blk .= 'data-toggle="tab">Categorie</a></li>';
            }

            if ($intersytem_en)
            {
                $blk .= '<li>';
                $blk .= '<a href="#access_tab" data-toggle="tab">';
                $blk .= 'Zichtbaarheid</a><li>';
            }

            $blk .= '</ul>';

            $blk .= '<div class="tab-content">';

            if ($expires_at_enabled)
            {
                $blk .= '<div role="tabpanel" class="tab-pane active" id="extend_tab">';
                $blk .= '<h3>Vraag en aanbod verlengen</h3>';

                $blk .= '<form method="post">';

                $blk .= '<div class="form-group">';
                $blk .= '<label for="buld_field[extend]" class="control-label">';
                $blk .= 'Verlengen met</label>';
                $blk .= '<select name="bulk_field[extend]" id="extend" class="form-control">';
                $blk .= $select_render->get_options($extend_options, '30');
                $blk .= "</select>";
                $blk .= '</div>';

                $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[extend]',
                    '%label%'   => 'Ik heb nagekeken dat de juiste berichten geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $blk .= '<input type="submit" value="Verlengen" ';
                $blk .= 'name="bulk_submit[extend]" class="btn btn-primary btn-lg">';

                $blk .= $form_token_service->get_hidden_input();

                $blk .= '</form>';

                $blk .= '</div>';
            }

            if ($service_stuff_enabled)
            {
                $blk .= '<div role="tabpanel" class="tab-pane" id="service_stuff_tab">';
                $blk .= '<h3>Diensten of spullen</h3>';
                $blk .= '<form method="post">';

                $blk .= '<div class="form-group">';

                foreach (MessageTypeCnst::SERVICE_STUFF_TPL_ARY as $key => $render_data)
                {
                    $label = '<span class="btn btn-';
                    $label .= $render_data['btn_class'];
                    $label .= '"';

                    if (isset($render_data['title']))
                    {
                        $label .= ' title="' . $render_data['title'] . '"';
                    }

                    $label .= '>';
                    $label .= $render_data['label'];
                    $label .= '</span>';

                    $blk .= strtr(BulkCnst::TPL_RADIO_INLINE,[
                        '%name%'    => 'bulk_field[service_stuff]',
                        '%value%'   => $key,
                        '%attr%'    => ' required',
                        '%label%'   => $label,
                    ]);
                }

                $blk .= '</div>';

                $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[service_stuff]',
                    '%label%'   => 'Ik heb nagekeken dat de juiste berichten geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $blk .= '<input type="submit" value="Aanpassen" ';
                $blk .= 'name="bulk_submit[service_stuff]" class="btn btn-primary btn-lg">';
                $blk .= $form_token_service->get_hidden_input();
                $blk .= '</form>';
                $blk .= '</div>';
            }

            if ($category_enabled)
            {
                $blk .= '<div role="tabpanel" class="tab-pane" id="category_tab">';
                $blk .= '<h3>Verhuizen naar categorie</h3>';
                $blk .= '<form method="post">';

                $blk .= strtr(BulkCnst::TPL_SELECT, [
                    '%options%' => $cat_options,
                    '%name%'    => 'bulk_field[category]',
                    '%label%'   => 'Categorie',
                    '%attr%'    => ' required',
                    '%fa%'      => 'clone',
                    '%explain%' => '',
                ]);

                $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[category]',
                    '%label%'   => 'Ik heb nagekeken dat de juiste berichten geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $blk .= '<input type="submit" value="Categorie aanpassen" ';
                $blk .= 'name="bulk_submit[category]" class="btn btn-primary btn-lg">';
                $blk .= $form_token_service->get_hidden_input();
                $blk .= '</form>';
                $blk .= '</div>';
            }

            if ($intersytem_en)
            {
                $blk .= '<div role="tabpanel" class="tab-pane" id="access_tab">';
                $blk .= '<h3>Zichtbaarheid instellen</h3>';
                $blk .= '<form method="post">';

                $blk .= $item_access_service->get_radio_buttons('bulk_field[access]', '', '', true);

                $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[access]',
                    '%label%'   => 'Ik heb nagekeken dat de juiste berichten geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $blk .= '<input type="submit" value="Aanpassen" ';
                $blk .= 'name="bulk_submit[access]" class="btn btn-primary btn-lg">';
                $blk .= $form_token_service->get_hidden_input();
                $blk .= '</form>';
                $blk .= '</div>';
            }

            $blk .= '</div>';

            $blk .= '<div class="clearfix"></div>';
            $blk .= '</div>';

            $blk .= '</div></div>';
        }

        return $this->render('messages/messages_list.html.twig', [
            'data_list_raw'         => $out,
            'bulk_actions_raw'      => $blk ?? '',
            'categories'            => $categories,
            'row_count'             => $row_count,
            'is_self'               => $is_self,
            'uid'                   => $uid,
            'cat_id'                => $filter_command->cat ?: null,
            'filter_form'           => $filter_form,
            'filtered'              => $filtered,
            'msgs_filter_collapse'  => $filter_collapse,
            'count_ary'             => $count_ary,
            'cat_count_ary'         => $cat_count_ary,
        ]);
    }

    public function fetch_and_filter(
        Request $request,
        Db $db,
        bool $is_self,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su
    ):array
    {
        $service_stuff_enabled = $config_service->get_bool('messages.fields.service_stuff.enabled', $pp->schema());
        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());

        $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

        $filter_command = new MessagesFilterCommand();

        if ($request->query->has('uid'))
        {
            $uid = (int) $request->query->get('uid');
        }

        if ($is_self)
        {
            $uid = $su->id();
        }

        if (isset($uid))
        {
            $filter_command->user = $uid;
        }

        $filter_form = $this->createForm(MessagesFilterType::class, $filter_command);
        $filter_form->handleRequest($request);
        $filter_command = $filter_form->getData();

        if (isset($uid))
        {
            $filter_command->user = $uid;
        }

        $f_params = $request->query->all('f');
        $filter_form_error = isset($f_params['user']) && !isset($filter_command->user);

        $pag = $request->query->all('p');
        $sort = $request->query->all('s');

        $sort_col = $sort['col'] ?? 'created';
        $sort_col = isset(self::COLUMNS_DEF_ARY[$sort_col]) ? $sort_col : 'created';

        $sort_dir = $sort['dir'] ?? 'desc';
        $sort_dir = in_array($sort_dir, ['asc', 'desc']) ? $sort_dir : 'desc';

        $pag_start = $pag['start'] ?? 0;
        $pag_limit = $pag['limit'] ?? 25;

        $sql_map = [
            'where'     => [],
            'where_or'  => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [
            'common'   => $sql_map,
        ];

        $sql['common']['where'][] = '1 = 1';

        $is_owner = isset($filter_command->user)
            && $su->is_owner($filter_command->user);

        if (isset($filter_command->q))
        {
            $sql['q'] = $sql_map;
            $sql['q']['where'][] = '(m.subject ilike ? or m.content ilike ?)';
            $sql['q']['params'][] = '%' . $filter_command->q . '%';
            $sql['q']['params'][] = '%' . $filter_command->q . '%';
            $sql['q']['types'][] = \PDO::PARAM_INT;
            $sql['q']['types'][] = \PDO::PARAM_INT;
        }

        if (isset($filter_command->user))
        {
            $sql['user']['where'][] = 'u.id = ?';
            $sql['user']['params'][] = $filter_command->user;
            $sql['user']['types'][] = \PDO::PARAM_INT;
        }

        $filter_valid_expired = $expires_at_enabled
            && isset($filter_command->ve)
            && $filter_command->ve;

        if ($filter_valid_expired)
        {
            $sql['valid_expired'] = $sql_map;

            if (in_array('valid', $filter_command->ve))
            {
                $sql['valid_expired']['where_or'][] = '(m.expires_at >= timezone(\'utc\', now()) or m.expires_at is null)';
            }

            if (in_array('expired', $filter_command->ve))
            {
                $sql['valid_expired']['where_or'][] = 'm.expires_at < timezone(\'utc\', now())';
                $params['f']['expired'] = '1';
            }

            if (count($sql['valid_expired']['where_or']))
            {
                $sql['valid_expired']['where'][] = ' (' . implode(' or ', $sql['valid_expired']['where_or']) . ') ';
            }
        }

        $filter_offer_want = isset($filter_command->ow) && $filter_command->ow;

        if ($filter_offer_want)
        {
            $sql['offer_want'] = $sql_map;

            if (in_array('want', $filter_command->ow))
            {
                $sql['offer_want']['where_or'][] = 'm.offer_want = \'want\'';
            }

            if (in_array('offer', $filter_command->ow))
            {
                $sql['offer_want']['where_or'][] = 'm.offer_want = \'offer\'';
            }

            if (count($sql['offer_want']['where_or']))
            {
                $sql['offer_want']['where'][] = ' (' . implode(' or ', $sql['offer_want']['where_or']) . ') ';
            }
        }

        $filter_service_stuff = $service_stuff_enabled
            && (isset($filter_command->srvc)
            && $filter_command->srvc
        );

        if ($filter_service_stuff)
        {
            $sql['service_stuff'] = $sql_map;

            if (in_array('srvc', $filter_command->srvc))
            {
                $sql['service_stuff']['where_or'][] = 'm.service_stuff = \'service\'';
            }

            if (in_array('stff', $filter_command->srvc))
            {
                $sql['service_stuff']['where_or'][] = 'm.service_stuff = \'stuff\'';
            }

            if (in_array('null', $filter_command->srvc))
            {
                $sql['service_stuff']['where_or'][] = 'm.service_stuff is null';
            }

            if (count($sql['service_stuff']['where_or']))
            {
                $sql['service_stuff']['where'][] = '(' . implode(' or ', $sql['service_stuff']['where_or']) . ')';
            }
        }

        $filter_user_status = isset($filter_command->us)
            && $filter_command->us;

        if ($filter_user_status)
        {
            $sql_user_status_where = [];
            $sql['user_status'] = $sql_map;

            if (in_array('new', $filter_command->us))
            {
                $sql_user_status_where[] = '(u.adate > ? and u.status = 1)';
                $sql['user_status']['params'][] = $new_user_treshold;
                $sql['user_status']['types'][] = Types::DATETIME_IMMUTABLE;
            }

            if (in_array('leaving', $filter_command->us))
            {
                $sql_user_status_where[] = 'u.status = 2';
            }

            if (in_array('active', $filter_command->us))
            {
                $sql_user_status_where[] = '(u.adate <= ? and u.status = 1)';
                $sql['user_status']['params'][] = $new_user_treshold;
                $sql['user_status']['types'][] = Types::DATETIME_IMMUTABLE;
            }

            if (count($sql_user_status_where))
            {
                $sql['user_status']['where'][] = '(' . implode(' or ', $sql_user_status_where) . ')';
            }
        }

        $filter_access = isset($filter_command->access)
            && $filter_command->access;

        if ($filter_access)
        {
            $sql_access_where = [];
            $sql['access'] = $sql_map;

            if (in_array('admin', $filter_command->access))
            {
                $sql_access_where[] = 'm.access = \'admin\'';
            }

            if (in_array('user', $filter_command->access))
            {
                $sql_access_where[] = 'm.access = \'user\'';
            }

            if (in_array('guest', $filter_command->access))
            {
                $sql_access_where[] = 'm.access = \'guest\'';
            }

            if (count($sql_access_where))
            {
                $sql['access']['where'][] = '(' . implode(' or ', $sql_access_where) . ')';
            }
        }

        if ($pp->is_guest())
        {
            $sql['is_guest'] = $sql_map;
            $sql['is_guest']['where'][] = 'm.access = \'guest\'';
        }

        $sql['common']['where'][] = 'u.status in (1, 2)';

        $filter_category = isset($filter_command->cat)
            && $filter_command->cat
            && $category_enabled;

        if ($filter_category)
        {
            $sql['category'] = $sql_map;

            if ($filter_command->cat === 'null')
            {
                $sql['category']['where'][] = 'm.category_id is null';
            }
            else
            {
                $cat_lr = $db->fetchAssociative('select left_id, right_id
                    from ' . $pp->schema() . '.categories
                    where id = ?',
                    [$filter_command->cat],
                    [\PDO::PARAM_INT]);

                if (!$cat_lr)
                {
                    throw new BadRequestHttpException('Category not found, id:' . $filter_command->cat);
                }

                $sql['category']['where'][] = 'c.left_id >= ? and c.right_id <= ?';
                $sql['category']['params'][] = $cat_lr['left_id'];
                $sql['category']['params'][] = $cat_lr['right_id'];
                $sql['category']['types'][] = \PDO::PARAM_INT;
                $sql['category']['types'][] = \PDO::PARAM_INT;
            }
        }

        $sql['pagination'] = $sql_map;
        $sql['pagination']['params'][] = $pag_limit;
        $sql['pagination']['types'][] = \PDO::PARAM_INT;
        $sql['pagination']['params'][] = $pag_start;
        $sql['pagination']['types'][] = \PDO::PARAM_INT;

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));

        $sort_ary = self::COLUMNS_DEF_ARY[$sort_col]['sort'];

        $order_query = [];

        foreach ($sort_ary['col'] as $col)
        {
            $order_query[] = $col . ' ' . $sort_dir;
        }

        $query = 'select m.*, u.postcode
            from ' . $pp->schema() . '.messages m
            inner join ' . $pp->schema() . '.users u
                on m.user_id = u.id
            left join ' . $pp->schema() . '.categories c
                on m.category_id = c.id
            left join ' . $pp->schema() . '.categories cp
                on c.parent_id = cp.id
            where ' . $sql_where . '
            order by ' . implode(', ', $order_query) . '
            limit ? offset ?';

        $messages = [];

        $res = $db->executeQuery($query,
            array_merge(...array_column($sql, 'params')),
            array_merge(...array_column($sql, 'types')));

        while ($msg = $res->fetchAssociative())
        {
            $msg['label'] = MessagesShowController::get_label($msg['offer_want']);
            $messages[] = $msg;
        }

        $count_ary = [
            'offer'                 => 0,
            'want'                  => 0,
            'service'               => 0,
            'stuff'                 => 0,
            'null_service_stuff'    => 0,
            'valid'                 => 0,
            'expired'               => 0,
            'active'                => 0,
            'new'                   => 0,
            'leaving'               => 0,
            'admin'                 => 0,
            'user'                  => 0,
            'guest'                 => 0,
        ];

        $sql_omit_pagination = $sql;
        unset($sql_omit_pagination['pagination']);

        $sql_omit_offer_want = $sql_omit_pagination;
        unset($sql_omit_offer_want['offer_want']);

        $sql_omit_offer_want_where = implode(' and ', array_merge(...array_column($sql_omit_offer_want, 'where')));

        $count_offer_want_query = 'select count(m.*), m.offer_want
            from ' . $pp->schema() . '.messages m
            inner join ' . $pp->schema() . '.users u
                on m.user_id = u.id
            left join ' . $pp->schema() . '.categories c
                on c.id = m.category_id
            where ' . $sql_omit_offer_want_where . '
            group by m.offer_want';

        $res = $db->executeQuery($count_offer_want_query,
            array_merge(...array_column($sql_omit_offer_want, 'params')),
            array_merge(...array_column($sql_omit_offer_want, 'types')));

        while($row = $res->fetchAssociative())
        {
            $count_ary[$row['offer_want']] = $row['count'];
        }

        if ($service_stuff_enabled)
        {
            $sql_omit_service_stuff = $sql_omit_pagination;
            unset($sql_omit_service_stuff['service_stuff']);

            $sql_omit_service_stuff_where = implode(' and ', array_merge(...array_column($sql_omit_service_stuff, 'where')));

            $count_service_stuff_query = 'select count(m.*), m.service_stuff
                from ' . $pp->schema() . '.messages m
                inner join ' . $pp->schema() . '.users u
                    on m.user_id = u.id
                left join ' . $pp->schema() . '.categories c
                    on c.id = m.category_id
                where ' . $sql_omit_service_stuff_where . '
                group by m.service_stuff';

            $res = $db->executeQuery($count_service_stuff_query,
                array_merge(...array_column($sql_omit_service_stuff, 'params')),
                array_merge(...array_column($sql_omit_service_stuff, 'types')));

            while($row = $res->fetchAssociative())
            {
                $count_ary[$row['service_stuff'] ?? 'null_service_stuff'] = $row['count'];
            }
        }

        if ($expires_at_enabled)
        {
            $sql_omit_valid_expired = $sql_omit_pagination;
            unset($sql_omit_valid_expired['valid_expired']);

            $sql_omit_valid_expired_where = implode(' and ', array_merge(...array_column($sql_omit_valid_expired, 'where')));

            $count_valid_expired_query = 'select count(m.*),
                    (m.expires_at >= timezone(\'utc\', now()) or m.expires_at is null) as valid
                from ' . $pp->schema() . '.messages m
                inner join ' . $pp->schema() . '.users u
                    on m.user_id = u.id
                left join ' . $pp->schema() . '.categories c
                    on c.id = m.category_id
                where ' . $sql_omit_valid_expired_where . '
                group by valid';

            $res = $db->executeQuery($count_valid_expired_query,
                array_merge(...array_column($sql_omit_valid_expired, 'params')),
                array_merge(...array_column($sql_omit_valid_expired, 'types')));

            while($row = $res->fetchAssociative())
            {
                $count_ary[$row['valid'] ? 'valid' : 'expired'] = $row['count'];
            }
        }

        $sql_omit_user_status = $sql_omit_pagination;
        unset($sql_omit_user_status['user_status']);

        $sql_omit_user_status_where = implode(' and ', array_merge(...array_column($sql_omit_user_status, 'where')));

        $count_user_status_query = 'select count(m.*),
                (case
                    when u.status = 2 then \'leaving\'
                    when u.status = 1 and u.adate > ? then \'new\'
                    when u.status = 1 then \'active\'
                    else \'inactive\'
                end) as u_status
            from ' . $pp->schema() . '.messages m
            inner join ' . $pp->schema() . '.users u
                on m.user_id = u.id
            left join ' . $pp->schema() . '.categories c
                on c.id = m.category_id
            where ' . $sql_omit_user_status_where . '
            group by u_status';

        $res = $db->executeQuery($count_user_status_query,
            array_merge([$new_user_treshold], ...array_column($sql_omit_user_status, 'params')),
            array_merge([Types::DATETIME_IMMUTABLE], ...array_column($sql_omit_user_status, 'types')));

        while($row = $res->fetchAssociative())
        {
            $count_ary[$row['u_status']] = $row['count'];
        }

        $sql_omit_access = $sql_omit_pagination;
        unset($sql_omit_access['access']);

        $sql_omit_access_where = implode(' and ', array_merge(...array_column($sql_omit_access, 'where')));

        $count_access_query = 'select count(m.*), m.access
            from ' . $pp->schema() . '.messages m
            inner join ' . $pp->schema() . '.users u
                on m.user_id = u.id
            left join ' . $pp->schema() . '.categories c
                on c.id = m.category_id
            where ' . $sql_omit_access_where . '
            group by m.access';

        $res = $db->executeQuery($count_access_query,
            array_merge(...array_column($sql_omit_access, 'params')),
            array_merge(...array_column($sql_omit_access, 'types')));

        while($row = $res->fetchAssociative())
        {
            $count_ary[$row['access']] = $row['count'];
        }

        $sql_omit_category = $sql_omit_pagination;
        unset($sql_omit_category['category']);

        $sql_omit_category_where = implode(' and ', array_merge(...array_column($sql_omit_category, 'where')));

        $cat_count_ary = [];
        $no_cat_count = 0;

        $count_category_query = 'select count(m.*), m.category_id
            from ' . $pp->schema() . '.messages m
            inner join ' .  $pp->schema() . '.users u
                on m.user_id = u.id
            where ' . $sql_omit_category_where . '
            group by m.category_id';

        $res = $db->executeQuery($count_category_query,
            array_merge(...array_column($sql_omit_category, 'params')),
            array_merge(...array_column($sql_omit_category, 'types')));

        while($row = $res->fetchAssociative())
        {
            if (isset($row['category_id']))
            {
                $cat_count_ary[$row['category_id']] = $row['count'];
                continue;
            }

            $cat_count_ary['null'] = $row['count'];
        }

        if (isset($filter_command->cat)
            && $category_enabled)
        {
            $row_count = $db->fetchOne('select count(m.*)
                from ' . $pp->schema() . '.messages m
                inner join ' . $pp->schema() . '.users u
                    on m.user_id = u.id
                left join ' . $pp->schema() . '.categories c
                    on c.id = m.category_id
                where ' . $sql_where,
                array_merge(...array_column($sql_omit_pagination, 'params')),
                array_merge(...array_column($sql_omit_pagination, 'types')));
        }
        else
        {
            $row_count = array_sum($cat_count_ary);
            $row_count += $no_cat_count;
        }

        $filter_panel_open = ($filter_command->user && !isset($uid))
            || $filter_offer_want
            || $filter_valid_expired
            || $filter_service_stuff
            || $filter_user_status
            || $filter_access;

        $filtered = isset($filter_command->q) || $filter_panel_open;

        $filter_collapse = !($filter_panel_open || $filter_form_error);

        return [
            'messages'                  => $messages,
            'row_count'                 => $row_count,
            'filter_form'               => $filter_form->createView(),
            'filter_command'            => $filter_command,
            'filtered'                  => $filtered,
            'filter_collapse'           => $filter_collapse,
            'uid'                       => $uid ?? null,
            'is_owner'                  => $is_owner,
            'count_ary'                 => $count_ary,
            'cat_count_ary'             => $cat_count_ary,
            'sort_col'                  => $sort_col,
            'sort_dir'                  => $sort_dir,
        ];
    }
}
