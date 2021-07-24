<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use App\Cnst\AccessCnst;
use App\Cnst\BulkCnst;
use App\Render\AccountRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\TypeaheadService;
use App\Service\AlertService;
use App\Service\FormTokenService;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ContactsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/contacts',
        name: 'contacts',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'contacts',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        AlertService $alert_service,
        SelectRender $select_render,
        LinkRender $link_render,
        FormTokenService $form_token_service,
        TypeaheadService $typeahead_service,
        ConfigService $config_service,
        AccountRender $account_render,
        PageParamsService $pp,
        ItemAccessService $item_access_service
    ):Response
    {
        $intersystem_enabled = $config_service->get_bool('intersystem.enabled', $pp->schema());
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

        $selected_contacts = $request->request->get('sel', []);
        $bulk_field = $request->request->get('bulk_field', []);
        $bulk_verify = $request->request->get('bulk_verify', []);
        $bulk_submit = $request->request->get('bulk_submit', []);

        if ($request->isMethod('POST')
            && count($bulk_submit))
        {
            $errors = [];

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

            if (!count($selected_contacts))
            {
                $errors[] = 'Selecteer ten minste één contact voor deze actie.';
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

            if ($bulk_submit_action === 'access' && !count($errors))
            {
                if (!in_array($bulk_field_value, ['admin', 'user', 'guest']))
                {
                    throw new BadRequestHttpException('Unvalid value: ' . $bulk_field_value);
                }

                $db->executeStatement('update ' . $pp->schema() . '.contact
                    set access = ?
                    where id in (?)',
                    [$bulk_field_value, array_keys($selected_contacts)],
                    [\PDO::PARAM_STR, Db::PARAM_INT_ARRAY]);

                if (count($selected_contacts) > 1)
                {
                    $alert_service->success('De contacten zijn aangepast.');
                }
                else
                {
                    $alert_service->success('Het contact is aangepast.');
                }

                $link_render->redirect('contacts', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $filter = $request->query->get('f', []);
        $pag = $request->query->get('p', []);
        $sort = $request->query->get('s', []);

        $params = [
            's'	=> [
                'orderby'	=> $sort['orderby'] ?? 'c.id',
                'asc'		=> $sort['asc'] ?? 0,
            ],
            'p'	=> [
                'start'		=> $pag['start'] ?? 0,
                'limit'		=> $pag['limit'] ?? 25,
            ],
        ];

        $sql_map = [
            'where'     => [],
            'where_or'  => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [];
        $sql['common'] = $sql_map;
        $sql['common']['where'][] = '1 = 1';

        if (isset($filter['uid']))
        {
            $params['f']['uid'] = $filter['uid'];
            $filter['code'] = $account_render->str((int) $filter['uid'], $pp->schema());
        }

        $filter_code = isset($filter['code']) && $filter['code'];

        if ($filter_code)
        {
            [$code] = explode(' ', trim($filter['code']));

            $fuid = $db->fetchOne('select id
                from ' . $pp->schema() . '.users
                where code = ?', [$code], [\PDO::PARAM_STR]);

            $sql['code'] = $sql_map;

            if ($fuid)
            {
                $sql['code']['where'][]= 'c.user_id = ?';
                $sql['code']['params'][]= $fuid;
                $sql['code']['types'][]= \PDO::PARAM_INT;
                $params['f']['code'] = $account_render->str($fuid, $pp->schema());
            }
            else
            {
                $sql['code']['where'][]= '1 = 2';
            }
        }

        $filter_q = isset($filter['q']) && $filter['q'];

        if ($filter_q)
        {
            $sql['q'] = $sql_map;
            $sql['q']['where'][]= '(c.value ilike ? or c.comments ilike ?)';
            $sql['q']['params'][]= '%' . $filter['q'] . '%';
            $sql['q']['params'][]= '%' . $filter['q'] . '%';
            $sql['q']['types'][]= \PDO::PARAM_STR;
            $sql['q']['types'][]= \PDO::PARAM_STR;
            $params['f']['q'] = $filter['q'];
        }

        $filter_abbrev = isset($filter['abbrev']) && $filter['abbrev'];

        if ($filter_abbrev)
        {
            $sql['abbrev'] = $sql_map;
            $sql['abbrev']['where'][]= 'tc.abbrev = ?';
            $sql['abbrev']['params'][]= $filter['abbrev'];
            $sql['abbrev']['types'][]= \PDO::PARAM_STR;
            $params['f']['abbrev'] = $filter['abbrev'];
        }

        $filter_access = isset($filter['admin'])
            || isset($filter['user'])
            || isset($filter['guest']);

        if ($filter_access)
        {
            $sql['access'] = $sql_map;

            if (isset($filter['admin']))
            {
                $sql['access']['where_or'][] = 'c.access = \'admin\'';
                $params['f']['admin'] = '1';
            }

            if (isset($filter['user']))
            {
                $sql['access']['where_or'][] = 'c.access = \'user\'';

                if (!$intersystem_enabled)
                {
                    $sql['access']['where_or'][] = 'c.access = \'guest\'';
                }

                $params['f']['user'] = '1';
            }

            if (isset($filter['guest']) && $intersystem_enabled)
            {
                $sql['access']['where_or'][] = 'c.access = \'guest\'';
                $params['f']['guest'] = '1';
            }

            if (count($sql['access']['where_or']))
            {
                $sql['access']['where'][] = ' (' . implode(' or ', $sql['access']['where_or']) . ') ';
            }
        }

        $filter_ustatus = isset($filter['ustatus']) && $filter['ustatus'] && $filter['ustatus'] !== 'all';

        if ($filter_ustatus)
        {
            $sql['ustatus'] = $sql_map;

            switch ($filter['ustatus'])
            {
                case 'new':
                    $sql['ustatus']['where'][]= 'u.adate > ? and u.status = 1';
                    $sql['ustatus']['params'][]= $config_service->get_new_user_treshold($pp->schema());
                    $sql['ustatus']['types'][]= Types::DATETIME_IMMUTABLE;
                    break;
                case 'leaving':
                    $sql['ustatus']['where'][]= 'u.status = 2';
                    break;
                case 'active':
                    $sql['ustatus']['where'][]= 'u.status in (1, 2)';
                    break;
                case 'inactive':
                    $sql['ustatus']['where'][]= 'u.status = 0';
                    break;
                case 'ip':
                    $sql['ustatus']['where'][]= 'u.status = 5';
                    break;
                case 'im':
                    $sql['ustatus']['where'][]= 'u.status = 6';
                    break;
                case 'extern':
                    $sql['ustatus']['where'][]= 'u.status = 7';
                    break;
                default:
                    $filter['ustatus'] = 'all';
                    break;

                $params['f']['ustatus'] = $filter['ustatus'];
            }
        }
        else
        {
            $params['f']['ustatus'] = 'all';
        }

        $sql['pagination'] = $sql_map;
        $sql['pagination']['params'][] = $params['p']['limit'];
        $sql['pagination']['types'][] = \PDO::PARAM_INT;
        $sql['pagination']['params'][] = $params['p']['start'];
        $sql['pagination']['types'][] = \PDO::PARAM_INT;

        $contacts = [];

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));
        $sql_params = array_merge(...array_column($sql, 'params'));
        $sql_types = array_merge(...array_column($sql, 'types'));

        $query = 'select c.*, tc.abbrev
            from ' . $pp->schema() . '.contact c
            inner join ' . $pp->schema() . '.type_contact tc
                on c.id_type_contact = tc.id
            inner join ' . $pp->schema() . '.users u
                on c.user_id = u.id
            where ' . $sql_where . '
            order by ' . $params['s']['orderby'] . ' ' .
            ($params['s']['asc'] ? 'asc' : 'desc') . '
            limit ? offset ?';

        $stmt = $db->executeQuery($query, $sql_params, $sql_types);

        while ($row = $stmt->fetch())
        {
            $contacts[] = $row;
        }

        $sql_omit_pagination = $sql;
        unset($sql_omit_pagination['pagination']);
        $sql_omit_pagination_where = implode(' and ', array_merge(...array_column($sql_omit_pagination, 'where')));
        $sql_omit_pagination_params = array_merge(...array_column($sql_omit_pagination, 'params'));
        $sql_omit_pagination_types = array_merge(...array_column($sql_omit_pagination, 'types'));

        $row_count = $db->fetchOne('select count(c.*)
            from ' . $pp->schema() . '.contact c
            inner join ' . $pp->schema() . '.type_contact tc
                on c.id_type_contact = tc.id
            inner join ' . $pp->schema() . '.users u
                on c.user_id = u.id
            where ' . $sql_omit_pagination_where,
            $sql_omit_pagination_params,
            $sql_omit_pagination_types);

        $count_ary = [
            'admin'     => 0,
            'user'      => 0,
            'guest'     => 0,
        ];

        $sql_omit_access = $sql_omit_pagination;
        unset($sql_omit_access['access']);
        $sql_omit_access_where = implode(' and ', array_merge(...array_column($sql_omit_access, 'where')));
        $sql_omit_access_params = array_merge(...array_column($sql_omit_access, 'params'));
        $sql_omit_access_types = array_merge(...array_column($sql_omit_access, 'types'));

        $count_access_query = 'select count(c.*), c.access
            from ' . $pp->schema() . '.contact c
            inner join ' . $pp->schema() . '.type_contact tc
                on c.id_type_contact = tc.id
            inner join ' . $pp->schema() . '.users u
                on c.user_id = u.id
            where ' . $sql_omit_access_where . '
            group by c.access';

        $stmt = $db->executeQuery($count_access_query,
            $sql_omit_access_params,
            $sql_omit_access_types);

        while($row = $stmt->fetch())
        {
            $count_ary[$row['access']] = $row['count'];
        }

        if (!$intersystem_enabled)
        {
            $count_ary['user'] += $count_ary['guest'];
        }

        $asc_preset_ary = [
            'asc'		=> 0,
            'fa' 		=> 'sort',
        ];

        $tableheader_ary = [
            'tc.abbrev' 	=> array_merge($asc_preset_ary, [
                'lbl' 		=> 'Type']),
            'c.value'		=> array_merge($asc_preset_ary, [
                'lbl' 		=> 'Waarde']),
            'u.code'	    => array_merge($asc_preset_ary, [
                'lbl' 		=> 'Gebruiker']),
            'c.comments'	=> array_merge($asc_preset_ary, [
                'lbl' 		=> 'Commentaar',
                'data_hide'	=> 'phone,tablet']),
            'c.access' => array_merge($asc_preset_ary, [
                'lbl' 		=> 'Zichtbaar',
                'data_hide'	=> 'phone, tablet']),
            'del' 			=> array_merge($asc_preset_ary, [
                'lbl' 		=> 'Verwijderen',
                'data_hide'	=> 'phone, tablet',
                'no_sort'	=> true]),
        ];

        $tableheader_ary[$params['s']['orderby']]['asc']
            = $params['s']['asc'] ? 0 : 1;
        $tableheader_ary[$params['s']['orderby']]['fa']
            = $params['s']['asc'] ? 'sort-asc' : 'sort-desc';

        unset($tableheader_ary['c.id']);

        $abbrev_ary = [];

        $stmt = $db->prepare('select abbrev
            from ' . $pp->schema() . '.type_contact');

        $stmt->execute();

        while($row = $stmt->fetch())
        {
            $abbrev_ary[$row['abbrev']] = $row['abbrev'];
        }

        $filtered = !isset($filter['uid']) && (
            $filter_q
            || $filter_abbrev
            || $filter_code
            || $filter_ustatus
            || $filter_access
        );

        $panel_collapse = !$filtered;

        $flt = '<div id="filter" class="panel panel-info';
        $flt .= $panel_collapse ? ' collapse' : '';
        $flt .= '">';

        $flt .= '<div class="panel-heading">';

        $flt .= '<form method="get" class="form-horizontal">';

        $flt .= '<div class="row">';

        $flt .= '<div class="col-sm-6">';
        $flt .= '<div class="input-group margin-bottom">';
        $flt .= '<span class="input-group-addon">';
        $flt .= '<i class="fa fa-search"></i>';
        $flt .= '</span>';
        $flt .= '<input type="text" class="form-control" id="q" value="';
        $flt .= $filter['q'] ?? '';
        $flt .= '" name="f[q]" placeholder="Zoeken">';
        $flt .= '</div>';
        $flt .= '</div>';

        $flt .= '<div class="col-sm-6">';
        $flt .= '<div class="input-group margin-bottom">';
        $flt .= '<span class="input-group-addon">';
        $flt .= 'Type';
        $flt .= '</span>';
        $flt .= '<select class="form-control" id="abbrev" name="f[abbrev]">';
        $flt .= $select_render->get_options(array_merge(['' => ''], $abbrev_ary), $filter['abbrev'] ?? '');
        $flt .= '</select>';
        $flt .= '</div>';
        $flt .= '</div>';

        $flt .= '</div>';

        $flt .= '<div class="row">';

        $user_status_options = [
            'all'		=> 'Alle',
            'active'	=> 'Actief',
            'new'		=> 'Enkel instappers',
            'leaving'	=> 'Enkel uitstappers',
            'inactive'	=> 'Inactief',
            'ip'		=> 'Info-pakket',
            'im'		=> 'Info-moment',
            'extern'	=> 'Extern',
        ];

        $flt .= '<div class="col-sm-6">';
        $flt .= '<div class="input-group margin-bottom">';
        $flt .= '<span class="input-group-addon">';
        $flt .= 'Status ';
        $flt .= '<i class="fa fa-user"></i>';
        $flt .= '</span>';
        $flt .= '<select class="form-control" ';
        $flt .= 'id="ustatus" name="f[ustatus]">';

        $flt .= $select_render->get_options($user_status_options, $filter['ustatus'] ?? 'all');

        $flt .= '</select>';
        $flt .= '</div>';
        $flt .= '</div>';

        $flt .= '<div class="col-sm-6">';
        $flt .= '<div class="input-group margin-bottom">';
        $flt .= '<span class="input-group-addon" id="code_addon">Van ';
        $flt .= '<span class="fa fa-user"></span></span>';
        $flt .= '<input type="text" class="form-control" ';
        $flt .= 'aria-describedby="code_addon" ';

        $flt .= 'data-typeahead="';
        $flt .= $typeahead_service->ini($pp->ary())
            ->add('accounts', ['status' => 'active'])
            ->add('accounts', ['status' => 'inactive'])
            ->add('accounts', ['status' => 'ip'])
            ->add('accounts', ['status' => 'im'])
            ->add('accounts', ['status' => 'extern'])
            ->str([
                'filter'        => 'accounts',
                'new_users_days'        => $new_users_days,
                'show_new_status'       => $show_new_status,
                'show_leaving_status'   => $show_leaving_status,
            ]);
        $flt .= '" ';

        $flt .= 'name="f[code]" id="code" placeholder="Account Code" ';
        $flt .= 'value="';
        $flt .= $filter['code'] ?? '';
        $flt .= '">';
        $flt .= '</div>';
        $flt .= '</div>';

        $flt .= '</div>';

        $flt .= '<div class="row">';
        $flt .= '<div class="col-sm-10">';
        $flt .= '<div class="input-group margin-bottom custom-checkbox">';

        foreach (AccessCnst::LABEL as $key => $d)
        {
            if ($key === 'guest' && !$intersystem_enabled)
            {
                continue;
            }

            $label = '<span class="btn btn-';
            $label .= $d['class'];
            $label .= '"';
            $label .= ' title="' . $d['title'] . '"';
            $label .= '>';
            $label .= $d['lbl'];
            $label .= ' (';
            $label .= $count_ary[$key];
            $label .= ')</span>';

            $flt .= strtr(BulkCnst::TPL_CHECKBOX_INLINE, [
                '%name%'        => 'f[' . $key . ']',
                '%attr%'        => isset($filter[$key]) ? ' checked' : '',
                '%label%'       => $label,
            ]);
        }

        $flt .= '</div>';
        $flt .= '</div>';

        $flt .= '<div class="col-sm-2">';
        $flt .= '<input type="submit" value="Toon" ';
        $flt .= 'class="btn btn-default btn-block">';
        $flt .= '</div>';

        $flt .= '</div>';

        $params_form = $params;
        unset($params_form['f']);
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

            $flt .= '<input name="' . $name . '" ';
            $flt .= 'value="' . $value . '" type="hidden">';
        }

        $flt .= '</form>';

        $flt .= '</div>';
        $flt .= '</div>';

        $out = '<div class="panel panel-danger">';
        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-hover ';
        $out .= 'table-striped table-bordered footable csv" ';
        $out .= 'data-sort="false">';

        $out .= '<thead>';
        $out .= '<tr>';

        $th_params = $params;

        foreach ($tableheader_ary as $key_orderby => $data)
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
                    'asc'		=> $data['asc'],
                ];

                $out .= $link_render->link_fa('contacts', $pp->ary(),
                    $th_params, $data['lbl'], [], $data['fa']);
            }

            $out .= '</th>';
        }

        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        foreach ($contacts as $c)
        {
        	$td = [];

            $td[] = strtr(BulkCnst::TPL_CHECKBOX_ITEM, [
                '%id%'      => $c['id'],
                '%attr%'    => isset($selected_contacts[$c['id']]) ? ' checked' : '',
                '%label%'   => $c['abbrev'],
            ]);

            if (isset($c['value']))
            {
                $td[] = $link_render->link_no_attr('contacts_edit', $pp->ary(),
                    ['id' => $c['id']], $c['value']);
            }
            else
            {
                $td[] = '&nbsp;';
            }

            $td[] = $account_render->link($c['user_id'], $pp->ary());

            if (isset($c['comments']))
            {
                $td[] = $link_render->link_no_attr('contacts_edit', $pp->ary(),
                    ['id' => $c['id']], $c['comments']);
            }
            else
            {
                $td[] = '&nbsp;';
            }

            $td[] = $item_access_service->get_label($c['access']);

            $td[] = $link_render->link_fa('contacts_del_admin', $pp->ary(),
                ['id' => $c['id']], 'Verwijderen',
                ['class' => 'btn btn-danger'],
                'times');

            $out .= '<tr><td>';
            $out .= implode('</td><td>', $td);
            $out .= '</td></tr>';
        }

        $out .= '</tbody>';

        $out .= '</table>';

        $out .= '</div></div>';

        $blk = BulkCnst::TPL_SELECT_BUTTONS;

        $blk .= '<h3>Bulk acties met geselecteerde contacten</h3>';
        $blk .= '<div class="panel panel-info">';
        $blk .= '<div class="panel-heading">';

        $blk .= '<ul class="nav nav-tabs" role="tablist">';

        $blk .= '<li class="active"><a href="#access_tab" ';
        $blk .= 'data-toggle="tab">Zichtbaarheid</a></li>';

        $blk .= '</ul>';

        $blk .= '<div class="tab-content">';

        $blk .= '<div role="tabpanel" class="tab-pane active" id="access_tab">';
        $blk .= '<h3>Zichtbaarheid</h3>';
        $blk .= '<form method="post">';

        $blk .= $item_access_service->get_radio_buttons('bulk_field[access]');

        $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'    => 'bulk_verify[access]',
            '%label%'   => 'Ik heb nagekeken dat de juiste contacten geselecteerd zijn.',
            '%attr%'    => ' required',
        ]);

        $blk .= '<input type="submit" value="Aanpassen" ';
        $blk .= 'name="bulk_submit[access]" class="btn btn-primary btn-lg">';
        $blk .= $form_token_service->get_hidden_input();
        $blk .= '</form>';
        $blk .= '</div>';

        $blk .= '<div class="clearfix"></div>';
        $blk .= '</div>';
        $blk .= '</div>';
        $blk .= '</div>';

        return $this->render('contacts/contacts.html.twig', [
            'data_list_raw'     => $out,
            'filter_form_raw'   => $flt,
            'bulk_actions_raw'  => $blk,
            'row_count'         => $row_count,
            'filtered' => $filtered,
        ]);
    }
}
