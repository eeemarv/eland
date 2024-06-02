<?php declare(strict_types=1);

namespace App\Controller\Cms;

use App\Command\Cms\CmsEditCommand;
use App\Form\Type\Cms\CmsEditType;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\StaticContentService;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsController]
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
        #[Autowire(service: 'html_sanitizer.sanitizer.cms_sanitizer')] HtmlSanitizerInterface $html_sanitizer,
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
            $route_enabled = $command->route_en === '1';
            $role = $command->role;
            $role_enabled = $command->role_en === '1';

            $sel_route = $route_enabled ? $route : null;
            $sel_role = $role_enabled ? $role : null;

            $page_id = null;

            if (isset($sel_route) && isset($all_params['page_id']))
            {
                $page_id = (int) $all_params['page_id'];
            }

            $count_updated = 0;

            foreach($content_ary as $block => $content)
            {
                $no_space_content = trim(preg_replace('/\s+/', '', $content));
                $set = '';

                error_log('block ' . $block . ' -- ' . $content);

                if (!isset(self::EMPTY_ARTEFACTS[$no_space_content]))
                {
                    error_log('sanitize');
                    $set =  $html_sanitizer->sanitize($content);
                }

                error_log('SET block ' . $block . ' -- ' . $set);

                $get = $static_content_service->get($sel_role, $sel_route, $page_id, $block, $pp->schema());

                if($get !== $set)
                {
                    $static_content_service->set($sel_role, $sel_route, $page_id, $block, $set, $su, $pp->schema());
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
