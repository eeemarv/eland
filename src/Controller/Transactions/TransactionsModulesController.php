<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsModulesCommand;
use App\Form\Type\Transactions\TransactionsModulesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TransactionsModulesController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/transactions/modules',
        name: 'transactions_modules',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'transactions',
        ],
    )]

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $command = new TransactionsModulesCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(TransactionsModulesType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $changed = $config_service->store_command($command, $pp->schema());

            if ($changed)
            {
                $alert_service->success('Submodules/velden transacties aangepast');
            }
            else
            {
                $alert_service->warning('Submodules/velden transacties niet gewijzigd');
            }

            return $this->redirectToRoute('transactions_modules', $pp->ary());
        }

        return $this->render('transactions/transactions_modules.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
