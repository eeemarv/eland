<?php declare(strict_types=1);

namespace App\Controller\News;

use App\Command\News\NewsSortCommand;
use App\Form\Type\News\NewsSortType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class NewsSortController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/news/sort',
    name: 'news_sort',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'news',
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
      config_id: 'news.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createAccessDeniedException(
        'News module not enabled.'
      );
    }

    $command = new NewsSortCommand();

    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: NewsSortType::class,
      data: $command,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $changed = $config_service->store_command(
        command: $command,
        route: $pp->route(),
        user_id: $su->id() ?: null,
        schema: $pp->schema_o(),
      );

      if ($changed)
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'news_sort.flash.change',
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

      return $this->redirectToRoute('news_sort', $pp->ary());
    }

    return $this->render('news/news_sort.html.twig', [
      'form'          => $form->createView(),
    ]);
  }
}
