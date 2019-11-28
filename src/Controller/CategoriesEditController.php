<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\PageParamsService;

class CategoriesEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        HeadingRender $heading_render,
        PageParamsService $pp,
        SelectRender $select_render
    ):Response
    {
        $cats = [];

        $rs = $db->prepare('select *
            from ' . $pp->schema() . '.categories
            order by fullname');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $cats[$row['id']] = $row;
        }

        $child_count_ary = [];

        foreach ($cats as $cat)
        {
            if (!isset($child_count_ary[$cat['id_parent']]))
            {
                $child_count_ary[$cat['id_parent']] = 0;
            }

            $child_count_ary[$cat['id_parent']]++;
        }

        $cat = $cats[$id];

        if ($request->isMethod('POST'))
        {
            $cat['name'] = $request->request->get('name', '');
            $cat['id_parent'] = (int) $request->request->get('id_parent', 0);
            $cat['leafnote'] = $cat['id_parent'] === 0 ? 0 : 1;

            if (!$cat['name'])
            {
                $alert_service->error('Vul naam in!');
            }
            else if (($cat['stat_msgs_wanted'] + $cat['stat_msgs_offers']) && !$cat['leafnote'])
            {
                $alert_service->error('Hoofdcategoriën kunnen
                    geen berichten bevatten.');
            }
            else if ($cat['leafnote'] && $child_count_ary[$id])
            {
                $alert_service->error('Subcategoriën kunnen
                    geen categoriën bevatten.');
            }
            else if ($token_error = $form_token_service->get_error())
            {
                $alert_service->error($token_error);
            }
            else
            {
                $prefix = '';

                if ($cat['id_parent'])
                {
                    $prefix .= $db->fetchColumn('select name
                        from ' . $pp->schema() . '.categories
                        where id = ?', [$cat['id_parent']]) . ' - ';
                }

                $cat['fullname'] = $prefix . $cat['name'];
                unset($cat['id']);

                if ($db->update($pp->schema() . '.categories', $cat, ['id' => $id]))
                {
                    $alert_service->success('Categorie aangepast.');
                    $db->executeUpdate('update ' . $pp->schema() . '.categories
                        set fullname = ? || \' - \' || name
                        where id_parent = ?', [$cat['name'], $id]);

                    $link_render->redirect('categories', $pp->ary(), []);
                }

                $alert_service->error('Categorie niet aangepast.');
            }
        }

        $parent_cats = [0 => '-- Hoofdcategorie --'];

        $rs = $db->prepare('select id, name
            from ' . $pp->schema() . '.categories
            where leafnote = 0
            order by name');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $parent_cats[$row['id']] = $row['name'];
        }

        $id_parent = $cat['id_parent'] ?? 0;

        $heading_render->add('Categorie aanpassen : ');
        $heading_render->add($cat['name']);
        $heading_render->fa('clone');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-clone"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" ';
        $out .= 'value="';
        $out .= $cat["name"] ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="id_parent" class="control-label">';
        $out .= 'Hoofdcategorie of deelcategorie van</label>';
        $out .= '<select class="form-control" id="id_parent" name="id_parent">';
        $out .= $select_render->get_options($parent_cats, (string) $id_parent);
        $out .= '</select>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('categories', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" ';
        $out .= 'name="zend" class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('categories');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
