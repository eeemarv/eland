<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\AccessCnst;
use App\Service\AlertService;
use App\Service\MenuService;
use App\Service\FormTokenService;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\XdbService;
use Psr\Log\LoggerInterface;

class DocsAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        LoggerInterface $logger,
        string $map_id,
        XdbService $xdb_service,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        S3Service $s3_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        $errors = [];

        if ($request->isMethod('POST'))
        {
            $f_file = $request->files->get('file');

            if (!$f_file)
            {
                $errors[] = 'Geen bestand geselecteerd.';
            }
            else
            {
                $ext = $f_file->getClientOriginalExtension();
                $original_filename = $f_file->getClientOriginalName();
                $file_size = $f_file->getSize();
                $tmpfile = $f_file->getRealPath();

                if ($file_size > 1024 * 1024 * 10)
                {
                    $errors[] = 'Het bestand is te groot. De maximum grootte is 10MB.';
                }
            }

            $access = $request->request->get('access', '');

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in';
            }

            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (count($errors))
            {
                $alert_service->error($errors);
            }
            else
            {
                $doc_id = substr(sha1(random_bytes(16)), 0, 24);

                $filename = $pp->schema() . '_d_' . $doc_id . '.' . $ext;

                $error = $s3_service->doc_upload($filename, $tmpfile);

                if ($error)
                {
                    $logger->error('doc upload fail: ' . $error);
                    $alert_service->error('Bestand opladen mislukt.',
                        ['schema' => $pp->schema()]);
                }
                else
                {
                    $doc = [
                        'filename'		=> $filename,
                        'org_filename'	=> $original_filename,
                        'access'		=> AccessCnst::TO_XDB[$access],
                        'user_id'		=> $su->id(),
                    ];

                    $map_name = trim($request->request->get('map_name', ''));

                    if (strlen($map_name))
                    {
                        $rows = $xdb_service->get_many(['agg_schema' => $pp->schema(),
                            'agg_type' => 'doc',
                            'data->>\'map_name\'' => $map_name], 'limit 1');

                        if (count($rows))
                        {
                            $map = reset($rows)['data'];
                            $map_id = reset($rows)['eland_id'];
                        }

                        if (!$map)
                        {
                            $map_id = substr(sha1(random_bytes(16)), 0, 24);

                            $map = ['map_name' => $map_name];

                            $xdb_service->set('doc', $map_id, $map, $pp->schema());

                            $typeahead_service->delete_thumbprint('doc_map_names',
                                $pp->ary(), []);
                        }

                        $doc['map_id'] = $map_id;
                    }

                    $name = trim($request->request->get('name', ''));

                    if ($name)
                    {
                        $doc['name'] = $name;
                    }

                    $xdb_service->set('doc', $doc_id, $doc, $pp->schema());


                    $alert_service->success('Het bestand is opgeladen.');

                    if (isset($doc['map_id']))
                    {
                        $link_render->redirect('docs_map', $pp->ary(),
                            ['map_id' => $doc['map_id']]);
                    }

                    $link_render->redirect('docs', $pp->ary(), []);
                }
            }
        }

        if ($map_id)
        {
            $row = $xdb_service->get('doc', $map_id, $pp->schema());

            if ($row)
            {
                $map_name = $row['data']['map_name'];
            }
        }

        $heading_render->add('Nieuw document opladen');
        $heading_render->fa('files-o');

        $out = '<div class="panel panel-info" id="add">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post" enctype="multipart/form-data">';

        $out .= '<div class="form-group">';
        $out .= '<label for="file" class="control-label">';
        $out .= 'Bestand</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-file-o"></i>';
        $out .= '</span>';
        $out .= '<input type="file" class="form-control" id="file" name="file" ';
        $out .= 'required>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="name" class="control-label">';
        $out .= 'Naam (optioneel)</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-file-o"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="name" name="name">';
        $out .= '</div>';
        $out .= '</div>';

        $out .= $item_access_service->get_radio_buttons('access', $access ?? '', 'docs');

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
        $out .= '<p>Optioneel. Creëer een nieuwe map of ';
        $out .= 'selecteer een bestaande.</p>';
        $out .= '</div>';

        if ($map_id)
        {
            $out .= $link_render->btn_cancel('docs_map', $pp->ary(),
                ['map_id' => $map_id]);
        }
        else
        {
            $out .= $link_render->btn_cancel('docs', $pp->ary(), []);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" name="zend" ';
        $out .= 'value="Document opladen" class="btn btn-success btn-lg">';
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
