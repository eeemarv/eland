<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use Doctrine\DBAL\Connection as Db;

class DocsEditController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        AlertService $alert_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        TypeaheadService $typeahead_service,
        MenuService $menu_service,
        FormTokenService $form_token_service,
        PageParamsService $pp,
        SessionUserService $su,
        string $env_s3_url
    ):Response
    {
        $errors = [];

        $access = $request->request->get('access', '');
        $name = trim($request->request->get('name', ''));
        $map_name = trim($request->request->get('map_name', ''));

        $doc = $db->fetchAssoc('select *
            from ' . $pp->schema() . '.docs
            where id = ?', [$id]);

        if (!$doc)
        {
            throw new NotFoundHttpException('Document met id ' . $id . ' niet gevonden.');
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in.';
            }

            $update = [
                'name'			=> $name === '' ? null : $name,
                'access'		=> $access,
            ];

            if (!count($errors))
            {
                if (isset($doc['map_id']))
                {
                    $map_doc_count = $db->fetchColumn('select count(*)
                        from ' . $pp->schema() . '.docs
                        where map_id = ?', [$doc['map_id']]);
                }
                else
                {
                    $map_doc_count = 0;
                }

                if (strlen($map_name))
                {
                    $map_id = $db->fetchColumn('select id
                        from ' . $pp->schema() . '.doc_maps
                        where lower(name) = ?', [$map_name]);

                    if (!$map_id)
                    {
                        $db->insert($pp->schema() . '.doc_maps', [
                            'name'      => $map_name,
                            'user_id'   => $su->id(),
                        ]);

                        $map_id = (int) $db->lastInsertId($pp->schema() . '.doc_maps_id_seq');

                        $delete_thumbprint = true;
                    }

                    if ($map_doc_count === 1 && $map_id !== $doc['map_id'])
                    {
                        $delete_map = true;
                    }
                }
                else if ($map_doc_count === 1)
                {
                    $delete_map = true;
                }

                $update['map_id'] = $map_id ?? null;

                if (isset($delete_map) && $delete_map)
                {
                    $db->delete($pp->schema() . '.doc_maps', ['id' => $doc['map_id']]);
                    $delete_thumbprint = true;
                }

                if (isset($delete_thumbprint) && $delete_thumbprint)
                {
                    $typeahead_service->delete_thumbprint('doc_map_names',
                        $pp->ary(), []);
                }

                $db->update($pp->schema() . '.docs', $update, ['id' => $id]);

                $alert_service->success('Document aangepast');

                if (!isset($update['map_id']))
                {
                    $link_render->redirect('docs', $pp->ary(), []);
                }

                $link_render->redirect('docs_map', $pp->ary(),
                    ['id' => $update['map_id']]);
            }

            $alert_service->error($errors);
        }

        if ($request->isMethod('GET'))
        {
            if (isset($doc['map_id']))
            {
                $map_name = $db->fetchColumn('select name
                    from ' . $pp->schema() . '.doc_maps
                    where id = ?', [$doc['map_id']]);
            }

            $name = $doc['name'] ?? '';
            $access = $doc['access'];
        }

        $heading_render->add('Document aanpassen');

        $out = '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="location" class="control-label">';
        $out .= 'Locatie</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-file-o"></span></span>';
        $out .= '<input type="text" class="form-control" id="location" ';
        $out .= 'name="location" value="';
        $out .= $env_s3_url . $doc['filename'];
        $out .= '" readonly>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="org_filename" class="control-label">';
        $out .= 'Originele bestandsnaam</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-file-o"></span></span>';
        $out .= '<input type="text" class="form-control" id="org_filename" ';
        $out .= 'name="org_filename" value="';
        $out .= $doc['original_filename'] ?? '';
        $out .= '" readonly>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam (optioneel)</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-file-o"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name" value="';
        $out .= $name ?? '';
        $out .= '">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $item_access_service->get_radio_buttons('access', $access, 'docs');

        $out .= '<div class="form-group">';
        $out .= '<label for="map_name" class="control-label">';
        $out .= 'Map</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-folder-o"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="map_name" name="map_name" value="';
        $out .= $map_name ?? '';
        $out .= '" ';
        $out .= 'data-typeahead="';

        $out .= $typeahead_service->ini($pp->ary())
            ->add('doc_map_names', [])
            ->str();

        $out .= '">';
        $out .= '</div>';
        $out .= '<p>Optioneel. Creëer een nieuwe map ';
        $out .= 'of selecteer een bestaande.</p>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel('docs', $pp->ary(), []);

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" value="Aanpassen" class="btn btn-primary btn-lg">';

        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('docs');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
