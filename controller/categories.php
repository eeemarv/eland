<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class categories
{
    public function get(Request $request, app $app):Response
    {
        $cats = $app['db']->fetchAll('select *
            from ' . $app['tschema'] . '.categories
            order by fullname');

        $child_count_ary = [];

        foreach ($cats as $cat)
        {
            if (!isset($child_count_ary[$cat['id_parent']]))
            {
                $child_count_ary[$cat['id_parent']] = 0;
            }

            $child_count_ary[$cat['id_parent']]++;
        }

        $app['btn_top']->add('categories_add',
            $app['pp_ary'], [], 'Categorie toevoegen');

        $app['heading']->add('Categorieën');
        $app['heading']->fa('clone');

        $out = '<div class="panel panel-default printview">';

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-striped table-hover ';
        $out .= 'table-bordered footable" data-sort="false">';
        $out .= '<tr>';
        $out .= '<thead>';
        $out .= '<th>Categorie</th>';
        $out .= '<th data-hide="phone">Vraag</th>';
        $out .= '<th data-hide="phone">Aanbod</th>';
        $out .= '<th data-hide="phone">Verwijderen</th>';
        $out .= '</tr>';
        $out .= '</thead>';

        $out .= '<tbody>';

        $messages_param_ary = [
            'f'	=> [
                's'		=> '1',
                'valid'	=> ['yes' => 'on', 'no' => 'on'],
                'ustatus'	=> ['active' => 'on', 'new' => 'on', 'leaving' => 'on'],
            ],
        ];

        foreach($cats as $cat)
        {
            $count_wanted = $cat['stat_msgs_wanted'];
            $count_offers = $cat['stat_msgs_offers'];
            $count = $count_wanted + $count_offers;

            if (isset($child_count_ary[$cat['id']]))
            {
                $count += $child_count_ary[$cat['id']];
            }

            $td = [];

            if (!$cat['id_parent'])
            {
                $out .= '<tr class="info"><td>';

                $str = '<strong>';
                $str .= $app['link']->link_no_attr('categories_edit', $app['pp_ary'],
                    ['id' => $cat['id']], $cat['name']);
                $td[] = $str . '</strong>';
            }
            else
            {
                $out .= '<tr><td>';
                $str = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                $str .= $app['link']->link_no_attr('categories_edit', $app['pp_ary'],
                    ['id' => $cat['id']], $cat['name']);
                $td[] = $str;
            }

            if ($count_wanted)
            {
                $param_ary = array_merge_recursive($messages_param_ary, [
                    'f'	=> [
                        'cid'	=> $cat['id'],
                        'type'	=> [
                            'want'	=> 'on',
                        ],
                    ],
                ]);

                $td[] = $app['link']->link_no_attr($app['r_messages'], $app['pp_ary'], $param_ary,
                    (string) $count_wanted);
            }
            else
            {
                $td[] = '&nbsp;';
            }

            if ($count_offers)
            {
                $param_ary = array_merge_recursive($messages_param_ary, [
                    'f'	=> [
                        'cid'	=> $cat['id'],
                        'type'	=> [
                            'offer'	=> 'on',
                        ],
                    ],
                ]);

                $td[] = $app['link']->link_no_attr($app['r_messages'], $app['pp_ary'], $param_ary,
                    (string) $count_offers);
            }
            else
            {
                $td[] = '&nbsp;';
            }

            if (!$count)
            {
                $td[] = $app['link']->link_fa('categories_del', $app['pp_ary'],
                    ['id' => $cat['id']], 'Verwijderen',
                    ['class' => 'btn btn-danger'], 'times');
            }
            else
            {
                $td[] = '&nbsp;';
            }

            $out .= implode('</td><td>', $td);
            $out .= '</td></tr>';
        }

        $out .= '</tbody>';
        $out .= '</table>';
        $out .= '</div></div>';

        $out .= '<p><ul><li>Categorieën met berichten ';
        $out .= 'of hoofdcategorieën met subcategorieën kan je niet verwijderen.</li>';
        $out .= '<li>Enkel subcategorieën kunnen berichten bevatten.</li></ul>';
        $out .= '</p>';

        $app['tpl']->add($out);

        return $app['tpl']->get($request);
    }
}
