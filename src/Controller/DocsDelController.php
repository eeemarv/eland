<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\TypeaheadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocsDelController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        ConfigService $config_service,
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
        if (!$config_service->get_bool('docs.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Documents module not enabled.');
        }

        $errors = [];

        $doc = $db->fetchAssociative('select *
            from ' . $pp->schema() . '.docs
            where id = ?', [$id], [\PDO::PARAM_INT]);

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
                    $doc_count = $db->fetchOne('select count(*)
                        from ' . $pp->schema() . '.docs
                        where map_id = ?',
                        [$doc['map_id']], [\PDO::PARAM_INT]);

                    if ($doc_count < 2)
                    {
                        $db->delete($pp->schema() . '.doc_maps', ['id' => $doc['map_id']]);

                        $typeahead_service->delete_thumbprint('doc_map_names',
                            $pp->ary(), []);

                        unset($doc['map_id']);
                    }
                }

                $db->delete($pp->schema() . '.docs', ['id' => $id]);

                $alert_service->success('Het document werd verwijderd.');

                if (isset($doc['map_id']))
                {
                    $link_render->redirect('docs_map', $pp->ary(),
                        ['id' => $doc['map_id']]);
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
        $out .= htmlspecialchars($doc['name'] ?? $doc['original_filename'], ENT_QUOTES);
        $out .= '</a>';
        $out .= '</p>';

        if (isset($doc['map_id']))
        {
            $out .= $link_render->btn_cancel('docs_map', $pp->ary(),
                ['id' => $doc['map_id']]);
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
