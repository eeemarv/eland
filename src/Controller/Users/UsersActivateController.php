<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersAccountCodeCommand;
use App\Command\Users\UsersActivateCommand;
use App\Form\Type\Users\UsersAccountCodeType;
use App\Form\Type\Users\UsersActivateType;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\ResponseCacheService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersActivateController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/activate',
        name: 'users_activate',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
            'id'            => '%assert.id%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        ResponseCacheService $response_cache_service,
        UserRepository $user_repository,
        AlertService $alert_service,
        ConfigService $config_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $user = $user_repository->get($id, $pp->schema());

        if ($user['is_active'])
        {
            throw new AccessDeniedHttpException('This user account can not be activated; it is already active.');
        }


        $form_options = [];
        $command = new UsersActivateCommand();

        $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);


        $form = $this->createForm(UsersActivateType::class, $command, $form_options);
        $form->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $user_repository->update([
                'is_active'    => 't',
            ], $id, $pp->schema());

            $response_cache_service->clear_cache($pp->schema());

            $alert_service->success('Het transactie-account is aangemaakt met code ' . $command->code);


            return $this->redirectToRoute('users_show', [
                ... $pp->ary(),
                'id' => $id,
            ]);
        }

        return $this->render('users/users_account_code_edit.html.twig', [
            'form'              => $form->createView(),
            'user'              => $user,
            'id'                => $id,
            'is_intersystem'    => $is_intersystem,
        ]);
    }
}
