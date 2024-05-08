<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Cache\ConfigCache;
use App\Command\Transactions\TransactionsCurrencyCommand;
use App\Form\Type\Transactions\TransactionsCurrencyType;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class TransactionsCurrencyController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/transactions/currency',
        name: 'transactions_currency',
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
        ConfigCache $config_cache,
        PageParamsService $pp
    ):Response
    {
        if (!$config_cache->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        $command = new TransactionsCurrencyCommand();
        $config_cache->load_command($command, $pp->schema());

        $form = $this->createForm(TransactionsCurrencyType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $changed = $config_cache->store_command($command, $pp->schema());

            if ($changed)
            {
                $alert_service->success('Munteenheid aangepast');
            }
            else
            {
                $alert_service->warning('Munteenheid niet gewijzigd');
            }

            return $this->redirectToRoute('transactions_currency', $pp->ary());
        }

        return $this->render('transactions/transactions_currency.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
