<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

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

        $min = $config_service->get_int('accounts.limits.global.min', $pp->schema());
        $max = $config_service->get_int('accounts.limits.global.max', $pp->schema());

        $form_data = [
            'min'   => $min,
            'max'   => $max,
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('min', IntegerType::class)
            ->add('max', IntegerType::class)
            ->add('submit', SubmitType::class);
        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $form_data = $form->getData();

            $config_service->set_int('accounts.limits.global.min', $form_data['min'], $pp->schema());
            $config_service->set_int('accounts.limits.global.max', $form_data['max'], $pp->schema());

            $alert_service->success('Systeemslimieten aangepast');

            return $this->redirectToRoute('transactions_system_limits', $pp->ary());
        }

        return $this->render('transactions/transactions_system_limits.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
