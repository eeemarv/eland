<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;

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
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        $form_data = [];

        $service_stuff = $config_service->get_bool('transactions.fields.service_stuff.enabled', $pp->schema());
        $limits = $config_service->get_bool('accounts.limits.enabled', $pp->schema());
        $autominlimit = $config_service->get_bool('accounts.limits.auto_min.enabled', $pp->schema());
        $mass = $config_service->get_bool('transactions.mass.enabled', $pp->schema());

        $form_data = [
            'service_stuff'     => $service_stuff,
            'limits'            => $limits,
            'autominlimit'      => $autominlimit,
            'mass'              => $mass,
        ];

        $builder = $this->createFormBuilder($form_data);
        $builder->add('service_stuff', CheckboxType::class);
        $builder->add('limits', CheckboxType::class);
        $builder->add('autominlimit', CheckboxType::class);
        $builder->add('mass', CheckboxType::class);
        $builder->add('submit', SubmitType::class);
        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $form_data = $form->getData();

            $service_stuff = $form_data['service_stuff'];
            $limits = $form_data['limits'];
            $autominlimit = $form_data['autominlimit'];
            $mass = $form_data['mass'];

            $config_service->set_bool('transactions.fields.service_stuff.enabled', $service_stuff, $pp->schema());
            $config_service->set_bool('accounts.limits.enabled', $limits, $pp->schema());
            $config_service->set_bool('accounts.limits.auto_min.enabled', $autominlimit, $pp->schema());
            $config_service->set_bool('transactions.mass.enabled', $mass, $pp->schema());

            $alert_service->success('Submodules/velden transacties aangepast');
            $link_render->redirect('transactions_modules', $pp->ary(), []);
        }

        return $this->render('transactions/transactions_modules.html.twig', [
            'form'          => $form->createView(),
        ]);
    }
}
