<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Render\LinkRender;
use App\Service\CmsEditFormTokenService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class CmsEditController extends AbstractController
{
    public function __invoke(
        string $route,
        string $form_token,
        CmsEditFormTokenService $cms_edit_form_token_service,
        ConfigService $config_service,
        LinkRender $link_render,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        if (!$cms_edit_form_token_service->verify($form_token))
        {
            throw new BadRequestException('Invalid form token.');
        }






        $alert_service->error('Ongeldig of verlopen token.');
        $link_render->redirect('contact_form', $pp->ary(), []);


        $alert_service->success('Je bericht werd succesvol verzonden.');
        $link_render->redirect('contact_form', $pp->ary(), []);

        return new Response();
    }
}
