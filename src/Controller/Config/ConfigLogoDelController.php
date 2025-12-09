<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Form\Type\Del\DelType;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ConfigLogoDelController extends AbstractController
{
  #[Route(
    '/{system}/{role_short}/logo/del',
    name: 'config_logo_del',
    methods: ['GET', 'POST'],
    requirements: [
      'system'        => '%assert.system%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'config',
    ],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    $logo = $config_service->get_str(
      config_id: 'system.logo',
      schema: $pp->schema_o(),
    );

    if (!$logo)
    {
      throw new ConflictHttpException('No logo is configured for this system.');
    }

    $form = $this->createForm(DelType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $changed = $config_service->set_str(
        config_id: 'system.logo',
        value: '',
        user_id: $su->id() ?: null,
        schema: $pp->schema_o(),
      );

      if ($changed)
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'config_logo_del.flash.change',
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

      return $this->redirectToRoute('config_logo', $pp->ary());
    }

    return $this->render('config/config_logo_del.html.twig', [
      'form'          => $form->createView(),
    ]);
  }
}
