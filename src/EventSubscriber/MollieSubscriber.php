<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Form\Type\Mollie\MollieCheckoutType;
use App\Repository\MollieRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

class MollieSubscriber implements EventSubscriberInterface
{
  public function __construct(
    private readonly UrlGeneratorInterface $url_generator,
    private readonly PageParamsService $pp,
    private readonly SessionUserService $su,
    private readonly UserCacheService $user_cache_service,
    private readonly ConfigService $config_service,
    private readonly FormFactoryInterface $form_factory,
    private readonly Environment $twig,
    private readonly MollieRepository $mollie_repository
  )
  {
  }

  public function onKernelController(ControllerEvent $event):void
  {
    $request = $event->getRequest();

    if (!$request->isMethod('GET'))
    {
      return;
    }

    if ($request->isXmlHttpRequest())
    {
      return;
    }

    if (!$request->attributes->has('system'))
    {
      return;
    }

    if (!$this->pp->system())
    {
      return;
    }

    if (!($this->pp->is_admin() || $this->pp->is_user()))
    {
      return;
    }

    if (!($this->config_service->get_bool(
      config_id: 'mollie.enabled',
      schema: $this->pp->schema_o(),
    )))
    {
      return;
    }

    if (!$this->su->has_open_mollie_payment())
    {
      return;
    }

    $route = $request->attributes->get('_route');

    if (str_starts_with($route, 'mollie_'))
    {
      return;
    }

    $payments = $this->mollie_repository->get_open_payments_for_user(
      user_id: $this->su->id(),
      schema: $this->pp->schema_o(),
    );

    if (!$payments)
    {
      error_log('User sync no payments in subscriber. Clear cache ++');
      $this->user_cache_service->clear($this->su->id(), $this->pp->schema());
      return;
    }

    $mollie_checkout_ary =[];

    foreach ($payments as $payment)
    {
      $description = $this->su->code() . ' ' . $payment['description'];
      $checkout_token = Uuid::fromRfc4122($payment['checkout_token']);

      $action = $this->url_generator->generate('mollie_checkout', [
        'system' => $this->pp->system(),
        'checkout_token' => $checkout_token->toBase58(),
      ]);

      $form = $this->form_factory->create(MollieCheckoutType::class, [], [
        'action' => $action,
      ]);

      $mollie_checkout_ary[] = [
        'form'          => $form->createView(),
        'from_user_id'  => $this->su->id(),
        'description'   => $description,
        'amount'        => strtr($payment['amount'], '.', ',') . ' EUR',
      ];
    }

    $this->twig->addGlobal('mollie_checkout_ary', $mollie_checkout_ary);
  }

  public static function getSubscribedEvents():array
  {
    return [
      KernelEvents::CONTROLLER => 'onKernelController',
    ];
  }
}
