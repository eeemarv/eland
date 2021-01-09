<?php declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection as Db;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Cnst\BulkCnst;
use App\Cnst\MenuCnst;
use App\Cnst\MessageTypeCnst;
use App\Controller\MessagesShowController;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\PaginationRender;
use App\Render\SelectRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    public function __invoke(
        Request $request,
        Db $db,
        FormTokenService $form_token_service,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        DateFormatService $date_format_service,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        MenuService $menu_service,
        LinkRender $link_render,
        PaginationRender $pagination_render,
        SelectRender $select_render,
        ConfigService $config_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        HeadingRender $heading_render
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $errors = [];

        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $units_enabled = $config_service->get_bool('messages.fields.units.enabled', $pp->schema());
        $intersytem_en = $config_service->get_intersystem_en($pp->schema());
        $bulk_actions_enabled = $category_enabled || $expires_at_enabled || $intersytem_en;

        $selected_messages = $request->request->get('sel', []);
        $bulk_field = $request->request->get('bulk_field', []);
        $bulk_verify = $request->request->get('bulk_verify', []);
        $bulk_submit = $request->request->get('bulk_submit', []);

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

            $rows = $db->executeQuery('select user_id, id, expires_at,
                    category_id
                from ' . $pp->schema() . '.messages
                where id in (?)',
                [array_keys($selected_messages)],
                [Db::PARAM_INT_ARRAY]);

            foreach ($rows as $row)
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

                $link_render->redirect($vr->get('messages'), $pp->ary(), []);
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

                $link_render->redirect($vr->get('messages'), $pp->ary(), []);
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

                $link_render->redirect($vr->get('messages'), $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $fetch_and_filter = self::fetch_and_filter(
            $request,
            $db,
            $account_render,
            $assets_service,
            $btn_top_render,
            $config_service,
            $heading_render,
            $link_render,
            $pagination_render,
            $select_render,
            $pp,
            $su,
            $vr,
            $typeahead_service
        );

        $messages = $fetch_and_filter['messages'];
        $params = $fetch_and_filter['params'];
        $categories = $fetch_and_filter['categories'];
        $categories_move_options = $fetch_and_filter['categories_move_options'];
        $cat_params = $fetch_and_filter['cat_params'];
        $is_owner = $fetch_and_filter['is_owner'];
        $out = $fetch_and_filter['out'];

        self::set_view_btn_nav(
            $btn_nav_render,
            $pp,
            $params,
            'list'
        );

        if ($bulk_actions_enabled && $pp->is_admin())
        {
            $btn_top_render->local('#bulk_actions', 'Bulk acties', 'envelope-o');
            $btn_nav_render->csv();
        }

        $assets_service->add(['table_sel.js']);

        if (!count($messages))
        {
            $out .= self::no_messages($pagination_render, $menu_service);

            return $this->render('base/navbar.html.twig', [
                'content'   => $out,
                'schema'    => $pp->schema(),
            ]);
        }

        $out .= $pagination_render->get();

        $out .= '<div class="panel panel-info printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-striped ';
        $out .= 'table-bordered table-hover footable csv" ';
        $out .= 'id="msgs" data-sort="false">';

        $out .= '<thead>';
        $out .= '<tr>';

        $th_params = $params;

        $column_ary = self::COLUMNS_DEF_ARY;

        if (isset($params['f']['uid']))
        {
            unset($column_ary['user']);
            unset($column_ary['postcode']);
        }

        if (isset($params['f']['cid']) || !$category_enabled)
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

                if ($col === $params['s']['col'])
                {
                    $dir = $params['s']['dir'] === 'asc' ? 'desc' : 'asc';
                    $fa = $params['s']['dir'] === 'asc' ? 'sort-asc' : 'sort-desc';
                }

                $th_params['s'] = [
                    'col'	    => $col,
                    'dir' 		=> $dir,
                ];

                $out .= $link_render->link_fa($vr->get('messages'), $pp->ary(),
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

            if (!isset($params['f']['uid']))
            {
                $out .= '<td>';
                $out .= $account_render->link($msg['user_id'], $pp->ary());
                $out .= '</td>';

                $out .= '<td>';
                $out .= $msg['postcode'] ?? '';
                $out .= '</td>';
            }

            if ($category_enabled && !($params['f']['cid'] ?? false))
            {
                $out .= '<td>';

                $out .= $link_render->link_no_attr($vr->get('messages'), $pp->ary(),
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

        $out .= $pagination_render->get();

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

            $out .= BulkCnst::TPL_SELECT_BUTTONS;

            $out .= '<h3>Bulk acties met geselecteerd vraag en aanbod</h3>';

            $out .= '<div class="panel panel-info">';
            $out .= '<div class="panel-heading">';

            $out .= '<ul class="nav nav-tabs" role="tablist">';

            if ($expires_at_enabled)
            {
                $out .= '<li class="active"><a href="#extend_tab" ';
                $out .= 'data-toggle="tab">Verlengen</a></li>';
            }

            if ($intersytem_en)
            {
                $out .= '<li>';
                $out .= '<a href="#access_tab" data-toggle="tab">';
                $out .= 'Zichtbaarheid</a><li>';
            }

            if ($category_enabled)
            {
                $out .= '<li><a href="#category_tab" ';
                $out .= 'data-toggle="tab">Categorie</a></li>';
            }

            $out .= '</ul>';

            $out .= '<div class="tab-content">';

            if ($expires_at_enabled)
            {
                $out .= '<div role="tabpanel" class="tab-pane active" id="extend_tab">';
                $out .= '<h3>Vraag en aanbod verlengen</h3>';

                $out .= '<form method="post">';

                $out .= '<div class="form-group">';
                $out .= '<label for="buld_field[extend]" class="control-label">';
                $out .= 'Verlengen met</label>';
                $out .= '<select name="bulk_field[extend]" id="extend" class="form-control">';
                $out .= $select_render->get_options($extend_options, '30');
                $out .= "</select>";
                $out .= '</div>';

                $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[extend]',
                    '%label%'   => 'Ik heb nagekeken dat de juiste berichten geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $out .= '<input type="submit" value="Verlengen" ';
                $out .= 'name="bulk_submit[extend]" class="btn btn-primary btn-lg">';

                $out .= $form_token_service->get_hidden_input();

                $out .= '</form>';

                $out .= '</div>';
            }

            if ($intersytem_en)
            {
                $out .= '<div role="tabpanel" class="tab-pane" id="access_tab">';
                $out .= '<h3>Zichtbaarheid instellen</h3>';
                $out .= '<form method="post">';

                $out .= $item_access_service->get_radio_buttons('bulk_field[access]', '', '', true);

                $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[access]',
                    '%label%'   => 'Ik heb nagekeken dat de juiste berichten geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $out .= '<input type="submit" value="Aanpassen" ';
                $out .= 'name="bulk_submit[access]" class="btn btn-primary btn-lg">';
                $out .= $form_token_service->get_hidden_input();
                $out .= '</form>';
                $out .= '</div>';
            }

            if ($category_enabled)
            {
                $out .= '<div role="tabpanel" class="tab-pane" id="category_tab">';
                $out .= '<h3>Verhuizen naar categorie</h3>';
                $out .= '<form method="post">';

                $out .= strtr(BulkCnst::TPL_SELECT, [
                    '%options%' => $cat_options,
                    '%name%'    => 'bulk_field[category]',
                    '%label%'   => 'Categorie',
                    '%attr%'    => ' required',
                    '%fa%'      => 'clone',
                    '%explain%' => '',
                ]);

                $out .= strtr(BulkCnst::TPL_CHECKBOX, [
                    '%name%'    => 'bulk_verify[category]',
                    '%label%'   => 'Ik heb nagekeken dat de juiste berichten geselecteerd zijn.',
                    '%attr%'    => ' required',
                ]);

                $out .= '<input type="submit" value="Categorie anpassen" ';
                $out .= 'name="bulk_submit[category]" class="btn btn-primary btn-lg">';
                $out .= $form_token_service->get_hidden_input();
                $out .= '</form>';
                $out .= '</div>';
            }

            $out .= '</div>';

            $out .= '<div class="clearfix"></div>';
            $out .= '</div>';

            $out .= '</div></div>';
        }

        $menu_service->set('messages');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }

    static public function no_messages(
        PaginationRender $pagination_render,
        MenuService $menu_service
    ):string
    {
        $out = $pagination_render->get();

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-body">';
        $out .= '<p>Er zijn geen resultaten.</p>';
        $out .= '</div></div>';

        $out .= $pagination_render->get();

        $menu_service->set('messages');

        return $out;
    }

    public static function get_checkbox_filter(
        array $checkbox_ary,
        string $filter_id,
        array $filter_ary
    ):string
    {
        $out = '';

        foreach ($checkbox_ary as $key => $label)
        {
            $out .= strtr(BulkCnst::TPL_CHECKBOX_BTN_INLINE, [
                '%name%'    => 'f[' . $filter_id . '][' . $key . ']',
                '%attr%'    => isset($filter_ary[$filter_id][$key]) ? ' checked' : '',
                '%label%'   => $label,
            ]);
        }

        return $out;
    }

    public static function set_view_btn_nav(
        BtnNavRender $btn_nav_render,
        PageParamsService $pp,
        array $params,
        string $view
    )
    {
        $btn_nav_render->view('messages_list', $pp->ary(),
            $params, 'Lijst', 'align-justify', $view === 'list');

        $btn_nav_render->view('messages_extended', $pp->ary(),
            $params, 'Lijst met omschrijvingen', 'th-list', $view === 'extended');
    }

    public static function fetch_and_filter(
        Request $request,
        Db $db,
        AccountRender $account_render,
        AssetsService $assets_service,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        PaginationRender $pagination_render,
        SelectRender $select_render,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        TypeaheadService $typeahead_service
    ):array
    {
        $service_stuff_enabled = $config_service->get_bool('messages.fields.service_stuff.enabled', $pp->schema());
        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $new_user_days = $config_service->get_int('users.new.days', $pp->schema());
        $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        $sort_col = $sort['col'] ?? 'created';
        $sort_col = isset(self::COLUMNS_DEF_ARY[$sort_col]) ? $sort_col : 'created';

        $sort_dir = $sort['dir'] ?? 'desc';
        $sort_dir = in_array($sort_dir, ['asc', 'desc']) ? $sort_dir : 'desc';

        $pag_start = $pag['start'] ?? 0;
        $pag_limit = $pag['limit'] ?? 25;

        $params = [
            's'	=> [
                'col'	    => $sort_col,
                'dir'		=> $sort_dir,
            ],
            'p'	=> [
                'start'		=> $pag_start,
                'limit'		=> $pag_limit,
            ],
        ];

        $sql = [
            'where'     => [],
            'params'    => [],
            'types'     => [],
        ];

        $is_owner = isset($filter['uid'])
            && $su->is_owner((int) $filter['uid']);

        if (isset($filter['uid'])
            && $filter['uid']
            && !isset($filter['s']))
        {
            $filter['fcode'] = $account_render->str((int) $filter['uid'], $pp->schema());
        }

        if (isset($filter['uid']))
        {
            $params['f']['uid'] = $filter['uid'];
        }

        if (isset($filter['q'])
            && $filter['q'])
        {
            $sql['where'][] = '(m.subject ilike ? or m.content ilike ?)';
            $sql['params'][] = '%' . $filter['q'] . '%';
            $sql['params'][] = '%' . $filter['q'] . '%';
            $sql['types'][] = \PDO::PARAM_INT;
            $sql['types'][] = \PDO::PARAM_INT;
            $params['f']['q'] = $filter['q'];
        }

        if (isset($filter['fcode'])
            && $filter['fcode'] !== '')
        {
            [$fcode] = explode(' ', trim($filter['fcode']));
            $fcode = trim($fcode);

            $fuid = $db->fetchOne('select id
                from ' . $pp->schema() . '.users
                where code = ?',
                [$fcode], [\PDO::PARAM_STR]);

            if ($fuid)
            {
                $sql['where'][] = 'u.id = ?';
                $sql['params'][] = $fuid;
                $sql['types'][] = \PDO::PARAM_INT;

                $fcode = $account_render->str((int) $fuid, $pp->schema());
                $params['f']['fcode'] = $fcode;
            }
            else
            {
                $sql['where'][] = '1 = 2';
            }
        }

        $filter_valid_expired = $expires_at_enabled
            && (isset($filter['valid']) xor isset($filter['expired']));

        if ($filter_valid_expired)
        {
            if (isset($filter['valid']))
            {
                $sql['where'][] = '(m.expires_at >= timezone(\'utc\', now()) or m.expires_at is null)';
                $params['f']['valid'] = '1';
            }
            else
            {
                $sql['where'][] = 'm.expires_at < timezone(\'utc\', now())';
                $params['f']['expired'] = '1';
            }
        }

        $filter_offer_want = isset($filter['want']) xor isset($filter['offer']);

        if ($filter_offer_want)
        {
            if (isset($filter['want']))
            {
                $sql['where'][] = 'm.offer_want = \'want\'';
                $params['f']['want'] = '1';
            }
            else
            {
                $sql['where'][] = 'm.offer_want = \'offer\'';
                $params['f']['offer'] = '1';
            }
        }

        $filter_service_stuff = $service_stuff_enabled
            && (isset($filter['service']) xor isset($filter['stuff']));

        if ($filter_service_stuff)
        {
            if (isset($filter['service']))
            {
                $sql['where'][] = 'm.service_stuff = \'service\'';
                $params['f']['service'] = '1';
            }
            else
            {
                $sql['where'][] = 'm.service_stuff = \'stuff\'';
                $params['f']['stuff'] = '1';
            }
        }

        $filter_user_status = isset($filter['u-new'])
            || isset($filter['u-leaving'])
            || isset($filter['u-active']);

        if ($filter_user_status)
        {
            $ustatus_sql = [];

            if (isset($filter['u-new']))
            {
                $ustatus_sql[] = '(u.adate > ? and u.status = 1)';
                $sql['params'][] = $new_user_treshold;
                $sql['types'][] = Types::DATETIME_IMMUTABLE;
                $params['f']['u-new'] = '1';
            }

            if (isset($filter['u-leaving']))
            {
                $ustatus_sql[] = 'u.status = 2';
                $params['f']['u-leaving'] = '1';
            }

            if (isset($filter['u-active']))
            {
                $ustatus_sql[] = '(u.adate <= ? and u.status = 1)';
                $sql['params'][] = $new_user_treshold;
                $sql['types'][] = Types::DATETIME_IMMUTABLE;
                $params['f']['u-active'] = '1';
            }

            if (count($ustatus_sql))
            {
                $sql['where'][] = '(' . implode(' or ', $ustatus_sql) . ')';
            }
        }

        if ($pp->is_guest())
        {
            $sql['where'][] = 'm.access = \'guest\'';
        }

        $sql['where'][] = 'u.status in (1, 2)';

        $omit_cat_sql = $sql;

        if (isset($filter['cid'])
            && $filter['cid']
            && $category_enabled)
        {
            if ($filter['cid'] === 'null')
            {
                $sql['where'][] = 'm.category_id is null';
            }
            else
            {
                $cat_lr = $db->fetchAssociative('select left_id, right_id
                    from ' . $pp->schema() . '.categories
                    where id = ?',
                    [$filter['cid']],
                    [\PDO::PARAM_INT]);

                if (!$cat_lr)
                {
                    throw new BadRequestHttpException('Category not found, id:' . $filter['cid']);
                }

                $sql['where'][] = 'c.left_id >= ? and c.right_id <= ?';
                $sql['params'][] = $cat_lr['left_id'];
                $sql['params'][] = $cat_lr['right_id'];
                $sql['types'][] = \PDO::PARAM_INT;
                $sql['types'][] = \PDO::PARAM_INT;
            }

            $params['f']['cid'] = $filter['cid'];
        }

        $sql_where = ' and ' . implode(' and ', $sql['where']) . ' ';

        $sort_ary = self::COLUMNS_DEF_ARY[$params['s']['col']]['sort'];
        $order_query = [];
        foreach ($sort_ary['col'] as $col)
        {
            $order_query[] = $col . ' ' . $params['s']['dir'];
        }

        $sql_pag = $sql;
        $sql_pag['params'][] = $params['p']['limit'];
        $sql_pag['types'][] = \PDO::PARAM_INT;
        $sql_pag['params'][] = $params['p']['start'];
        $sql_pag['types'][] = \PDO::PARAM_INT;

        $query = 'select m.*, u.postcode
            from ' . $pp->schema() . '.messages m
            inner join ' . $pp->schema() . '.users u
                on m.user_id = u.id
            left join ' . $pp->schema() . '.categories c
                on m.category_id = c.id
            left join ' . $pp->schema() . '.categories cp
                on c.parent_id = cp.id
            where 1 = 1' . $sql_where . '
            order by ' . implode(', ', $order_query) . '
            limit ? offset ?';

        $messages = [];

        $st = $db->executeQuery($query, $sql_pag['params'], $sql_pag['types']);

        while ($msg = $st->fetch())
        {
            $msg['label'] = MessagesShowController::get_label($msg['offer_want']);
            $messages[] = $msg;
        }

        $omit_cat_sql_where = ' and ' . implode(' and ', $omit_cat_sql['where']) . ' ';

        $cat_count_ary = [];
        $no_cat_count = 0;

        $cat_count_query = 'select count(m.*), m.category_id
            from ' . $pp->schema() . '.messages m, ' .
                $pp->schema() . '.users u
            where m.user_id = u.id
                ' . $omit_cat_sql_where . '
            group by m.category_id';

        $st = $db->executeQuery($cat_count_query,
            $omit_cat_sql['params'], $omit_cat_sql['types']);

        while($row = $st->fetch())
        {
            if (isset($row['category_id']))
            {
                $cat_count_ary[$row['category_id']] = $row['count'];
                continue;
            }

            $no_cat_count = $row['count'];
        }

        if (isset($filter['cid'])
            && $filter['cid']
            && $category_enabled)
        {
            $row_count = $db->fetchOne('select count(m.*)
                from ' . $pp->schema() . '.messages m
                inner join ' . $pp->schema() . '.users u
                    on m.user_id = u.id
                left join ' . $pp->schema() . '.categories c
                    on c.id = m.category_id
                where 1 = 1'. $sql_where,
                $sql['params'], $sql['types']);
        }
        else
        {
            $row_count = array_sum($cat_count_ary);
            $row_count += $no_cat_count;
        }

        $pagination_render->init($vr->get('messages'), $pp->ary(),
            $row_count, $params);

        $categories_filter_options = [];
        $categories_filter_options[''] = '-- alle categorieën --';

        if ($no_cat_count)
        {
            $categories_filter_options['null'] = '-- zonder categorie (' . $no_cat_count . ')-- ';
        }

        $categories_move_options = ['' => ['name' => '']];

        $categories = [];
        $cat_params  = [];
        $cat_params_sort = $params;

        if ($no_cat_count)
        {
            $categories['null'] = '** zonder categorie **';
            $cat_params['null'] = $cat_params_sort;
            $cat_params['null']['f']['cid'] = 'null';
        }

        if ($params['s']['col'] === 'category')
        {
            unset($cat_params_sort['s']);
        }

        $parent_name = '***';

        $st = $db->executeQuery('select *
            from ' . $pp->schema() . '.categories
            order by left_id asc');

        while ($row = $st->fetch())
        {
            $cat_id = $row['id'];
            $parent_id = $row['parent_id'];
            $name = $row['name'];
            $parent_name = isset($parent_id) ? $parent_name : $name;
            $cat_ident = isset($parent_id) ? ' . > . ' : '';
            $count_str = isset($cat_count_ary[$cat_id]) ? ' (' . $cat_count_ary[$cat_id] . ')' : '';
            $categories_filter_options[$cat_id] = $cat_ident . $name . $count_str;
            $full_name = isset($parent_id) ? $parent_name . ' > ' : '';
            $full_name .= $name;
            $categories[$cat_id] = $full_name;

            $cat_params[$cat_id] = $cat_params_sort;
            $cat_params[$cat_id]['f']['cid'] = $cat_id;

            if (isset($parent_id))
            {
                $categories_move_options[$parent_id]['children'][$cat_id] = [
                    'name'  => $name . $count_str,
                ];
            }
            else
            {
                $categories_move_options[$cat_id] = [
                    'name'          => $name . $count_str,
                    'children'      => [],
                ];
            }
        }

        if ($pp->is_admin() || $pp->is_user())
        {
            if ($is_owner || !isset($filter['uid']))
            {
                $btn_top_render->add('messages_add', $pp->ary(),
                    [], 'Vraag of aanbod toevoegen');
            }

            if (isset($filter['uid']))
            {
                if ($pp->is_admin() && !$is_owner)
                {
                    $str = 'Vraag of aanbod voor ';
                    $str .= $account_render->str((int) $filter['uid'], $pp->schema());

                    $btn_top_render->add('messages_add', $pp->ary(),
                        ['uid' => $filter['uid']], $str);
                }
            }
        }

        $assets_service->add(['messages_filter.js']);

        $filter_panel_open = (($filter['fcode'] ?? false) && !isset($filter['uid']))
            || $filter_offer_want
            || $filter_valid_expired
            || $filter_service_stuff
            || $filter_user_status;

        $filtered = ($filter['q'] ?? false) || $filter_panel_open;

        if (isset($filter['uid']))
        {
            if ($is_owner)
            {
                $heading_render->add('Mijn vraag en aanbod');
            }
            else
            {
                $heading_render->add_raw($link_render->link_no_attr($vr->get('messages'), $pp->ary(),
                    ['f' => ['uid' => $filter['uid']]],
                    'Vraag en aanbod'));

                $heading_render->add(' van ');
                $heading_render->add_raw($account_render->link((int) $filter['uid'], $pp->ary()));
            }
        }
        else
        {
            $heading_render->add('Vraag en aanbod');
        }

        if (isset($filter['cid']) && $filter['cid'] && $category_enabled)
        {
            if ($filter['cid'] === 'null')
            {
                $heading_render->add(', zonder categorie');
            }
            else
            {
                $heading_render->add(', categorie "' . $categories[$filter['cid']] . '"');
            }
        }

        $heading_render->add_filtered($filtered);
        $heading_render->fa('newspaper-o');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="get" class="form-horizontal">';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-';
        $out .= $category_enabled ? '5' : '10';
        $out .= '">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="q" value="';
        $out .= $filter['q'] ?? '';
        $out .= '" name="f[q]" placeholder="Zoeken">';
        $out .= '</div>';
        $out .= '</div>';

        if ($category_enabled)
        {
            $out .= '<div class="col-sm-5 col-xs-10">';
            $out .= '<div class="input-group margin-bottom">';
            $out .= '<span class="input-group-addon">';
            $out .= '<i class="fa fa-clone"></i>';
            $out .= '</span>';
            $out .= '<select class="form-control" id="cid" name="f[cid]">';

            $cid = (string) ($filter['cid'] ?? '');

            $out .= $select_render->get_options($categories_filter_options, $cid);

            $out .= '</select>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '<div class="col-sm-2 col-xs-2">';
        $out .= '<button class="btn btn-default btn-block" title="Meer filters" ';
        $out .= 'type="button" ';
        $out .= 'data-toggle="collapse" data-target="#filters">';
        $out .= '<i class="fa fa-caret-down"></i><span class="hidden-xs hidden-sm"> ';
        $out .= 'Meer</span></button>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<div id="filters"';
        $out .= $filter_panel_open ? '' : ' class="collapse"';
        $out .= '>';

        $out .= '<div class="row">';
        $out .= '<div class="col-sm-4">';
        $out .= '<div class="input-group margin-bottom custom-checkbox">';

        foreach (MessageTypeCnst::OFFER_WANT_TPL_ARY as $key => $d)
        {
            $out .= strtr(BulkCnst::TPL_CHECKBOX_BTN_INLINE, [
                '%name%'        => 'f[' . $key . ']',
                '%attr%'        => isset($filter[$key]) ? ' checked' : '',
                '%label%'       => $d['label'],
                '%btn_class%'   => $d['btn_class']
            ]);
        }

        $out .= '</div>';
        $out .= '</div>';

        if ($service_stuff_enabled)
        {
            $out .= '<div class="col-sm-4">';
            $out .= '<div class="input-group margin-bottom custom-checkbox">';

            foreach (MessageTypeCnst::SERVICE_STUFF_TPL_ARY as $key => $d)
            {
                $out .= strtr(BulkCnst::TPL_CHECKBOX_BTN_INLINE, [
                    '%name%'        => 'f[' . $key . ']',
                    '%attr%'        => isset($filter[$key]) ? ' checked' : '',
                    '%label%'       => $d['label'],
                    '%btn_class%'   => $d['btn_class']
                ]);
            }

            $out .= '</div>';
            $out .= '</div>';
        }

        if ($expires_at_enabled)
        {
            $out .= '<div class="col-sm-4">';
            $out .= '<div class="input-group margin-bottom custom-checkbox">';

            foreach (MessageTypeCnst::VALID_EXPIRED_TPL_ARY as $key => $d)
            {
                $out .= strtr(BulkCnst::TPL_CHECKBOX_BTN_INLINE, [
                    '%name%'        => 'f[' . $key . ']',
                    '%attr%'        => isset($filter[$key]) ? ' checked' : '',
                    '%label%'       => $d['label'],
                    '%btn_class%'   => $d['btn_class']
                ]);
            }

            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '</div>';

        $out .= '<div class="row">';
        $out .= '<div class="col-sm-12">';
        $out .= '<div class="input-group margin-bottom custom-checkbox">';

        foreach (MessageTypeCnst::USERS_TPL_ARY as $key => $d)
        {
            $out .= strtr(BulkCnst::TPL_CHECKBOX_BTN_INLINE, [
                '%name%'        => 'f[' . $key . ']',
                '%attr%'        => isset($filter[$key]) ? ' checked' : '',
                '%label%'       => $d['label'],
                '%btn_class%'   => $d['btn_class']
            ]);
        }

        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-10">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="fcode_addon">Van ';
        $out .= '<span class="fa fa-user"></span></span>';

        $out .= '<input type="text" class="form-control" ';
        $out .= 'aria-describedby="fcode_addon" ';
        $out .= 'data-typeahead="';

        $out .= $typeahead_service->ini($pp->ary())
            ->add('accounts', ['status'	=> 'active'])
            ->str([
                'filter'		=> 'accounts',
                'newuserdays'	=> $new_user_days,
            ]);

        $out .= '" ';
        $out .= 'name="f[fcode]" id="fcode" placeholder="Account" ';
        $out .= 'value="';
        $out .= $filter['fcode'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-2">';
        $out .= '<input type="submit" id="filter_submit" ';
        $out .= 'value="Toon" class="btn btn-default btn-block" ';
        $out .= 'name="f[s]">';
        $out .= '</div>';

        $out .= '</div>';
        $out .= '</div>';

        $params_form = array_merge($params, $pp->ary());
        unset($params_form['role_short']);
        unset($params_form['system']);
        unset($params_form['f']);
        unset($params_form['uid']);
        unset($params_form['p']['start']);

        $params_form = http_build_query($params_form, 'prefix', '&');
        $params_form = urldecode($params_form);
        $params_form = explode('&', $params_form);

        foreach ($params_form as $param)
        {
            [$name, $value] = explode('=', $param);

            if (!isset($value) || $value === '')
            {
                continue;
            }

            $out .= '<input name="' . $name . '" ';
            $out .= 'value="' . $value . '" type="hidden">';
        }

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return [
            'messages'                  => $messages,
            'params'                    => $params,
            'categories'                => $categories,
            'cat_params'                => $cat_params,
            'categories_move_options'   => $categories_move_options,
            'is_owner'                  => $is_owner,
            'out'                       => $out,
        ];
    }
}
