<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPeriodicOverviewCommand;
use App\Form\Type\Users\UsersPeriodicOverviewType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersPeriodicOverviewEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/periodic-overview/edit',
        name: 'users_periodic_overview_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'id'            => '%assert.id%',
        ],
        defaults: [
            'is_self'       => false,
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/self/periodic-overview/edit',
        name: 'users_periodic_overview_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'            => 0,
            'is_self'       => true,
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        bool $is_self,
        UserRepository $user_repository,
        AlertService $alert_service,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_service->get_bool('periodic_mail.enabled', $pp->schema()))
        {
            throw new AccessDeniedHttpException('Periodic mail submodule not enabled.');
        }

        if (!$is_self
            && $su->is_owner($id))
        {
            return $this->redirectToRoute('users_periodic_overview_edit_self', $pp->ary());
        }

        if ($is_self)
        {
            $id = $su->id();
        }

        $command = new UsersPeriodicOverviewCommand();

        $user = $user_repository->get($id, $pp->schema());
        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
        $command->enabled = $user['periodic_overview_en'];
        $enabled = $user['periodic_overview_en'];

        $form = $this->createForm(UsersPeriodicOverviewType::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            if ($command->enabled === $enabled)
            {
                $alert_service->warning('Ontvangst periodieke overzichts e-mail niet gewijzigd');
            }
            else
            {
                $pg_enabled = $command->enabled ? 't' : 'f';

                $user_repository->update([
                    'periodic_overview_en'    => $pg_enabled,
                ], $id, $pp->schema());

                $alert_service->success('Ontvangst periodieke overzichts e-mail aangepast');
            }

            if ($is_self)
            {
                return $this->redirectToRoute('users_show_self', $pp->ary());
            }

            return $this->redirectToRoute('users_show', [
                ... $pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('users/users_periodic_overview_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_self'           => $is_self,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
