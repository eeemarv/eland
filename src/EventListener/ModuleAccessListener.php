<?php declare(strict_types=1);

namespace App\EventListener;

use App\Service\ConfigService;
use App\Service\PageParamsService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(
  event: KernelEvents::REQUEST, 
  priority: 30,
)]
class ModuleAccessListener
{
  public function __construct(
    private readonly ConfigService $config_service,
    private readonly PageParamsService $pp,
  )
  {
  }

  public function __invoke(
    RequestEvent $event,
  ): void
  {
    error_log('POEHA');

    if (!$event->isMainRequest()) 
    {
      return;
    }

    $request = $event->getRequest();
    $attributes = $request->attributes;

    if (!$attributes->has('schema'))
    {
      return;
    }

    $module = $attributes->get('_module');

    if (!isset($module))
    {
      throw new NotFoundHttpException(
        sprintf('Module missing for schema %s and route %s',
          $this->pp->schema(), $this->pp->route(),
        )
      );
    }

    $module_config_ids = $attributes->get('_module_config_ids', []);
    $schema = $this->pp->schema_o();

    foreach ($module_config_ids as $config_id => $name)
    {
      if (!$this->config_service->get_bool(
          config_id: $config_id,
          schema: $schema,
        )) 
      {
        $chain = implode(', ', $module_config_ids);
        throw new NotFoundHttpException(
          sprintf('Module "%s" disabled from module chain %s', $name, $chain),
        );
      }      
    }
  }
}
