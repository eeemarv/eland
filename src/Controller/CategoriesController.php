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

        $want_count_ary = [];
        $offer_count_ary = [];

        $stmt = $db->prepare('select c.id, count(m.*)
            from ' . $pp->schema() . '.categories c,
                ' . $pp->schema() . '.messages m
            where m.category_id = c.id
                and m.is_want = \'t\'
            group by c.id');

        $stmt->execute();

        while($row = $stmt->fetch())
        {
            $want_count_ary[$row['id']] = $row['count'];
        }

        $stmt = $db->prepare('select c.id, count(m.*)
            from ' . $pp->schema() . '.categories c,
                ' . $pp->schema() . '.messages m
            where m.category_id = c.id
                and m.is_offer = \'t\'
            group by c.id');

        $stmt->execute();

        while($row = $stmt->fetch())
        {
            $offer_count_ary[$row['id']] = $row['count'];
        }

        $child_count_ary = [];

        foreach ($cats as $cat)
        {
            $child_count_ary[$cat['id_parent']] ??= 0;
            $child_count_ary[$cat['id_parent']]++;
        }

        $btn_top_render->add('categories_add',
            $pp->ary(), [], 'Categorie toevoegen');

        $heading_render->add('Categorieën');
        $heading_render->fa('clone');

        $out .= '<div class="table-responsive border border-dark rounded mb-3">';
        $out .= '<table class="table table-striped table-hover ';
        $out .= 'table-bordered footable bg-default mb-0" ';
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
            $id = $cat['id'];

            $want_count = $want_count_ary[$id] ?? 0;
            $offer_count = $offer_count_ary[$id] ?? 0;

            $dependency_count = $want_count + $offer_count;

            if (isset($child_count_ary[$id]))
            {
                $dependency_count += $child_count_ary[$id];
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

            if ($want_count)
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
                    (string) $want_count);
            }
            else
            {
                $td[] = '&nbsp;';
            }

            if ($offer_count)
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
                    (string) $offer_count);
            }
            else
            {
                $td[] = '&nbsp;';
            }

            if (!$dependency_count)
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
