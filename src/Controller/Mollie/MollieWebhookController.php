<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Queue\MailQueue;
use App\Repository\MollieRepository;
use App\Service\ConfigService;
use App\Service\MailAddrUserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PageParamsService;
use Mollie\Api\MollieApiClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
class MollieWebhookController extends AbstractController
{
  #[Route(
    '/{system}/mollie/webhook',
    name: 'mollie_webhook',
    methods: ['POST'],
    priority: 30,
    requirements: [
      'system'        => '%assert.system%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'mollie',
    ],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    MessageBusInterface $bus,
    PageParamsService $pp,
    MailQueue $mail_queue,
    MailAddrUserService $mail_addr_user_service,
    MollieRepository $mollie_repository
  ):Response
  {
    if (!$config_service->get_bool('mollie.enabled', $pp->schema()))
    {
      $this->createNotFoundException('Mollie submodule (users) not enabled.');
    }

    $id = $request->request->get('id', '');

    $mollie_apikey = $config_service->get_str('mollie.apikey', $pp->schema());

    $mollie = new MollieApiClient();
    $mollie->setApiKey($mollie_apikey);

    $payment = $mollie->payments->get($id);
    $checkout_token = $payment->metadata->checkout_token;
    $uuid_checkout_token = Uuid::fromBase58($checkout_token);

    $mollie_payment = $mollie_repository->get_payment(
      checkout_token: $uuid_checkout_token,
      schema: $pp->schema_o(),
    );

    if (!$mollie_payment)
    {
      $this->createNotFoundException('Payment request not found');
    }

    if ($payment->isPaid())
    {
      $mollie_repository->set_paid(
        checkout_token: $uuid_checkout_token,
        mollie_status: $payment->status,
        schema: $pp->schema_o(),
      );

      $amount = strtr($mollie_payment['amount'], '.', ',');
      $description = $mollie_payment['code'] . ' ' . $mollie_payment['description'];

      $vars = [
        'amount'        => $amount,
        'description'   => $description,
        'user_id'       => $mollie_payment['user_id'],
      ];

      $mail_queue->queue([
        'schema'	=> $pp->schema(),
        'template'	=> 'mollie/is_paid',
        'vars'		=> $vars,
        'to'		=> $mail_addr_user_service->get($mollie_payment['user_id'], $pp->schema()),
      ], 8500);
    }

    return new Response('');
  }
}
