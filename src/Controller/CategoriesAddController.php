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
use App\Service\SessionUserService;

class CategoriesAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        HeadingRender $heading_render,
        PageParamsService $pp,
        SessionUserService $su,
        SelectRender $select_render
    ):Response
    {
        $cat = [];

        if ($request->isMethod('POST'))
        {
            $cat['name'] = $request->request->get('name', '');
            $cat['id_parent'] = (int) $request->request->get('id_parent', 0);
            $cat['leafnote'] = $cat['id_parent'] === 0 ? 0 : 1;

            if (trim($cat['name']) === '')
            {
                $errors[] = 'Vul naam in!';
            }

            if (strlen($cat['name']) > 40)
            {
                $errors[] = 'De naam mag maximaal 40 tekens lang zijn.';
            }

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (!count($errors))
            {
                $cat['cdate'] = gmdate('Y-m-d H:i:s');
                $cat['id_creator'] = $su->is_master() ? 0 : $su->id();
                $cat['fullname'] = '';

                if ($cat['leafnote'])
                {
                    $cat['fullname'] .= $db->fetchColumn('select name
                        from ' . $pp->schema() . '.categories
                        where id = ?', [(int) $cat['id_parent']]);
                    $cat['fullname'] .= ' - ';
                }

                $cat['fullname'] .= $cat['name'];

                if ($db->insert($pp->schema() . '.categories', $cat))
                {
                    $alert_service->success('Categorie toegevoegd.');
                    $link_render->redirect('categories', $pp->ary(), []);
                }

                $alert_service->error('Categorie niet toegevoegd.');
            }
            else
            {
                $alert_service->error($errors);
            }
        }

        $parent_cats = [0 => '-- Hoofdcategorie --'];

        $rs = $db->prepare('select id, name
            from ' . $pp->schema() . '.categories
            where leafnote = 0 order by name');

        $rs->execute();

        while ($row = $rs->fetch())
        {
            $parent_cats[$row['id']] = $row['name'];
        }

        $id_parent = $cat['id_parent'] ?? 0;

        $heading_render->add('Categorie toevoegen');
        $heading_render->fa('clone');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form  method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-clone"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" ';
        $out .= 'value="';
        $out .= $cat['name'] ?? '';
        $out .= '" required maxlength="40">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="id_parent" class="control-label">';
        $out .= 'Hoofdcategorie of deelcategorie van</label>';
        $out .= '<select name="id_parent" id="id_parent" class="form-control">';
        $out .= $select_render->get_options($parent_cats, (string) ($id_parent ?? 0));
        $out .= '</select>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('categories', $pp->ary(), []);
        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Toevoegen" ';
        $out .= 'class="btn btn-success btn-lg">';
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
