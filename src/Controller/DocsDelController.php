<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\XdbService;
use Psr\Log\LoggerInterface;

class DocsDelController extends AbstractController
{
    public function docs_del(
        Request $request,
        string $doc_id,
        XdbService $xdb_service,
        LoggerInterface $logger,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        S3Service $s3_service,
        TypeaheadService $typeahead_service,
        MenuService $menu_service,
        PageParamsService $pp,
        string $env_s3_url
    ):Response
    {
        $row = $xdb_service->get('doc', $doc_id, $pp->schema());

        if ($row)
        {
            $doc = $row['data'];
        }
        else
        {
            $alert_service->error('Document niet gevonden');
            $link_render->redirect('docs', $pp->ary(), []);
        }

        if ($request->isMethod('POST'))
        {
            $errors = [];

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $err = $s3_service->del($doc['filename']);

                if ($err)
                {
                    $logger->error('doc delete file fail: ' . $err,
                        ['schema' => $pp->schema()]);
                }

                if (isset($doc['map_id']))
                {
                    $rows = $xdb_service->get_many(['agg_schema' => $pp->schema(),
                        'agg_type'	=> 'doc',
                        'data->>\'map_id\'' => $doc['map_id']]);

                    if (count($rows) < 2)
                    {
                        $xdb_service->del('doc', $doc['map_id'], $pp->schema());

                        $typeahead_service->delete_thumbprint('doc_map_names',
                            $pp->ary(), []);

                        unset($doc['map_id']);
                    }
                }

                $xdb_service->del('doc', $doc_id, $pp->schema());

                $alert_service->success('Het document werd verwijderd.');

                if (isset($doc['map_id']))
                {
                    $link_render->redirect('docs_map', $pp->ary(),
                        ['map_id' => $doc['map_id']]);
                }

                $link_render->redirect('docs', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $heading_render->add('Document verwijderen?');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';
        $out .= '<form method="post">';

        $out .= '<p>';
        $out .= '<a href="';
        $out .= $env_s3_url . $doc['filename'];
        $out .= '" target="_self">';
        $out .= $doc['name'] ?? $doc['org_filename'];
        $out .= '</a>';
        $out .= '</p>';

        if (isset($doc['map_id']))
        {
            $out .= $link_render->btn_cancel('docs_map', $pp->ary(),
                ['map_id' => $doc['map_id']]);
        }
        else
        {
            $out .= $link_render->btn_cancel('docs', $pp->ary(), []);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" ';
        $out .= 'name="confirm_del" class="btn btn-danger btn-lg">';
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
