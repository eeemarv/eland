<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersPeriodicOverviewCommand;
use App\Form\Type\Users\UsersPeriodicOverviewType;
use App\Repository\UserRepository;
use App\Routing\AccessLevel;
use App\Routing\SchemaRoute;
use App\Routing\UsersSubModule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class UsersPeriodicOverviewEditController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: UsersSubModule::PeriodicMail,
    is_self: false,
    path: '/users/{id}/periodic-overview/edit',
    name: 'users_periodic_overview_edit',
    methods: ['GET', 'POST'],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: UsersSubModule::PeriodicMail,
    is_self: true,
    path: '/users/self/periodic-overview/edit',
    name: 'users_periodic_overview_edit_self',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    bool $_is_self,
    UserRepository $user_repository,
    PageParamsService $pp,
    SessionUserService $su,
    int|null $id = null,
  ):Response
  {

    if (!$_is_self
      && $su->is_owner($id))
    {
      return $this->redirectToRoute(
        route: 'users_periodic_overview_edit_self',
        parameters: $pp->ary(),
      );
    }

    if ($_is_self)
    {
      $id = $su->id();
    }

    $user = $user_repository->get(
      id: $id,
      schema: $pp->schema_o(),
    );

    if ($user === false)
    {
      throw $this->createNotFoundException(
        'User with id ' . $id . ' not found'
      );
    }

    $form_options = [];

    $command = new UsersPeriodicOverviewCommand();

    $is_intersystem = isset($user['remote_schema']) || isset($user['remote_email']);
    $command->enabled = $user['periodic_overview_en'];
    $enabled = $user['periodic_overview_en'];

    $form = $this->createForm(
      type: UsersPeriodicOverviewType::class,
      data: $command,
      options: $form_options,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      if ($command->enabled === $enabled)
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]
        );
      }
      else
      {
        $user_repository->set_periodic_overview_en(
          id: $id,
          periodic_overview_en: $command->enabled,
          schema: $pp->schema_o(),
        );

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_periodic_overview_edit.flash.success',
            'params' => [
              'self'   => $_is_self ? 'yes' : 'no',
              'switch'   => $command->enabled ? 'on' : 'off',
              'user'  => $user['name'],
            ]
          ],
        );
      }

      if ($_is_self)
      {
        return $this->redirectToRoute(
          route: 'users_show_self',
          parameters: $pp->ary(),
        );
      }

      return $this->redirectToRoute(
        route: 'users_show',
        parameters: [
          ... $pp->ary(),
          'id' => $id,
        ],
      );
    }

    return $this->render('users/users_periodic_overview_edit.html.twig', [
      'form'              => $form->createView(),
      'user'              => $user,
      'id'                => $id,
      'is_self'           => $_is_self,
      'is_intersystem'    => $is_intersystem,
    ]);
  }
}
