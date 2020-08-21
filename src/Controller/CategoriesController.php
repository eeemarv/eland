<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Service\MenuService;
use App\Render\HeadingRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoriesController extends AbstractController
{
    public function __invoke(
        Db $db,
        ConfigService $config_service,
        AssetsService $assets_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        BtnTopRender $btn_top_render,
        PageParamsService $pp,
        VarRouteService $vr,
        HeadingRender $heading_render
    ):Response
    {
        if (!$config_service->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        $categories = $db->fetchAll('select c.*, count(m.*)
            from ' . $pp->schema() . '.categories c
            left join ' . $pp->schema() . '.messages m
            on m.category_id = c.id
            group by c.id
            order by c.left_id asc');

        $assets_service->add(['sortable', 'categories.js']);

        $btn_top_render->add('categories_add',
            $pp->ary(), [], 'Categorie toevoegen');

        $heading_render->add('Categorieën');
        $heading_render->fa('clone');

        $out = '<form  method="post">';
        $out .= '<div class="list-group" data-sortable data-sort-base>';

        $open_div = 0;

        foreach($categories as $cat)
        {
            $id = $cat['id'];
            $level = $cat['level'];
            $name = $cat['name'];
            $left_id = $cat['left_id'];
            $right_id = $cat['right_id'];
            $count = $cat['count'];

            if ($level === 1)
            {
                while($open_div > 0)
                {
                    $out .= '</div>';
                    $open_div--;
                }
            }

            $out .= '<div class="list-group-item';
            $out .= $level === 1 ? ' list-group-item-info' : '';
            $out .= '"';
            $out .= ' data-id="' . $id . '"';
            $out .= $count === 0 ? '' : ' data-has-messages';
            $out .= ($left_id + 1) === $right_id ? '' : ' data-has-categories';
            $out .= '>';
            $out .= '<strong>';
            $out .= htmlspecialchars($name, ENT_QUOTES);

            if ($count <> 0)
            {
                $out .= ' (';
                $out .= $link_render->link_no_attr($vr->get('messages'),
                    $pp->ary(), ['f' => ['cid' => $id]],
                    (string) $count);
                $out .= ')';
            }

            $out .= '</strong>';

            $out .= '<div class="pull-right">';
            $out .= $link_render->link_fa('categories_edit', $pp->ary(),
                ['id' => $id], 'Aanpassen', ['class' => 'btn btn-primary'], 'pencil');

            if (($left_id + 1) === $right_id && $count === 0)
            {
                $out .= '&nbsp;';
                $out .= $link_render->link_fa('categories_del', $pp->ary(),
                    ['id' => $id], 'Verwijderen',
                    ['class' => 'btn btn-danger'], 'times');
            }

            $out .= '</div>';
            $out .= '<div class="clearfix"></div>';

            if ($count === 0)
            {
                $out .= '<div class="list-group" data-sortable>';
                $open_div += 2;
                continue;
            }

            $out .= '</div>';
        }

        while ($open_div > 0)
        {
            $out .= '</div>';
            $open_div--;
        }

        $out .= '</div>';

        $out .= '<br/>';
        $out .= '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= $link_render->btn_cancel('categories', $pp->ary(), []);
        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Opslaan" ';
        $out .= 'class="btn btn-primary btn-lg">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

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
