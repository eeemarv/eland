<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsSystemLimitsCommand;
use App\Form\Type\Transactions\TransactionsSystemLimitsType;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TransactionsSystemLimitsController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/transactions/system-limits',
        name: 'transactions_system_limits',
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
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        $command = new TransactionsSystemLimitsCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(TransactionsSystemLimitsType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('Systeemslimieten aangepast');
            return $this->redirectToRoute('transactions_system_limits', $pp->ary());
        }

        return $this->render('transactions/transactions_system_limits.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
