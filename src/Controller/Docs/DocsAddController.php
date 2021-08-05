<?php declare(strict_types=1);

namespace App\Controller\Docs;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Render\LinkRender;
use App\Service\ConfigService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class DocsAddController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/docs/add',
        name: 'docs_add',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'docs',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        ConfigService $config_service,
        LoggerInterface $logger,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        S3Service $s3_service,
        TypeaheadService $typeahead_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('docs.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Documents module not enabled.');
        }

        $errors = [];

        $map_id = $request->query->get('map_id', '');
        $name = trim($request->request->get('name', ''));
        $map_name = trim($request->request->get('map_name', ''));
        $access = $request->request->get('access', '');
        $f_file = $request->files->get('file');

        if ($request->isMethod('POST'))
        {
            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

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

            if (!$access)
            {
                $errors[] = 'Vul een zichtbaarheid in';
            }

            if (!count($errors))
            {
                $doc_id = substr(sha1(random_bytes(16)), 0, 24);
                $filename = $pp->schema() . '_d_' . $doc_id . '.' . $ext;
                $error = $s3_service->doc_upload($filename, $tmpfile);

                if ($error)
                {
                    $errors[] = 'Opladen document mislukt.';
                    $logger->error('doc upload fail: ' . $error,
                        ['schema' => $pp->schema()]);
                }
            }

            if (!count($errors))
            {
                $doc = [
                    'filename'		    => $filename,
                    'original_filename' => $original_filename,
                    'access'		    => $access,
                    'user_id'		    => $su->id(),
                ];

                if ($name)
                {
                    $doc['name'] = $name;
                }

                if (strlen($map_name))
                {
                    $map_id = $db->fetchOne('select id
                        from ' . $pp->schema() . '.doc_maps
                        where lower(name) = ?',
                        [strtolower($map_name)],
                        [\PDO::PARAM_STR]
                    );

                    if (!$map_id)
                    {
                        $db->insert($pp->schema() . '.doc_maps', [
                            'name'      => $map_name,
                            'user_id'   => $su->id(),
                        ]);

                        $map_id = (int) $db->lastInsertId($pp->schema() . '.doc_maps_id_seq');

                        $typeahead_service->delete_thumbprint('doc_map_names',
                            $pp->ary(), []);
                    }

                    $doc['map_id'] = $map_id;
                }

                $db->insert($pp->schema() . '.docs', $doc);

                $alert_service->success('Het bestand is opgeladen.');

                if (isset($doc['map_id']))
                {
                    return $this->redirectToRoute('docs_map', array_merge($pp->ary(),
                        ['id' => $doc['map_id']]));
                }

                return $this->redirectToRoute('docs', $pp->ary());
            }

            $alert_service->error($errors);
        }

        if ($map_id)
        {
            $map_name = $db->fetchOne('select name
                from ' . $pp->schema() . '.doc_maps
                where id = ?',
                [$map_id], [\PDO::PARAM_STR]);
            $map_name = $map_name ?: '';
        }

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
                ['id' => $map_id]);
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

        return $this->render('docs/docs_add.html.twig', [
            'content'   => $out,
        ]);
    }
}
