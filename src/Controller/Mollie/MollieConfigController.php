<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Command\Mollie\MollieConfigCommand;
use App\Form\Type\Mollie\MollieConfigType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class MollieConfigController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/mollie/config',
        name: 'mollie_config',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'mollie',
        ],
    )]

    public function __invoke(
        Request $request,
        ConfigService $config_service,
        AlertService $alert_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('mollie.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Mollie submodule (users) not enabled.');
        }

        $command = new MollieConfigCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(MollieConfigType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('De Mollie Apikey is aangepast.');
            return $this->redirectToRoute('mollie_payments', $pp->ary());
        }

        return $this->render('mollie/mollie_config.html.twig', [
            'form'      => $form->createView(),
        ]);
    }
}
