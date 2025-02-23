<?php declare(strict_types=1);

namespace App\Controller\Contacts;

use App\Cache\ConfigCache;
use App\Cnst\BulkCnst;
use App\Command\Contacts\ContactsFilterCommand;
use App\Form\Type\Contacts\ContactsFilterType;
use App\Render\AccountRender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Render\LinkRender;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\AlertService;
use App\Service\FormTokenService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
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
        LinkRender $link_render,
        FormTokenService $form_token_service,
        ConfigCache $config_cache,
        AccountRender $account_render,
        PageParamsService $pp,
        ItemAccessService $item_access_service
    ):Response
    {
        $new_users_enabled = $config_cache->get_bool('users.new.enabled', $pp->schema());
        $leaving_users_enabled = $config_cache->get_bool('users.leaving.enabled', $pp->schema());
        $new_user_treshold = $config_cache->get_new_user_treshold($pp->schema());

        $filter_command = new ContactsFilterCommand();

        $uid = $request->query->get('uid');

        if (isset($uid))
        {
            $filter_command->user = (int) $uid;
        }

        $filter_form_options = ['validation_groups' => ['filter']];

        $filter_form = $this->createForm(ContactsFilterType::class, $filter_command, $filter_form_options);
        $filter_form->handleRequest($request);
        $filter_command = $filter_form->getData();

        $f_params = $request->query->all('f');
        $filter_form_error = isset($f_params['user']) && !isset($filter_command->user);

        $intersystem_enabled = $config_cache->get_bool('intersystem.enabled', $pp->schema());

        $selected_contacts = $request->request->all('sel');
        $bulk_field = $request->request->all('bulk_field');
        $bulk_verify = $request->request->all('bulk_verify');
        $bulk_submit = $request->request->all('bulk_submit');

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
                    [\PDO::PARAM_STR, ArrayParameterType::INTEGER]);

                if (count($selected_contacts) > 1)
                {
                    $alert_service->success('De contacten zijn aangepast.');
                }
                else
                {
                    $alert_service->success('Het contact is aangepast.');
                }

                return $this->redirectToRoute('contacts', $pp->ary());
            }

            $alert_service->error($errors);
        }

        $pag = $request->query->all('p');
        $sort = $request->query->all('s');

        $pag_start = $pag['start'] ?? 0;
        $pag_limit = $pag['limit'] ?? 25;
        $sort_orderby = $sort['orderby'] ?? 'c.id';
        $sort_asc = isset($sort['asc']) && $sort['asc'] ? true : false;

        $all_params = $request->query->all();

        $sql_map = [
            'where'     => [],
            'params'    => [],
            'types'     => [],
        ];

        $sql = [];
        $sql['common'] = $sql_map;
        $sql['common']['where'][] = '1 = 1';

        if (isset($filter_command->user))
        {
            $sql['user']['where'][]= 'c.user_id = ?';
            $sql['user']['params'][]= $filter_command->user;
            $sql['user']['types'][]= \PDO::PARAM_INT;
        }

        if (isset($filter_command->q))
        {
            $sql['q'] = $sql_map;
            $sql['q']['where'][]= '(c.value ilike ? or c.comments ilike ?)';
            $sql['q']['params'][]= '%' . $filter_command->q . '%';
            $sql['q']['params'][]= '%' . $filter_command->q . '%';
            $sql['q']['types'][]= \PDO::PARAM_STR;
            $sql['q']['types'][]= \PDO::PARAM_STR;
        }

        if (isset($filter_command->type))
        {
            $sql['type'] = $sql_map;
            $sql['type']['where'][]= 'c.id_type_contact = ?';
            $sql['type']['params'][]= $filter_command->type;
            $sql['type']['types'][]= \PDO::PARAM_INT;
        }

        if (isset($filter_command->access))
        {
            $sql['access'] = $sql_map;
            $wh_or = [];

            if (in_array('admin', $filter_command->access))
            {
                $wh_or[] = 'c.access = \'admin\'';
            }

            if (in_array('user', $filter_command->access))
            {
                $wh_or[] = 'c.access = \'user\'';

                if (!$intersystem_enabled)
                {
                    $wh_or[] = 'c.access = \'guest\'';
                }
            }

            if (in_array('guest', $filter_command->access) && $intersystem_enabled)
            {
                $wh_or[] = 'c.access = \'guest\'';
            }

            if (count($wh_or))
            {
                $sql['access']['where'][] = ' (' . implode(' or ', $wh_or) . ') ';
            }
        }

        if (isset($filter_command->ustatus))
        {
            $sql['ustatus'] = $sql_map;

            switch ($filter_command->ustatus)
            {
                case 'active':
                    $sql['ustatus']['where'][]= 'u.is_active';
                    break;
                case 'new':
                    $sql['ustatus']['where'][]= 'u.activated_at > ?';
                    $sql['ustatus']['where'][]= 'u.is_active';
                    $sql['ustatus']['where'][]= 'not u.is_leaving';
                    $sql['ustatus']['params'][]= $config_cache->get_new_user_treshold($pp->schema());
                    $sql['ustatus']['types'][]= Types::DATETIME_IMMUTABLE;
                    break;
                case 'leaving':
                    $sql['ustatus']['where'][]= 'u.is_leaving';
                    $sql['ustatus']['where'][]= 'u.is_active';
                    break;
                case 'pre-active':
                    $sql['ustatus']['where'][]= 'not u.is_active';
                    $sql['ustatus']['where'][]= 'u.activated_at is null';
                    break;
                case 'post-active':
                    $sql['ustatus']['where'][]= 'not u.is_active';
                    $sql['ustatus']['where'][]= 'u.activated_at is not null';
                    break;
                default:
                    break;
            }
        }

        $sql['pagination'] = $sql_map;
        $sql['pagination']['params'][] = $pag_limit;
        $sql['pagination']['types'][] = \PDO::PARAM_INT;
        $sql['pagination']['params'][] = $pag_start;
        $sql['pagination']['types'][] = \PDO::PARAM_INT;

        $contacts = [];

        $sql_where = implode(' and ', array_merge(...array_column($sql, 'where')));
        $sql_params = array_merge(...array_column($sql, 'params'));
        $sql_types = array_merge(...array_column($sql, 'types'));

        $query = 'select c.*, tc.abbrev,
            u.is_active, u.is_leaving,
            u.activated_at
            from ' . $pp->schema() . '.contact c
            inner join ' . $pp->schema() . '.type_contact tc
                on c.id_type_contact = tc.id
            inner join ' . $pp->schema() . '.users u
                on c.user_id = u.id
            where ' . $sql_where . '
            order by ' . $sort_orderby . ' ' .
            ($sort_asc ? 'asc' : 'desc') . '
            limit ? offset ?';

        $res = $db->executeQuery($query, $sql_params, $sql_types);

        while ($row = $res->fetchAssociative())
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
            inner join ' . $pp->schema() . '.users u
                on c.user_id = u.id
            where ' . $sql_omit_access_where . '
            group by c.access';

        $res = $db->executeQuery($count_access_query,
            $sql_omit_access_params,
            $sql_omit_access_types);

        while($row = $res->fetchAssociative())
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
            'tc.abbrev' 	=> [
                ...$asc_preset_ary,
                'lbl'   => 'Type',
            ],
            'c.value'		=> [
                ...$asc_preset_ary,
                'lbl' 	=> 'Waarde',
            ],
            'u.code'	    => [
                ...$asc_preset_ary,
                'lbl' 	=> 'Gebruiker',
            ],
            'c.comments'	=> [
                ...$asc_preset_ary,
                'lbl' 		=> 'Commentaar',
                'data_hide'	=> 'phone,tablet',
            ],
            'c.access' => [
                ...$asc_preset_ary,
                'lbl' 		=> 'Zichtbaar',
                'data_hide'	=> 'phone, tablet',
            ],
            'del' 			=> [
                ...$asc_preset_ary,
                'lbl' 		=> 'Verwijderen',
                'data_hide'	=> 'phone, tablet',
                'no_sort'	=> true,
            ],
        ];

        $tableheader_ary[$sort_orderby]['asc']
            = $sort_asc ? 0 : 1;
        $tableheader_ary[$sort_orderby]['fa']
            = $sort_asc ? 'sort-asc' : 'sort-desc';

        unset($tableheader_ary['c.id']);

        $abbrev_ary = [];

        $stmt = $db->prepare('select abbrev
            from ' . $pp->schema() . '.type_contact');

        $res = $stmt->executeQuery();

        while($row = $res->fetchAssociative())
        {
            $abbrev_ary[$row['abbrev']] = $row['abbrev'];
        }

        $filtered = !isset($uid) && (
            isset($filter_command->q)
            || isset($filter_command->type)
            || isset($filter_command->user)
            || isset($filter_command->ustatus)
            || (isset($filter_command->access) && $filter_command->access)
        );

        $filter_collapse = !($filtered || $filter_form_error);

        $out = '<div class="panel panel-danger">';
        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-hover ';
        $out .= 'table-striped table-bordered footable csv" ';
        $out .= 'data-sort="false">';

        $out .= '<thead>';
        $out .= '<tr>';

        $th_params = $all_params;
        unset($th_params['p']['start']);

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
            $is_active = $c['is_active'];
            $is_leaving = $c['is_leaving'];
            $post_active = isset($c['activated_at']);
            $is_new = false;
            if ($post_active)
            {
                if ($new_user_treshold->getTimestamp() < strtotime($c['activated_at'] . ' UTC'))
                {
                    $is_new = true;
                }
            }

            $u_class = null;

            if ($is_active)
            {
                if ($is_leaving && $leaving_users_enabled)
                {
                    $u_class = 'danger';
                }
                else if ($is_new && $new_users_enabled)
                {
                    $u_class = 'success';
                }
            }
            else if ($post_active)
            {
                $u_class = 'inactive';
            }
            else
            {
                $u_class = 'info';
            }

            $out .= '<tr>';
            $out .= '<td>';

            $out .= strtr(BulkCnst::TPL_CHECKBOX_ITEM, [
                '%id%'      => $c['id'],
                '%attr%'    => isset($selected_contacts[$c['id']]) ? ' checked' : '',
                '%label%'   => $c['abbrev'],
            ]);

            $out .= '</td>';
            $out .= '<td>';

            if (isset($c['value']))
            {
                $out .= $link_render->link_no_attr('contacts_edit', $pp->ary(),
                    ['id' => $c['id']], $c['value']);
            }
            else
            {
                $out .= '&nbsp;';
            }

            $out .= '</td>';
            $out .= '<td';

            if (isset($u_class))
            {
                $out .= ' class="';
                $out .= $u_class;
                $out .= '"';
            }

            $out .= '>';

            $out .= $account_render->link($c['user_id'], $pp->ary());

            $out .= '</td>';
            $out .= '<td>';

            if (isset($c['comments']))
            {
                $out .= $link_render->link_no_attr('contacts_edit', $pp->ary(),
                    ['id' => $c['id']], $c['comments']);
            }
            else
            {
                $out .= '&nbsp;';
            }

            $out .= '</td>';
            $out .= '<td>';

            $out .= $item_access_service->get_label($c['access']);

            $out .= '</td>';
            $out .= '<td>';

            $out .= $link_render->link_fa('contacts_del_admin', $pp->ary(),
                ['id' => $c['id']], 'Verwijderen',
                ['class' => 'btn btn-danger'],
                'times');

            $out .= '</td>';
            $out .= '</tr>';
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
            'bulk_actions_raw'  => $blk,
            'row_count'         => $row_count,
            'filtered'          => $filtered,
            'filter_collapse'   => $filter_collapse,
            'filter_form'       => $filter_form->createView(),
            'count_ary'         => $count_ary,
        ]);
    }
}
