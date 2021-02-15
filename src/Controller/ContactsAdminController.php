<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\AccountRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Service\MenuService;
use App\Render\HeadingRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Render\PaginationRender;
use App\Render\SelectRender;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Cnst\RoleCnst;
use Doctrine\DBAL\Types\Types;

class ContactsAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        HeadingRender $heading_render,
        PaginationRender $pagination_render,
        SelectRender $select_render,
        LinkRender $link_render,
        MenuService $menu_service,
        TypeaheadService $typeahead_service,
        ConfigService $config_service,
        AccountRender $account_render,
        SessionUserService $su,
        PageParamsService $pp,
        ItemAccessService $item_access_service
    ):Response
    {
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

        if (isset($filter['code']) && $filter['code'])
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

        if (isset($filter['q']) && $filter['q'])
        {
            $sql['q'] = $sql_map;
            $sql['q']['where'][]= '(c.value ilike ? or c.comments ilike ?)';
            $sql['q']['params'][]= '%' . $filter['q'] . '%';
            $sql['q']['params'][]= '%' . $filter['q'] . '%';
            $sql['q']['types'][]= \PDO::PARAM_STR;
            $sql['q']['types'][]= \PDO::PARAM_STR;
            $params['f']['q'] = $filter['q'];
        }

        if (isset($filter['abbrev']) && $filter['abbrev'])
        {
            $sql['abbrev'] = $sql_map;
            $sql['abbrev']['where'][]= 'tc.abbrev = ?';
            $sql['abbrev']['params'][]= $filter['abbrev'];
            $sql['abbrev']['types'][]= \PDO::PARAM_STR;
            $params['f']['abbrev'] = $filter['abbrev'];
        }

        if (isset($filter['access']) && $filter['access'] !== 'all')
        {
            $sql['access'] = $sql_map;
            $sql['access']['where'][]= 'c.access = ?';
            $sql['access']['params'][]= $filter['access'];
            $sql['access']['types'][]= \PDO::PARAM_STR;
            $params['f']['access'] = $filter['access'];
        }

        if (isset($filter['ustatus']) && $filter['ustatus'])
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

        $pagination_render->init('contacts', $pp->ary(),
            $row_count, $params);

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

        $btn_nav_render->csv();

        $btn_top_render->add('contacts_add_admin', $pp->ary(),
            [], 'Contact toevoegen');

        $filtered = !isset($filter['uid']) && (
            (isset($filter['q']) && $filter['q'] !== '')
            || (isset($filter['abbrev']) && $filter['abbrev'] !== '')
            || (isset($filter['code']) && $filter['code'] !== '')
            || (isset($filter['ustatus'])
                && !in_array($filter['ustatus'], ['all', '']))
            || (isset($filter['access'])
                && !in_array($filter['access'], ['all', ''])));

        $panel_collapse = !$filtered;

        $heading_render->add('Contacten');
        $heading_render->add_filtered($filtered);
        $heading_render->btn_filter();
        $heading_render->fa('map-marker');

        $out = '<div id="filter" class="panel panel-info';
        $out .= $panel_collapse ? ' collapse' : '';
        $out .= '">';

        $out .= '<div class="panel-heading">';

        $out .= '<form method="get" class="form-horizontal">';

        $out .= '<div class="row">';

        $out .= '<div class="col-sm-4">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-search"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="q" value="';
        $out .= $filter['q'] ?? '';
        $out .= '" name="f[q]" placeholder="Zoeken">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-4">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon">';
        $out .= 'Type';
        $out .= '</span>';
        $out .= '<select class="form-control" id="abbrev" name="f[abbrev]">';
        $out .= $select_render->get_options(array_merge(['' => ''], $abbrev_ary), $filter['abbrev'] ?? '');
        $out .= '</select>';
        $out .= '</div>';
        $out .= '</div>';

        $access_options = [
            'all'		=> '',
        ];

        $access_options += RoleCnst::LABEL_ARY;

        if (!$config_service->get_intersystem_en($pp->schema()))
        {
            unset($access_options['guest']);
        }

        $out .= '<div class="col-sm-4">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon">';
        $out .= 'Zichtbaar';
        $out .= '</span>';
        $out .= '<select class="form-control" id="access" name="f[access]">';
        $out .= $select_render->get_options($access_options, $filter['access'] ?? 'all');
        $out .= '</select>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        $out .= '<div class="row">';

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

        $out .= '<div class="col-sm-5">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon">';
        $out .= 'Status ';
        $out .= '<i class="fa fa-user"></i>';
        $out .= '</span>';
        $out .= '<select class="form-control" ';
        $out .= 'id="ustatus" name="f[ustatus]">';

        $out .= $select_render->get_options($user_status_options, $filter['ustatus'] ?? 'all');

        $out .= '</select>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-5">';
        $out .= '<div class="input-group margin-bottom">';
        $out .= '<span class="input-group-addon" id="code_addon">Van ';
        $out .= '<span class="fa fa-user"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'aria-describedby="code_addon" ';

        $out .= 'data-typeahead="';
        $out .= $typeahead_service->ini($pp->ary())
            ->add('accounts', ['status' => 'active'])
            ->add('accounts', ['status' => 'inactive'])
            ->add('accounts', ['status' => 'ip'])
            ->add('accounts', ['status' => 'im'])
            ->add('accounts', ['status' => 'extern'])
            ->str([
                'filter'        => 'accounts',
                'newuserdays'   => $config_service->get('newuserdays', $pp->schema()),
            ]);
        $out .= '" ';

        $out .= 'name="f[code]" id="code" placeholder="Account Code" ';
        $out .= 'value="';
        $out .= $filter['code'] ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-sm-2">';
        $out .= '<input type="submit" value="Toon" ';
        $out .= 'class="btn btn-default btn-block">';
        $out .= '</div>';

        $out .= '</div>';

        $params_form = $params;
        unset($params_form['f']);
        unset($params_form['uid']);
        unset($params_form['p']['start']);

        $params_form['r'] = 'admin';
        $params_form['u'] = $su->id();

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

        $out .= $pagination_render->get();

        if (!count($contacts))
        {
            $out .= '<br>';
            $out .= '<div class="panel panel-default">';
            $out .= '<div class="panel-body">';
            $out .= '<p>Er zijn geen resultaten.</p>';
            $out .= '</div></div>';

            $out .= $pagination_render->get();

            $menu_service->set('contacts');

            return $this->render('base/navbar.html.twig', [
                'content'   => $out,
                'schema'    => $pp->schema(),
            ]);
        }

        $out .= '<div class="panel panel-danger">';
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

            $td[] = $c['abbrev'];

            if (isset($c['value']))
            {
                $td[] = $link_render->link_no_attr('contacts_edit_admin', $pp->ary(),
                    ['id' => $c['id']], $c['value']);
            }
            else
            {
                $td[] = '&nbsp;';
            }

            $td[] = $account_render->link($c['user_id'], $pp->ary());

            if (isset($c['comments']))
            {
                $td[] = $link_render->link_no_attr('contacts_edit_admin', $pp->ary(),
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

        $out .= $pagination_render->get();

        $menu_service->set('contacts');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
