<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Service\MenuService;
use App\Render\HeadingRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\PageParamsService;
use App\Service\VarRouteService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoriesController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        ConfigService $config_service,
        AssetsService $assets_service,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        BtnTopRender $btn_top_render,
        PageParamsService $pp,
        VarRouteService $vr,
        HeadingRender $heading_render
    ):Response
    {
        $errors = [];

        if (!$config_service->get_bool('messages.fields.category.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Categories module not enabled.');
        }

        $categories = [];
        $update_ary = [];
        $stored_categories = [];
        $base_cat_index = -1;

        $stmt = $db->prepare('select c.*, count(m.*)
            from ' . $pp->schema() . '.categories c
            left join ' . $pp->schema() . '.messages m
            on m.category_id = c.id
            group by c.id
            order by c.left_id asc');

        $stmt->execute();

        while ($row = $stmt->fetch())
        {
            $id = $row['id'];
            $level = $row['level'];

            $categories[$id] = $row;

            if ($level === 1)
            {
                $base_cat_index++;
                $stored_categories[$base_cat_index] = ['id' => $id];
                continue;
            }

            if (!isset($stored_categories[$base_cat_index]['children']))
            {
                $stored_categories[$base_cat_index]['children'] = [];
            }

            $stored_categories[$base_cat_index]['children'][] = ['id' => $id];
        }

        if ($request->isMethod('POST'))
        {
            $posted_json = $request->request->get('categories', '[]');
            $posted_categories = json_decode($posted_json, true);

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            $left_id = 0;

            foreach ($posted_categories as $base_item)
            {
                if (!isset($base_item['id']))
                {
                    throw new BadRequestHttpException('Malformed request for categories input (missing id): ' . $posted_json);
                }

                $left_id++;
                $base_id = $base_item['id'];
                $children_count = count($base_item['children'] ?? []);
                $right_id = $left_id + ($children_count * 2) + 1;

                if ($children_count > 0 && $categories[$base_id]['count'] > 0)
                {
                    throw new BadRequestHttpException('A category with messages cannot contain sub-categories. id: ' . $base_id);
                }

                $update_ary[$base_id] = [
                    'left_id'   => $left_id,
                    'right_id'  => $right_id,
                    'level'     => 1,
                    'parent_id' => null,
                ];

                $left_id++;

                if (isset($base_item['children']) && count($base_item['children']))
                {
                    foreach($base_item['children'] as $sub_item)
                    {
                        if (!isset($sub_item['id']))
                        {
                            throw new BadRequestHttpException('Malformed request for categories input (missing id): ' . $posted_json);
                        }

                        if (isset($sub_item['children']))
                        {
                            throw new BadRequestHttpException('A subcategory can not have subcategories itself. id: ' . $sub_item['id']);
                        }

                        $right_id = $left_id + 1;

                        $update_ary[$sub_item['id']] = [
                            'left_id'   => $left_id,
                            'right_id'  => $right_id,
                            'level'     => 2,
                            'parent_id' => $base_id,
                        ];

                        $left_id = $right_id + 1;
                    }
                }
            }

            $count_update_ary = count($update_ary);
            $count_categories = count($categories);

            if ($count_update_ary !== $count_categories)
            {
                throw new BadRequestHttpException('Mismatch number of stored and posted
                    categories, stored: ' . $count_categories . ', update: ' . $count_update_ary);
            }

            if (!count($errors))
            {
                $count_updated = 0;

                foreach ($update_ary as $id => $update)
                {
                    $stored_cat = $categories[$id];

                    if ($stored_cat['level'] === $update['level']
                        && $stored_cat['parent_id'] === $update['parent_id']
                        && $stored_cat['left_id'] === $update['left_id']
                        && $stored_cat['right_id'] === $update['right_id'])
                    {
                        continue;
                    }

                    $db->update($pp->schema() . '.categories', $update, ['id' => $id]);
                    $count_updated++;
                }

                if ($count_updated > 0)
                {
                    $alert_service->success('Plaatsing categorieën aangepast.');
                }
                else
                {
                    $alert_service->warning('Geen gewijzigde plaatsing van categorieën.');
                }

                error_log('count_updated: ' . $count_updated);

                $link_render->redirect('categories', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $assets_service->add(['sortable', 'categories.js']);

        $btn_top_render->add('categories_add',
            $pp->ary(), [], 'Categorie toevoegen');

        $heading_render->add('Categorieën');
        $heading_render->fa('clone');

        $out = '<p><ul>';
        $out .= '<li>Versleep categorieën om plaats en volgorde te veranderen..</li>';
        $out .= '<li>Wijzigingen worden enkel opgeslagen na het klikken van de "Opslaan" knop onderaan.</li>';
        $out .= '<li>Hoofdcategorieën (blauw) kunnen subcategorieën (wit) bevatten ofwel vraag en aanbod berichten, niet beide.';
        $out .= '</li>';
        $out .= '<li>Subcategoriën (wit) kunnen enkel vraag en aanbod berichten bevatten, geen categorieën.</li>';
        $out .= '<li>Enkel lege categorieën (zonder subcategorieën of vraag en aanbod) kunnen verwijderd worden.</li>';
        $out .= '</ul>';
        $out .= '</p>';

        $out .= '<form  method="post">';
        $out .= '<div class="list-group" data-sortable data-sort-base>';

        $open_div = 0;

        foreach($categories as $id => $cat)
        {
            $level = $cat['level'];
            $name = $cat['name'];
            $left_id = $cat['left_id'];
            $right_id = $cat['right_id'];
            $count = $cat['count'];

            while($open_div > (($level - 1) * 2))
            {
                $out .= '</div>';
                $open_div--;
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

            if ($count > 0)
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
                    ['class' => 'btn btn-danger', 'data-del-btn' => ''], 'times');
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
        $out .= '<input type="hidden" value="';
        $out .= htmlspecialchars(json_encode($stored_categories));
        $out .= '" name="categories" data-categories-input>';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $menu_service->set('categories');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
