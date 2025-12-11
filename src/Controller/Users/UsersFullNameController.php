<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Command\Users\UsersFullNameCommand;
use App\Form\Type\Users\UsersFullNameType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersFullNameController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/full-name',
    name: 'users_full_name',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'users.fields.full_name.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw new AccessDeniedHttpException('Full name module not enabled.');
    }

    $command = new UsersFullNameCommand();
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: UsersFullNameType::class,
      data: $command,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $command = $form->getData();

      $changed = $config_service->store_command(
        command: $command,
        user_id: $su->id() ?: null,
        schema: $pp->schema_o(),
      );

      if ($changed)
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_full_name.flash.change',
          ],
        );
      }
      else
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ],
        );
      }

      return $this->redirectToRoute('users_full_name', $pp->ary());
    }

    return $this->render('users/users_full_name.html.twig', [
      'form'          => $form->createView(),
    ]);
  }
}
