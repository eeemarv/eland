<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Service\MenuService;
use App\Render\HeadingRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Service\PageParamsService;
use App\Service\VarRouteService;

class CategoriesController extends AbstractController
{
    public function __invoke(
        Db $db,
        MenuService $menu_service,
        LinkRender $link_render,
        BtnTopRender $btn_top_render,
        PageParamsService $pp,
        VarRouteService $vr,
        HeadingRender $heading_render
    ):Response
    {
        $cats = $db->fetchAll('select *
            from ' . $pp->schema() . '.categories
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

        $btn_top_render->add('categories_add',
            $pp->ary(), [], 'Categorie toevoegen');

        $heading_render->add('Categorieën');
        $heading_render->fa('clone');

        $out .= '<div class="table-responsive">';
        $out .= '<table class="table table-striped table-hover ';
        $out .= 'table-bordered footable bg-default" ';
        $out .= 'data-sort="false">';
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
                $str .= $link_render->link_no_attr('categories_edit', $pp->ary(),
                    ['id' => $cat['id']], $cat['name']);
                $td[] = $str . '</strong>';
            }
            else
            {
                $out .= '<tr><td>';
                $str = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                $str .= $link_render->link_no_attr('categories_edit', $pp->ary(),
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

                $td[] = $link_render->link_no_attr($vr->get('messages'), $pp->ary(), $param_ary,
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

                $td[] = $link_render->link_no_attr($vr->get('messages'), $pp->ary(), $param_ary,
                    (string) $count_offers);
            }
            else
            {
                $td[] = '&nbsp;';
            }

            if (!$count)
            {
                $td[] = $link_render->link_fa('categories_del', $pp->ary(),
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
        $out .= '</div>';

        $out .= '<p><ul><li>Categorieën met berichten ';
        $out .= 'of hoofdcategorieën met subcategorieën kan je niet verwijderen.</li>';
        $out .= '<li>Enkel subcategorieën kunnen berichten bevatten.</li></ul>';
        $out .= '</p>';

        $menu_service->set('categories');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
