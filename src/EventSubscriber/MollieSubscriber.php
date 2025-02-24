<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Cache\ConfigCache;
use App\Cache\UserInvalidateCache;
use App\Form\Type\Mollie\MollieCheckoutType;
use App\Render\LinkRender;
use App\Repository\MollieRepository;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

class MollieSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected PageParamsService $pp,
        protected SessionUserService $su,
        protected UserInvalidateCache $user_invalidate_cache,
        protected LinkRender $link_render,
        protected ConfigCache $config_cache,
        protected FormFactoryInterface $form_factory,
        protected Environment $twig,
        protected MollieRepository $mollie_repository
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

        if (!($this->config_cache->get_bool('mollie.enabled', $this->pp->schema())))
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

        $payments = $this->mollie_repository->get_open_payments_for_user($this->su->id(), $this->pp->schema());

        if (!$payments)
        {
            error_log('User sync no payments in subscriber. Clear cache ++');
            $this->user_invalidate_cache->user($this->su->id(), $this->pp->schema());
            return;
        }

        $mollie_checkout_ary =[];

        foreach ($payments as $payment)
        {
            $description = $this->su->code() . ' ' . $payment['description'];

            $form = $this->form_factory->create(MollieCheckoutType::class, [], [
                'action' => $this->link_render->context_path('mollie_checkout',
                    ['system' => $this->pp->system()],
                    ['token' => $payment['token']]),
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
