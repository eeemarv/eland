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

        $errors = [];

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

            $config_service->set_int('accounts.limits.global.min', $form_data['min'], $pp->schema());
            $config_service->set_int('accounts.limits.global.max', $form_data['max'], $pp->schema());

            $alert_service->success('Systeemslimieten aangepast');
            $link_render->redirect('transactions_system_limits', $pp->ary(), []);
        }

        $menu_service->set('transactions_system_limits');

        return $this->render('transactions/transactions_system_limits.html.twig', [
            'form'          => $form->createView(),
            'form_token'    => $form_token_service->get(),
            'schema'        => $pp->schema(),
        ]);
    }
}
