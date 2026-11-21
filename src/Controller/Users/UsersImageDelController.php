<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Render\LinkRender;
use App\Repository\UserRepository;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class UsersImageDelController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Users,
    is_self: false,
    path: '/users/{id}/image/del',
    name: 'users_image_del',
    methods: ['GET', 'POST'],
  )]

  #[SchemaRoute(
    access_level: AccessLevel::User,
    module: AppModule::Users,
    is_self: true,
    path: '/users/self/image/del',
    name: 'users_image_del_self',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    bool $_is_self,
    LinkRender $link_render,
    UserRepository $user_repository,
    PageParamsService $pp,
    SessionUserService $su,
    string $env_s3_url,
    int|null $id = null,
  ):Response
  {
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

    $file = $user['image_file'];

    if ($file == '' || !$file)
    {
      throw $this->createNotFoundException('No image file found for user with id ' . $id);
    }

    if ($request->isMethod('POST'))
    {
      $user_repository->del_image_file(
        id: $id,
        schema: $pp->schema_o(),
      );

      $this->addFlash('success', 'Profielfoto/afbeelding verwijderd.');

      return $this->redirectToRoute(
        route: 'users_show',
        parameters: [
          ...$pp->ary(),
          'id' => $id,
        ],
      );
    }

    $out = '<div class="row">';
    $out .= '<div class="col-xs-6">';
    $out .= '<div class="thumbnail">';
    $out .= '<img src="';
    $out .= $env_s3_url . $file;
    $out .= '" class="img-rounded">';
    $out .= '</div>';
    $out .= '</div>';

    $out .= '</div>';

    $out .= '<form method="post">';

    $out .= '<div class="panel panel-info">';
    $out .= '<div class="panel-heading">';

    $out .= $link_render->btn_cancel('users_show', $pp->ary(), ['id' => $id]);

    $out .= '&nbsp;';
    $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger">';

    $out .= '</form>';

    $out .= '</div>';
    $out .= '</div>';

    return $this->render('users/users_image_del.html.twig', [
      'content'   => $out,
      'id'        => $id,
      'is_self'   => $_is_self,
      'user'      => $user,
    ]);
  }
}
