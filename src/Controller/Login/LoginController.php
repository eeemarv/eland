<?php declare(strict_types=1);

namespace App\Controller\Login;

use App\Command\Login\LoginCommand;
use App\Form\Type\Login\LoginType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\AccountRender;
use App\Repository\LoginRepository;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class LoginController extends AbstractController
{
  #[Route(
    '/{schema}/login',
    name: 'login',
    methods: ['GET', 'POST'],
    priority: 30,
    requirements: [
      'schema'        => '%assert.schema%',
    ],
  )]

  public function __invoke(
    Request $request,
    UserRepository $user_repository,
    LoginRepository $login_repository,
    ConfigService $config_service,
    LoggerInterface $logger,
    AccountRender $account_render,
    PageParamsService $pp,
    SessionUserService $su,
    VarRouteService $vr,
  ):Response
  {
    $location = $request->query->get('location', '');

    if (!$location
      || str_contains($location, 'login')
      || str_contains($location, 'logout')
      || $location === '/')
    {
      $location = '';
    }

    $command = new LoginCommand();
    $command->login = $request->query->get('login');

    $form = $this->createForm(LoginType::class, $command);

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();

      if (isset($command->is_master) && $command->is_master)
      {
        $su->set_master_login($pp->schema());

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'login.flash.success_master',
          ],
        );

        if ($location)
        {
          return $this->redirect($location);
        }

        $pp_ary = [
          'schema'        => $pp->schema(),
          'role_short'    => 'a',
        ];

        return $this->redirectToRoute($vr->get('default'), $pp_ary);
      }

      if (!isset($command->id) || !$command->id)
      {
        throw new \LogicException('No user id set in validator.');
      }

      $su->set_login(
        schema: $pp->schema(),
        user_id: $command->id
      );

      $agent = $request->server->get('HTTP_USER_AGENT');
      $ip = $request->getClientIp();

      $user = $user_repository->get(
        id: $command->id,
        schema: $pp->schema_o(),
      );

      if ($user === false)
      {
        throw $this->createNotFoundException(
          'User with id ' . $command->id . ' not found'
        );
      }

      $log_ary = [
        'user_id'	=> $user['id'],
        'code'	    => $user['code'],
        'username'	=> $user['name'],
        'schema' 	=> $pp->schema(),
      ];

      $logger->info('User ' .
        $account_render->str_id($command->id, $pp->schema()) .
        ' logged in, agent: ' . $agent, $log_ary);

      if (isset($command->password_hashing_updated)
        && $command->password_hashing_updated
      )
      {
        $logger->info('Password hashing updated', $log_ary);
      }

      $login_repository->insert(
        user_id: $command->id,
        agent: $agent,
        ip: $ip,
        schema: $pp->schema_o(),
      );

      $this->addFlash(
        type: 'success',
        message: [
          'key' => 'login.flash.success',
        ],
      );

      if ($location)
      {
        return $this->redirect($location);
      }

      $su_ary = [
        'schema'      => $pp->schema(),
        'role_short'  => 'u',
      ];

      if ($user['role'] === 'admin'
        && $config_service->get_bool(
        config_id: 'users.admin.login.as_admin.enabled',
        schema: $pp->schema_o(),
      ))
      {
        $su_ary['role_short'] = 'a';
      }

      return $this->redirectToRoute($vr->get('default'), $su_ary);
    }

    if($config_service->get_bool(
      config_id: 'system.maintenance_en',
      schema: $pp->schema_o(),
    ))
    {
      $this->addFlash(
        type: 'warning',
        message: [
          'key' => 'flash.maintenance',
        ],
      );
    }

    if ($request->isMethod('GET') && $su->is_user())
    {
      if ($location)
      {
        if (stripos($location, $pp->schema() . '/a/') === false)
        {
          return $this->redirect($location);
        }
      }

      return $this->redirectToRoute($vr->get('default'), $su->ary());
    }

    return $this->render('login/login.html.twig', [
      'form'      => $form->createView(),
    ]);
  }
}
