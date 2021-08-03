<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsAutoMinLimitCommand;
use App\Form\Post\Transactions\TransactionsAutoMinLimitType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class TransactionsAutoMinLimitController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/auto-min-limit',
        name: 'transactions_autominlimit',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'transactions',
            'sub_module'    => 'autominlimit',
        ],
    )]

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        PageParamsService $pp,
        ConfigService $config_service
    ):Response
    {
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        if (!$config_service->get_bool('accounts.limits.auto_min.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Submodule auto min limit not enabled.');
        }

        $command = new TransactionsAutoMinLimitCommand();
        $config_service->load_command($command, $pp->schema());

        $form = $this->createForm(TransactionsAutoMinLimitType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();
            $config_service->store_command($command, $pp->schema());

            $alert_service->success('De automatische minimum limiet instellingen zijn aangepast.');
            return $this->redirectToRoute('transactions_autominlimit', $pp->ary());
        }

        return $this->render('transactions/transactions_autominlimit.html.twig', [
            'form'      => $form->createView(),
        ]);
    }
}
