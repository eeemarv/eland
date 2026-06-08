<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Email\Mollie\Confirmation\EmailMollieConfirmationMessage;
use App\Repository\MollieRepository;
use App\Service\ConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use Mollie\Api\MollieApiClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
class MollieWebhookController extends AbstractController
{
  #[Route(
    '/{schema}/mollie/webhook',
    name: 'mollie_webhook',
    methods: ['POST'],
    priority: 30,
    requirements: [
      'schema'  => '%assert.schema%',
    ],
    defaults: [
      'module'  => 'users',
      'sub_module'  => 'mollie',
    ],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    MessageBusInterface $bus,
    PageParamsService $pp,
    MollieRepository $mollie_repository,
    LoggerInterface $logger,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'mollie.enabled',
      schema: $pp->schema_o(),
    ))
    {
      $logger->info('(webhook) Mollie submodule (users) not enabled.', [
        'schema' => $pp->schema(),
        'post_params' => $request->request->all(),
      ]);
      return new Response();
    }

    $signatures = $request->headers->all('X-Mollie-Signature');


    $id = $request->request->get('id');

    if (!isset($id))
    {
      $logger->error('(webhook) Mollie payment id missing.', [
        'schema' => $pp->schema(),
        'post_params' => $request->request->all(),
      ]);
      return new Response();
    }

    $mollie_apikey = $config_service->get_str(
      config_id: 'mollie.apikey',
      schema: $pp->schema_o(),
    );

    if (!(str_starts_with($mollie_apikey, 'live_')
      || str_starts_with($mollie_apikey, 'test_')))
    {
      $logger->error('(webhook) Mollie apikey not configured with live_ or test_', [
        'schema' => $pp->schema(),
        'post_params' => $request->request->all(),
      ]);
      return new Response();
    }

    $mollie = new MollieApiClient();
    $mollie->setApiKey($mollie_apikey);

    $payment = $mollie->payments->get($id);


    $checkout_token = $payment->metadata->checkout_token;

    if (!$checkout_token)
    {
      $logger->error('(webhook) Mollie payment checkout_token not found.', [
        'schema'  => $pp->schema(),
        'post_params' => $request->request->all(),
      ]);
      return new Response();
    }

    $uuid_checkout_token = Uuid::fromBase58($checkout_token);

    $mollie_payment = $mollie_repository->get_payment(
      checkout_token: $uuid_checkout_token,
      schema: $pp->schema_o(),
    );

    if (!$mollie_payment)
    {
      $logger->error('(webhook) Mollie payment with id ' . $id . ' not found.', [
        'schema'  => $pp->schema(),
        'post_params' => $request->request->all(),
      ]);
      return new Response();
    }

    if ($payment->isPaid())
    {
      $mollie_repository->set_paid(
        checkout_token: $uuid_checkout_token,
        mollie_status: $payment->status,
        schema: $pp->schema_o(),
      );

      $m_confirm = new EmailMollieConfirmationMessage(
        payment_id: $mollie_payment['id'],
        schema: $pp->schema_o(),
      );
      $bus->dispatch($m_confirm);
    }

    return new Response();
  }
}
