<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class TransactionsLeavingEqController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/transactions/leaving-eq',
        name: 'transactions_leaving_eq',
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
        FormTokenService $form_token_service,
        AlertService $alert_service,
        ConfigService $config_service,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get_bool('transactions.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Transactions module not enabled.');
        }

        if (!$config_service->get_bool('users.leaving.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Users leaving status not enabled.');
        }

        $errors = [];

        $eq = $config_service->get_int('accounts.equilibrium', $pp->schema());

        $form_data = [
            'eq'   => $eq,
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('eq', IntegerType::class)
            ->add('submit', SubmitType::class);
        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($request->isMethod('POST'))
        {
            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (count($errors))
            {
                $alert_service->error($errors);
            }
        }

        if ($form->isSubmitted()
            && $form->isValid()
            && !count($errors))
        {
            $form_data = $form->getData();

            $config_service->set_int('accounts.equilibrium', $form_data['eq'], $pp->schema());

            $alert_service->success('Uitstappers saldo aangepast');
            $link_render->redirect('transactions_leaving_eq', $pp->ary(), []);
        }

        $menu_service->set('transactions_leaving_eq');

        return $this->render('transactions/transactions_leaving_eq.html.twig', [
            'form'          => $form->createView(),
            'form_token'    => $form_token_service->get(),
            'schema'        => $pp->schema(),
        ]);
    }
}
