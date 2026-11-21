<?php declare(strict_types=1);

namespace App\Controller\Config;

use App\Command\Config\ConfigNotificationsCommand;
use App\Form\Type\Config\ConfigNotificationsType;
use App\Routing\AccessLevel;
use App\Routing\AppModule;
use App\Routing\SchemaRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\FeaturesService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

#[AsController]
class ConfigNotificationsController extends AbstractController
{
  #[SchemaRoute(
    access_level: AccessLevel::Admin,
    module: AppModule::Config,
    path: '/config/notifications',
    name: 'config_notifications',
    methods: ['GET', 'POST'],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    FeaturesService $features_service,
    PageParamsService $pp,
    SessionUserService $su,
    #[Autowire(service: 'cache.messenger.restart_workers_signal')]
    CacheItemPoolInterface $cache_rws,
  ):Response
  {
    $command = new ConfigNotificationsCommand();
    $features_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );
    $config_service->load_command(
      command: $command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: ConfigNotificationsType::class, 
      data: $command,
    );
    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {

      $changed_config = $config_service->store_command(
        command: $command,
        route: $pp->route(),
        user_id: $su->id() ?: null,
        schema: $pp->schema_o(),
      );

      $changed_features = $features_service->store_command(
        command: $command,
        route: $pp->route(),
        user_id: $su->id() ?: null,
        schema: $pp->schema_o(),
      );

      if ($changed_config || $changed_features)
      {
        // Signal all Messenger workers to gracefully restart
        $cache_item = $cache_rws->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        $cache_item->set(microtime(true));
        $cache_rws->save($cache_item);
        error_log('Restart workers signal sent');

        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'config_notifications.flash.change',
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

      return $this->redirectToRoute(
        route: 'config_notifications', 
        parameters: $pp->ary(),
      );
    }

    return $this->render('config/config_notifications.html.twig', [
      'form'  => $form->createView(),
    ]);
  }
}
