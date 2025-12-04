<?php declare(strict_types=1);

namespace App\Controller\Cms;

use App\Command\Cms\CmsEditCommand;
use App\Form\Type\Cms\CmsEditType;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\StaticContentService;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Target;

#[AsController]
class CmsEditController extends AbstractController
{
  const EMPTY_ARTEFACTS = [
    '<p><br></p>'   => true,
    '<br>'          => true,
    '<br/>'         => true,
    '<p></p>'       => true,
  ];

  #[Route(
    '/{system}/{role_short}/cms-edit',
    name: 'cms_edit',
    methods: ['POST'],
    requirements: [
      'system'        => '%assert.system%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'cms',
    ],
  )]

  public function __invoke(
    Request $request,
    StaticContentService $static_content_service,
    #[Target(name: 'cms_sanitizer')]
    HtmlSanitizerInterface $html_sanitizer,
    SessionUserService $su,
    PageParamsService $pp
  ):Response
  {
    $command = new CmsEditCommand();
    $form = $this->createForm(CmsEditType::class, $command);
    $form->handleRequest($request);

    if (!$form->isSubmitted()
      || !$form->isValid())
    {
      throw new BadRequestException('Invalid form');
    }

    $command = $form->getData();
    $content_ary = json_decode($command->content, true);
    $all_params = json_decode($command->all_params, true);
    $route = $command->route;
    $route_enabled = $command->route_en === '1';
    $role = $command->role;
    $role_enabled = $command->role_en === '1';

    $sel_route = $route_enabled ? $route : '';
    $sel_role = $role_enabled ? $role : '';

    $count_updated = 0;

    foreach($content_ary as $block => $content)
    {
      $no_space_content = trim(preg_replace('/\s+/', '', $content));
      $set = '';

      error_log('block ' . $block . ' -- ' . $content);

      if (!isset(self::EMPTY_ARTEFACTS[$no_space_content]))
      {
        error_log('sanitize');
        $set =  $html_sanitizer->sanitize($content);
      }

      error_log('SET block ' . $block . ' -- ' . $set);

      $get = $static_content_service->get($sel_role, $sel_route, $block, $pp->schema());
      if($get !== $set)
      {
        $static_content_service->set($sel_role, $sel_route, $block, $set, $su, $pp->schema());
        $count_updated++;
      }
    }

    $this->addFlash(
      type: 'success',
      message: [
        'key' => 'cms_edit.flash.success',
        'params'  => [
          'count' => $count_updated,
        ],
      ],
    );

    return $this->redirectToRoute($route, $all_params);
  }
}
