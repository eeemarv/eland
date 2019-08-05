<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use controller\users_list;
use render\btn_nav;
use render\heading;
use cnst\status as cnst_status;
use cnst\role as cnst_role;

class users_tiles
{
    public function admin(Request $request, app $app, string $status):Response
    {
        return $this->get($request, $app, $status);
    }

    public function get(Request $request, app $app, string $status):Response
    {
        $q = $request->get('q', '');
        $users_route = $app['s_admin'] ? 'users_tiles_admin' : 'users_tiles';

        $st = users_list::get_st($app['s_admin'], $app['new_user_treshold']);

        $params = ['status'	=> $status];

        $sql_bind = [];

        if (isset($st[$status]['sql_bind']))
        {
            $sql_bind[] = $st[$status]['sql_bind'];
        }

        $users = $app['db']->fetchAll('select u.*
            from ' . $app['tschema'] . '.users u
            where ' . $st[$status]['sql'] . '
            order by u.letscode asc', $sql_bind);

        $app['assets']->add(['isotope', 'users_tiles.js']);

        if ($app['s_admin'])
        {
            $app['btn_top']->add('users_add', $app['pp_ary'],
                [], 'Gebruiker toevoegen');
        }

        users_list::btn_nav($app['btn_nav'], $app['pp_ary'], $params, 'users_tiles');
        users_list::heading($app['heading']);

        $out = users_list::get_filter_and_tab_selector(
            $users_route, $app['pp_ary'], $params, $app['link'],
            $app['s_admin'], '', $q, $app['new_user_treshold']
        );

        $out .= '<p>';
        $out .= '<span class="btn-group sort-by" role="group">';
        $out .= '<button class="btn btn-default active" data-sort-by="letscode">';
        $out .= 'Account Code ';
        $out .= '<i class="fa fa-sort-asc"></i></button>';
        $out .= '<button class="btn btn-default" data-sort-by="name">';
        $out .= 'Naam ';
        $out .= '<i class="fa fa-sort"></i></button>';
        $out .= '<button class="btn btn-default" data-sort-by="postcode">';
        $out .= 'Postcode ';
        $out .= '<i class="fa fa-sort"></i></button>';
        $out .= '</span>';
        $out .= '</p>';

        $out .= '<div class="row tiles">';

        foreach ($users as $u)
        {
            $row_stat = ($u['status'] == 1 && $app['new_user_treshold'] < strtotime($u['adate'])) ? 3 : $u['status'];

            $url = $app['link']->context_path($app['r_users_show'], $app['pp_ary'],
                ['id' => $u['id'], 'link' => $status]);

            $out .= '<div class="col-xs-4 col-md-3 col-lg-2 tile">';
            $out .= '<div';

            if (isset(cnst_status::CLASS_ARY[$row_stat]))
            {
                $out .= ' class="bg-';
                $out .= cnst_status::CLASS_ARY[$row_stat];
                $out .= '"';
            }

            $out .= '>';
            $out .= '<div class="thumbnail text-center">';
            $out .= '<a href="' . $url . '">';

            if (isset($u['PictureFile']) && $u['PictureFile'] != '')
            {
                $out .= '<img src="';
                $out .= $app['s3_url'] . $u['PictureFile'];
                $out .= '" class="img-rounded">';
            }
            else
            {
                $out .= '<div><i class="fa fa-user fa-5x text-muted"></i></div>';
            }
            $out .= '</a>';

            $out .= '<div class="caption">';

            $out .= '<a href="' . $url . '">';
            $out .= '<span class="letscode">' . $u['letscode'] . '</span> ';
            $out .= '<span class="name">' . $u['name'] . '</span>';
            $out .= '</a>';
            $out .= '<br><span class="postcode">' . $u['postcode'] . '</span>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</div>';
        }

        $out .= '</div>';

        $app['tpl']->add($out);
        $app['tpl']->menu('users');

        return $app['tpl']->get($request);
    }
}
