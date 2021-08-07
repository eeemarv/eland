<?php declare(strict_types=1);

namespace App\Controller\Cms;

use App\Cnst\RoleCnst;
use App\Command\Cms\CmsEditCommand;
use App\Form\Post\Cms\CmsEditType;
use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\StaticContentService;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class CmsEditController extends AbstractController
{
    const EMPTY_ARTEFACTS = [
        '<p><br></p>'   => true,
        '<br>'          => true,
        '<br/>'         => true,
        '<p></p>'       => true,
    ];

    #[Route(
        '/{system}/{role_short}/cms-edit',
        name: 'cms_edit',
        methods: ['POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'cms',
        ],
    )]

    public function __invoke(
        Request $request,
        StaticContentService $static_content_service,
        HtmlPurifier $html_purifier,
        AlertService $alert_service,
        SessionUserService $su,
        PageParamsService $pp
    ):Response
    {
        $command = new CmsEditCommand();
        $form = $this->createForm(CmsEditType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $content_ary = json_decode($command->content, true);
            $all_params = json_decode($command->all_params, true);
            $route = $command->route;

            if (!isset($all_params['edit']) || !isset($all_params['edit']['en']) || $all_params['edit']['en'] !== '1')
            {
                throw new BadRequestException('Invalid form, cms not enabled in query.');
            }

            $route_en = isset($all_params['edit']['route']) && $all_params['edit']['route'] === '1';
            $role_en = isset($all_params['edit']['role']) && $all_params['edit']['role'] === '1';

            if ($role_en && !isset($route_params['role_short']))
            {
                throw new BadRequestHttpException('CMS: role blocks are not allowed on public pages.');
            }

            $sel_route = $route_en ? $route : '';
            $sel_role = $role_en ? RoleCnst::LONG[$all_params['role_short']] : '';

            $count_updated = 0;

            foreach($content_ary as $block => $content)
            {
                $no_space_content = trim(preg_replace('/\s+/', '', $content));
                $set = isset(self::EMPTY_ARTEFACTS[$no_space_content]) ? '' : $html_purifier->purify($content);
                $get = $static_content_service->get($sel_role, $sel_route, $block, $pp->schema());
                if($get !== $set)
                {
                    $static_content_service->set($sel_role, $sel_route, $block, $set, $su, $pp->schema());
                    $count_updated++;
                }
            }

            switch($count_updated)
            {
                case 0:
                    $alert_service->warning('Geen content aangepast.');
                break;
                case 1:
                    $alert_service->success('1 content blok aangepast');
                break;
                default:
                    $alert_service->success($count_updated . ' content blokken aangepast.');
                break;
            }

            return $this->redirectToRoute($route, $all_params);
        }

        throw new BadRequestException('Invalid form');
    }
}
