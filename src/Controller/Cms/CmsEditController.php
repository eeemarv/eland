<?php declare(strict_types=1);

namespace App\Controller\Cms;

use App\Cnst\RoleCnst;
use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\CmsEditFormTokenService;
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
        '/{system}/{role_short}/cms-edit/{route}/{form_token}',
        name: 'cms_edit',
        methods: ['POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'route'         => '%assert.route%',
            'form_token'    => '%assert.token%',
        ],
        defaults: [
            'module'        => 'cms',
        ],
    )]

    public function __invoke(
        string $route,
        string $form_token,
        Request $request,
        StaticContentService $static_content_service,
        CmsEditFormTokenService $cms_edit_form_token_service,
        HtmlPurifier $html_purifier,
        AlertService $alert_service,
        SessionUserService $su,
        PageParamsService $pp
    ):Response
    {
        if (!$cms_edit_form_token_service->verify($form_token))
        {
            throw new BadRequestException('Invalid cms form token.');
        }

        $content_json = $request->request->get('content');
        $route_params_json = $request->request->get('route_params');
        $query_params_json = $request->request->get('query_params');

        if ($content_json === false || $route_params_json === false || $query_params_json === false)
        {
            throw new BadRequestException('Invalid cms form, missing fields.');
        }

        $content_ary = json_decode($content_json, true);
        $route_params = json_decode($route_params_json, true);
        $query_params = json_decode($query_params_json, true);

        if (!isset($query_params['edit']) || !isset($query_params['edit']['en']) || $query_params['edit']['en'] !== '1')
        {
            throw new BadRequestException('Invalid form, cms not enabled in query.');
        }

        $route_en = isset($query_params['edit']['route']) && $query_params['edit']['route'] === '1';
        $role_en = isset($query_params['edit']['role']) && $query_params['edit']['role'] === '1';

        if ($role_en && !isset($route_params['role_short']))
        {
            throw new BadRequestHttpException('CMS: role blocks are not allowed on public pages.');
        }

        $sel_route = $route_en ? $route : '';
        $sel_role = $role_en ? RoleCnst::LONG[$route_params['role_short']] : '';

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

        $params = array_merge($route_params, $query_params);

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

        return $this->redirectToRoute($route, $params);
    }
}
