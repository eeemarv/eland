<?php declare(strict_types=1);

namespace App\Controller\Register;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Queue\MailQueue;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\MailAddrSystemService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterTokenAdminController extends AbstractController
{
    public function __invoke(
        string $token,
        Db $db,
        TranslatorInterface $translator,
        ConfigService $config_service,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        LinkRender $link_render,
        MailAddrSystemService $mail_addr_system_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $alert_service->success('Inschrijving voltooid.');

        $registration_success_text = $config_service->get('registration_success_text', $pp->schema());

        $menu_service->set('register');

        return $this->render('register/register_token_admin.html.twig', [
            'content'   => $registration_success_text ?: '',
            'schema'    => $pp->schema(),
        ]);
    }
}
