<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Form\Type\Mollie\MollieCheckoutType;
use App\Repository\MollieRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use Mollie\Api\Http\Data\Money;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Mollie\Api\MollieApiClient;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

#[AsController]
class MollieCheckoutController extends AbstractController
{
  #[Route(
    '/{schema}/mollie/checkout/{checkout_token}',
    name: 'mollie_checkout',
    methods: ['GET', 'POST'],
    priority: 30,
    requirements: [
      'checkout_token'   => '%uuid_base58%',
      'schema'           => '%assert.schema%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'mollie',
    ],
  )]

  public function __invoke(
    Request $request,
    string $checkout_token,
    ConfigService $config_service,
    UrlGeneratorInterface $url_generator,
    FormFactoryInterface $form_factory,
    MollieRepository $mollie_repository,
    PageParamsService $pp
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'mollie.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Mollie submodule (users) not enabled.');
    }

    $uuid_checkout_token = Uuid::fromBase58($checkout_token);

    $mollie_payment = $mollie_repository->get_payment(
      checkout_token: $uuid_checkout_token,
      schema: $pp->schema_o(),
    );

    if (!$mollie_payment)
    {
      throw $this->createNotFoundException('Payment request not found.');
    }

    $mollie_apikey = $config_service->get_str(
      config_id: 'mollie.apikey',
      schema: $pp->schema_o(),
    );

    if (!($mollie_payment['is_paid'] || $mollie_payment['is_canceled']))
    {
      if (!$mollie_apikey ||
      !(str_starts_with($mollie_apikey, 'test_')
      || str_starts_with($mollie_apikey, 'live_')))
      {
        throw $this->createAccessDeniedException('Configuratie-fout (Geen Mollie apikey). Contacteer de administratie.');
      }
      else if (!str_starts_with($mollie_apikey, 'live_'))
      {
        if ($request->isMethod('GET'))
        {
          $this->addFlash(
            type: 'warning',
            message: [
              'key' => 'mollie_checkout.flash.test_modus',
            ],
          );
        }
      }
    }

    $description = $mollie_payment['code'] . ' ' . $mollie_payment['description'];

    $form = $form_factory->create(
      type: MollieCheckoutType::class,
    );

    $form->handleRequest($request);

    if (!($mollie_payment['is_paid'] || $mollie_payment['is_canceled'])
      && $form->isSubmitted() && $form->isValid())
    {
      $mollie = new MollieApiClient();
      $mollie->setApiKey($mollie_apikey);

      $redirect_url = $url_generator->generate('mollie_checkout', [
        ...$pp->ary(),
        'checkout_token' => $checkout_token,
      ], UrlGeneratorInterface::ABSOLUTE_URL);

      $cancel_url = $url_generator->generate('mollie_checkout', [
        ...$pp->ary(),
        'checkout_token' => $checkout_token,
        'cancel'  => true,
      ], UrlGeneratorInterface::ABSOLUTE_URL);

      $webhook_url = $url_generator->generate('mollie_webhook', [
        'schema'  => $pp->schema(),
      ], UrlGeneratorInterface::ABSOLUTE_URL);

      $payment_request = new CreatePaymentRequest(
        description: $description,
        amount: Money::euro($mollie_payment['amount']),
        redirectUrl: $redirect_url,
        cancelUrl: $cancel_url,
        webhookUrl: $webhook_url,
        metadata: [
          'checkout_token'  => $checkout_token,
        ],
        locale: 'nl_BE',
      );

      $payment = $mollie->send($payment_request);

      $mollie_repository->update_mollie_payment_id(
        checkout_token: $uuid_checkout_token,
        mollie_payment_id: $payment->id,
        schema: $pp->schema_o(),
      );

      return $this->redirect($payment->getCheckoutUrl(), 303);
    }

    return $this->render('mollie/mollie_checkout.html.twig', [
      'form'          => $form->createView(),
      'from_user_id'  => $mollie_payment['user_id'],
      'description'   => $description,
      'amount'        => strtr($mollie_payment['amount'], '.', ',') . ' EUR',
      'is_paid'       => $mollie_payment['is_paid'],
      'is_canceled'   => $mollie_payment['is_canceled'],
    ]);
  }
}
