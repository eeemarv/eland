<?php declare(strict_types=1);

namespace App\Controller\UsersConfig;

use App\Cache\ConfigCache;
use App\Command\UsersConfig\UsersConfigFullNameCommand;
use App\Form\Type\UsersConfig\UsersConfigFullNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersConfigFullNameController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/config/full-name',
        name: 'users_config_full_name',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        ConfigCache $config_cache,
        PageParamsService $pp
    ):Response
    {
        if (!$config_cache->get_bool('users.fields.full_name.enabled', $pp->schema()))
        {
            throw new AccessDeniedHttpException('Full name module not enabled.');
        }

        $command = new UsersConfigFullNameCommand();
        $config_cache->load_command($command, $pp->schema());

        $form = $this->createForm(UsersConfigFullNameType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $changed = $config_cache->store_command($command, $pp->schema());

            if ($changed)
            {
                $alert_service->success('Volledige naam configuratie aangepast');
            }
            else
            {
                $alert_service->warning('Volledige naam configuratie niet gewijzigd');
            }

            return $this->redirectToRoute('users_config_full_name', $pp->ary());
        }

        return $this->render('users_config/users_config_full_name.html.twig', [
            'form'  => $form->createView(),
        ]);
    }
}
