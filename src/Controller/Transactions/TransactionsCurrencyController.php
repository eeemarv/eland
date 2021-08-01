<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

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
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
        $timebased_en = $config_service->get_bool('transactions.currency.timebased_en', $pp->schema());
        $per_hour_ratio = $config_service->get_int('transactions.currency.per_hour_ratio', $pp->schema());

        $form_data = [
            'currency'          => $currency,
            'timebased_en'      => $timebased_en,
            'per_hour_ratio'    => $per_hour_ratio,
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('currency', TextType::class)
            ->add('timebased_en', CheckboxType::class)
            ->add('per_hour_ratio', IntegerType::class)
            ->add('submit', SubmitType::class);
        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $form_data = $form->getData();

            $config_service->set_str('transactions.currency.name', $form_data['currency'], $pp->schema());
            $config_service->set_bool('transactions.currency.timebased_en', $form_data['timebased_en'], $pp->schema());
            $config_service->set_int('transactions.currency.per_hour_ratio', $form_data['per_hour_ratio'], $pp->schema());

            $alert_service->success('Munteenheid aangepast');

            return $this->redirectToRoute('transactions_currency', $pp->ary());
        }

        return $this->render('transactions/transactions_currency.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
