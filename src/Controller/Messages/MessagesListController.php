<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Doctrine\DBAL\Connection as Db;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Cnst\BulkCnst;
use App\Controller\Messages\MessagesShowController;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\PaginationRender;
use App\Render\SelectRender;
use App\Service\AlertService;
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

class MessagesListController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        FormTokenService $form_token_service,
        AccountRender $account_render,
        AlertService $alert_service,
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
        $errors = [];

        $selected_messages = $request->request->get('sel', []);
        $bulk_field = $request->request->get('bulk_field', []);
        $bulk_verify = $request->request->get('bulk_verify', []);
        $bulk_submit = $request->request->get('bulk_submit', []);

        if ($request->isMethod('POST')
            && !$pp->is_guest()
            && count($bulk_submit))
        {
            if (count($bulk_submit) > 1)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Meer dan 1 submit.');
            }

            if (count($bulk_field) > 1)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Request voor meer dan één veld.');
            }

            if (count($bulk_verify) > 1)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Meer dan één bevestigingsvakje.');
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
                throw new BadRequestHttpException('Ongeldig formulier. Actie nazichtvakje klopt niet.');
            }

            if (isset($bulk_field_action)
                && $bulk_field_action !== $bulk_submit_action)
            {
                throw new BadRequestHttpException('Ongeldig formulier. Actie waardeveld klopt niet.');
            }

            if (!isset($bulk_field_action))
            {
                throw new BadRequestHttpException('Ongeldig formulier. Waarde veld ontbreekt.');
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
                    throw new AccessDeniedHttpException('Je bent niet de eigenaar van vraag of aanbod ' .
                        $row['subject'] . ' ( ' . $row['id'] . ')');
                }

                $update_msgs_ary[$row['id']] = $row;
            }

            if ($bulk_submit_action === 'extend' && !count($errors))
            {
                foreach ($update_msgs_ary as $id => $row)
                {
                    $expires_at = $row['expires_at'];
                    $expires_at = gmdate('Y-m-d H:i:s', strtotime($expires_at . ' UTC') + (86400 * (int) $bulk_field_value));

                    $msg_update = [
                        'expires_at'		=> $expires_at,
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
                $to_category_id = (int) $bulk_field_value;

                $test_category_id = $db->fetchColumn('select id
                    from ' . $pp->schema() . '.categories
                    where id_parent <> 0
                        and leafnote = 1
                        and id = ?', [$to_category_id]);

                if (!$test_category_id)
                {
                    throw new BadRequestHttpException('Ongeldig categorie id ' . $to_category_id);
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

        if ($pp->is_admin())
        {
            $btn_top_render->local('#bulk_actions', 'Bulk acties', 'envelope-o');
            $btn_nav_render->csv();
        }

        $show_visibility_column = !$pp->is_guest() && $intersystems_service->get_count($pp->schema());

        if (!count($messages))
        {
            $out .= self::no_messages($pagination_render, $menu_service);

            return $this->render('messages/messages_list.html.twig', [
                'content'   => $out,
                'schema'    => $pp->schema(),
            ]);
        }

        $out .= $pagination_render->get();

        $out .= '<div class="table-responsive border border-secondary-li rounded mb-3">';
        $out .= '<table class="table table-striped mb-0 ';
        $out .= 'table-bordered table-hover bg-default" ';
        $out .= 'data-sort="false" data-footable data-csv>';

        $out .= '<thead>';
        $out .= '<tr>';

        $th_params = $params;

        $table_header_ary = self::get_table_header_ary($params, $show_visibility_column);

        foreach ($table_header_ary as $key_orderby => $data)
        {
            $out .= '<th';

            if (isset($data['data_hide']))
            {
                $out .= ' data-hide="' . $data['data_hide'] . '"';
            }

            $out .= '>';

            if (isset($data['no_sort']))
            {
                $out .= $data['lbl'];
            }
            else
            {
                $th_params['s'] = [
                    'orderby'	=> $key_orderby,
                    'asc' 		=> $data['asc'],
                ];

                $out .= $link_render->link_fa($vr->get('messages'), $pp->ary(),
                    $th_params, $data['lbl'], [], $data['fa']);
            }

            $out .= '</th>';
        }

        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach($messages as $msg)
        {
            $out .= '<tr';
            $out .= strtotime($msg['expires_at']) < time() ? ' class="danger"' : '';
            $out .= '>';

            $out .= '<td>';

            if ($pp->is_admin() || $is_owner)
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

            if (!($params['f']['cid'] ?? false))
            {
                $out .= '<td>';
                $out .= $link_render->link_no_attr($vr->get('messages'), $pp->ary(),
                    $cat_params[$msg['category_id']],
                    $categories[$msg['category_id']]);
                $out .= '</td>';
            }

            $out .= '<td>';
            $out .= $date_format_service->get($msg['expires_at'], 'day', $pp->schema());
            $out .= '</td>';

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

        $out .= $pagination_render->get();

        if (($pp->is_admin() || $is_owner) && count($messages))
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

            $out .= BulkCnst::TPL_SELECT_BUTTONS;

            $out .= '<h3>Bulk acties met geselecteerd vraag en aanbod</h3>';

            $out .= '<div class="card fcard fcard-info">';
            $out .= '<div class="card-body">';

            $out .= '<ul class="nav nav-tabs mb-2" role="tablist">';

            $out .= '<li class="nav-item">';
            $out .= '<a href="#extend_tab" ';
            $out .= 'class="nav-link active" ';
            $out .= 'data-toggle="tab">Verlengen</a></li>';

            if ($config_service->get_intersystem_en($pp->schema()))
            {
                $out .= '<li class="nav-item">';
                $out .= '<a class="nav-link" ';
                $out .= 'href="#access_tab" data-toggle="tab">';
                $out .= 'Zichtbaarheid</a><li>';
            }

            $out .= '<li class="nav-item">';
            $out .= '<a class="nav-link" href="#category_tab" ';
            $out .= 'data-toggle="tab">Categorie</a></li>';

            $out .= '</ul>';

            $out .= '<div class="tab-content">';

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

            if ($config_service->get_intersystem_en($pp->schema()))
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

            $out .= '<div role="tabpanel" class="tab-pane" id="category_tab">';
            $out .= '<h3>Verhuizen naar categorie</h3>';
            $out .= '<form method="post">';

            $out .= strtr(BulkCnst::TPL_SELECT, [
                '%options%' => $select_render->get_options($categories_move_options, ''),
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

            $out .= '</div>';

            $out .= '<div class="clearfix"></div>';
            $out .= '</div>';

            $out .= '</div></div>';
        }

        $menu_service->set('messages');

        return $this->render('messages/messages_list.html.twig', [
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

        $out .= '<div class="card card-default">';
        $out .= '<div class="card-body">';
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
            $id = 'f_' . $filter_id . '_' . $key;
            $out .= '<div class="custom-control custom-checkbox custom-control-inline">';
            $out .= '<input type="checkbox" id="' . $id . '" ';
            $out .= 'class="custom-control-input" ';
            $out .= 'name="f[' . $filter_id . '][' . $key . ']"';
            $out .= isset($filter_ary[$filter_id][$key]) ? ' checked' : '';
            $out .= '>&nbsp;';
            $out .= '<label class="custom-control-label" for="' . $id . '">';
            $out .= '<span class="btn btn-default border border-secondary-li">';
            $out .= $label;
            $out .= '</span>';
            $out .= '</label>';
            $out .= '</div>';
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

    public static function get_table_header_ary(
        array $params,
        bool $show_visibility_column
    ):array
    {
        $asc_preset_ary = [
            'asc'	=> '0',
            'fa' 	=> 'sort',
        ];

        $table_header_ary = [
            'm.is_offer' => array_merge($asc_preset_ary, [
                'lbl' => 'V/A']),
            'm.subject' => array_merge($asc_preset_ary, [
                'lbl' => 'Wat']),
        ];

        if (!isset($params['f']['uid']))
        {
            $table_header_ary += [
                'u.name'	=> array_merge($asc_preset_ary, [
                    'lbl' 		=> 'Wie',
                    'data_hide' => 'phone,tablet',
                ]),
                'u.postcode'	=> array_merge($asc_preset_ary, [
                    'lbl' 		=> 'Postcode',
                    'data_hide'	=> 'phone,tablet',
                ]),
            ];
        }

        if (!($params['f']['cid'] ?? false))
        {
            $table_header_ary += [
                'c.fullname' => array_merge($asc_preset_ary, [
                    'lbl' 		=> 'Categorie',
                    'data_hide'	=> 'phone, tablet',
                ]),
            ];
        }

        $table_header_ary += [
            'm.expires_at' => array_merge($asc_preset_ary, [
                'lbl' 	=> 'Geldig tot',
                'data_hide'	=> 'phone, tablet',
            ]),
        ];

        if ($show_visibility_column)
        {
            $table_header_ary += [
                'm.access' => array_merge($asc_preset_ary, [
                    'lbl' 	=> 'Zichtbaarheid',
                    'data_hide'	=> 'phone, tablet',
                ]),
            ];
        }

        $table_header_ary[$params['s']['orderby']]['asc']
            = $params['s']['asc'] ? '0' : '1';
        $table_header_ary[$params['s']['orderby']]['fa']
            = $params['s']['asc'] ? 'sort-asc' : 'sort-desc';

        unset($table_header_ary['m.created_at']);

        return $table_header_ary;
    }

    public static function fetch_and_filter(
        Request $request,
        Db $db,
        AccountRender $account_render,
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
        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        $is_owner = isset($filter['uid'])
            && $su->is_owner((int) $filter['uid']);

        $params = [
            's'	=> [
                'orderby'	=> $sort['orderby'] ?? 'm.created_at',
                'asc'		=> $sort['asc'] ?? 0,
            ],
            'p'	=> [
                'start'		=> $pag['start'] ?? 0,
                'limit'		=> $pag['limit'] ?? 25,
            ],
        ];

        $params_sql = $where_sql = [];

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
            $where_sql[] = '(m.subject ilike ? or m.content ilike ?)';
            $params_sql[] = '%' . $filter['q'] . '%';
            $params_sql[] = '%' . $filter['q'] . '%';
            $params['f']['q'] = $filter['q'];
        }

        if (isset($filter['fcode'])
            && $filter['fcode'] !== '')
        {
            [$fcode] = explode(' ', trim($filter['fcode']));
            $fcode = trim($fcode);

            $fuid = $db->fetchColumn('select id
                from ' . $pp->schema() . '.users
                where code = ?', [$fcode]);

            if ($fuid)
            {
                $where_sql[] = 'u.id = ?';
                $params_sql[] = $fuid;

                $fcode = $account_render->str((int) $fuid, $pp->schema());
                $params['f']['fcode'] = $fcode;
            }
            else
            {
                $where_sql[] = '1 = 2';
            }
        }

        $filter_valid = isset($filter['valid'])
            && (isset($filter['valid']['yes']) xor isset($filter['valid']['no']));

        if ($filter_valid)
        {
            if (isset($filter['valid']['yes']))
            {
                $where_sql[] = 'm.expires_at >= now()';
                $params['f']['valid']['yes'] = 'on';
            }
            else
            {
                $where_sql[] = 'm.expires_at < now()';
                $params['f']['valid']['no'] = 'on';
            }
        }

        $filter_type = isset($filter['type'])
            && (isset($filter['type']['want']) xor isset($filter['type']['offer']));

        if ($filter_type)
        {
            if (isset($filter['type']['want']))
            {
                $where_sql[] = 'm.is_want = \'t\'';
                $params['f']['type']['want'] = 'on';
            }
            else
            {
                $where_sql[] = 'm.is_offer = \'t\'';
                $params['f']['type']['offer'] = 'on';
            }
        }

        if ($pp->is_guest())
        {
            $where_sql[] = 'm.access = \'guest\'';
        }

        $where_sql[] = 'u.status in (1, 2)';

        $no_cat_where_sql = $where_sql;
        $no_cat_params_sql = $params_sql;

        if (isset($filter['cid'])
            && $filter['cid'])
        {
            $cat_ary = [];

            $st = $db->prepare('select id
                from ' . $pp->schema() . '.categories
                where id_parent = ?');
            $st->bindValue(1, $filter['cid']);
            $st->execute();

            while ($row = $st->fetch())
            {
                $cat_ary[] = $row['id'];
            }

            if (count($cat_ary))
            {
                $where_sql[] = 'm.category_id in (' . implode(', ', $cat_ary) . ')';
            }
            else
            {
                $where_sql[] = 'm.category_id = ?';
                $params_sql[] = $filter['cid'];
            }

            $params['f']['cid'] = $filter['cid'];
        }

        $where_sql = ' and ' . implode(' and ', $where_sql) . ' ';

        $query = 'select m.*, u.postcode, c.fullname
            from ' . $pp->schema() . '.messages m, ' .
                $pp->schema() . '.users u, ' .
                $pp->schema() . '.categories c
                where m.user_id = u.id
                    and m.category_id = c.id' . $where_sql . '
            order by ' . $params['s']['orderby'] . ' ';

        $query .= $params['s']['asc'] ? 'asc ' : 'desc ';
        $query .= ' limit ' . $params['p']['limit'];
        $query .= ' offset ' . $params['p']['start'];

        $messages = [];

        $st = $db->executeQuery($query, $params_sql);

        while ($msg = $st->fetch())
        {
            $msg['type'] = $msg['is_offer'] ? 'offer' : 'want';
            $msg['label'] = MessagesShowController::get_label($msg['type']);

            $messages[] = $msg;
        }

        $no_cat_where_sql = ' and ' . implode(' and ', $no_cat_where_sql) . ' ';

        $cat_count_ary = [];

        $cat_count_query = 'select count(m.*), m.category_id
            from ' . $pp->schema() . '.messages m, ' .
                $pp->schema() . '.users u
            where m.user_id = u.id
                ' . $no_cat_where_sql . '
            group by m.category_id';

        $st = $db->executeQuery($cat_count_query, $no_cat_params_sql);

        while($row = $st->fetch())
        {
            $cat_count_ary[$row['category_id']] = $row['count'];
        }

        if (isset($filter['cid'])
            && $filter['cid'])
        {
            $row_count = $db->fetchColumn('select count(m.*)
                from ' . $pp->schema() . '.messages m, ' .
                    $pp->schema() . '.users u
                where m.user_id = u.id' . $where_sql, $params_sql);
        }
        else
        {
            $row_count = array_sum($cat_count_ary);
        }

        $pagination_render->init($vr->get('messages'), $pp->ary(),
            $row_count, $params);

        $categories_filter_options = ['' => '-- alle categorieën --'];
        $categories_move_options = ['' => ''];

        $categories = $cat_params  = [];

        $cat_params_sort = $params;

        if ($params['s']['orderby'] === 'c.fullname')
        {
            unset($cat_params_sort['s']);
        }

        if (isset($filter['uid']))
        {
            $st = $db->executeQuery('select c.*
                from ' . $pp->schema() . '.categories c, ' .
                    $pp->schema() . '.messages m
                where m.category_id = c.id
                    and m.user_id = ?
                order by c.fullname', [$filter['uid']]);
        }
        else
        {
            $st = $db->executeQuery('select *
                from ' . $pp->schema() . '.categories
                order by fullname');
        }

        while ($row = $st->fetch())
        {
            $categories_filter_options[$row['id']] = $row['id_parent'] ? ' . . ' : '';
            $categories_filter_options[$row['id']] .= $row['name'];

            $count_msgs = $cat_count_ary[$row['id']] ?? 0;

            if ($row['id_parent'] && $count_msgs)
            {
                $categories_filter_options[$row['id']] .= ' (' . $count_msgs . ')';
            }

            $categories[$row['id']] = $row['fullname'];

            $cat_params[$row['id']] = $cat_params_sort;
            $cat_params[$row['id']]['f']['cid'] = $row['id'];

            if ($row['id_parent'])
            {
                $categories_move_options[$row['id']] = $row['fullname'];
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

        $filter_panel_open = (($filter['fcode'] ?? false) && !isset($filter['uid']))
            || $filter_type
            || $filter_valid;

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

        if (isset($filter['cid']) && $filter['cid'])
        {
            $heading_render->add(', categorie "' . $categories[$filter['cid']] . '"');
        }

        $heading_render->add_filtered($filtered);
        $heading_render->fa('newspaper-o');

        $out = '<div class="card fcard fcard-info mb-2">';
        $out .= '<div class="card-body pb-1">';

        $out .= '<form method="get" class="form-horizontal">';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-5">';
        $out .= '<div class="input-group mb-2">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="q" value="';
        $out .= $filter['q'] ?? '';
        $out .= '" name="f[q]" placeholder="Zoeken">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-5">';
        $out .= '<div class="input-group mb-2">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-clone"></i>';
        $out .= '</span>';
        $out .= '</span>';
        $out .= '<select class="form-control" name="f[cid]" data-auto-submit>';

        $cid = (string) ($filter['cid'] ?? '');

        $out .= $select_render->get_options($categories_filter_options, $cid);

        $out .= '</select>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-2 mb-2">';
        $out .= '<button class="btn btn-default btn-block ';
        $out .= 'border border-secondary-li" title="Meer filters" ';
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

        $offerwant_options = [
            'want'		=> 'Vraag',
            'offer'		=> 'Aanbod',
        ];

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group mb-2">';

        $out .= self::get_checkbox_filter($offerwant_options, 'type', $filter);

        $out .= '</div>';
        $out .= '</div>';

        $valid_options = [
            'yes'		=> 'Geldig',
            'no'		=> 'Vervallen',
        ];

        $out .= '<div class="col-sm-6">';
        $out .= '<div class="input-group mb-2">';

        $out .= self::get_checkbox_filter($valid_options, 'valid', $filter);

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-10">';
        $out .= '<div class="input-group mb-2">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= 'Van&nbsp;';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '</span>';

        $out .= '<input type="text" class="form-control" ';
        $out .= 'data-typeahead="';

        $out .= $typeahead_service->ini($pp->ary())
            ->add('accounts', ['status'	=> 'active'])
            ->str([
                'filter'		=> 'accounts',
                'newuserdays'	=> $config_service->get('newuserdays', $pp->schema()),
            ]);

        $out .= '" ';
        $out .= 'name="f[fcode]" id="fcode" placeholder="Account" ';
        $out .= 'value="';
        $out .= $filter['fcode'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-2 mb-2">';
        $out .= '<input type="submit" ';
        $out .= 'value="Toon" ';
        $out .= 'class="btn btn-default btn-block border border-secondary-li" ';
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
