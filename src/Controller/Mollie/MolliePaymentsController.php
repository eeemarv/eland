<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Command\Mollie\MollieBulkCancelCommand;
use App\Command\Mollie\MollieBulkEmailCommand;
use App\Command\Mollie\MollieFilterCommand;
use App\Email\MollieBulk\Copy\EmailMollieBulkCopyMessage;
use App\Email\MollieBulk\Message\EmailMollieBulkMessageMessage;
use App\Form\Type\Mollie\MollieBulkCancelType;
use App\Form\Type\Mollie\MollieBulkEmailType;
use App\Form\Type\Mollie\MollieFilterType;
use App\Render\AccountRender;
use App\Repository\MollieRepository;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MolliePaymentsController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/mollie/payments',
    name: 'mollie_payments',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
      'sub_module'    => 'mollie',
    ],
  )]

  public function __invoke(
    Request $request,
    MollieRepository $mollie_repository,
    AccountRender $account_render,
    ConfigService $config_service,
    MessageBusInterface $bus,
    PageParamsService $pp,
    SessionUserService $su,
    UserCacheService $user_cache_service,
    #[Target(name: 'no_img_email_sanitizer')]
    HtmlSanitizerInterface $html_sanitizer,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'mollie.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw new NotFoundHttpException('Mollie submodule (users) not enabled.');
    }

    $no_apikey = false;
    $no_live_apikey = false;
    $mollie_apikey = $config_service->get_str(
      config_id: 'mollie.apikey',
      schema: $pp->schema_o(),
    );

    if (!$mollie_apikey ||
      !(str_starts_with($mollie_apikey, 'test_')
      || str_starts_with($mollie_apikey, 'live_')))
    {
      if ($request->isMethod('GET')){
        $this->addFlash('warning', [
          'key'     => 'mollie.flash.no_apikey',
          'is_raw'  => true,
          'params'  => [
            'ao'  => '<a href="' . $this->generateUrl('mollie_config', $pp->ary()) . '">',
            'ac'  => '</a>',
          ]
        ]);
      }

      $no_apikey = true;
    }
    else if (!str_starts_with($mollie_apikey, 'live_'))
    {
      if ($request->isMethod('GET')){
        $this->addFlash('warning', [
          'key'     => 'mollie.flash.no_live_apikey',
          'is_raw'  => true,
          'params'  => [
            'ao'  => '<a href="' . $this->generateUrl('mollie_config', $pp->ary()) . '">',
            'ac'  => '</a>',
          ]
        ]);
      }
      $no_live_apikey = true;
    }

    $filter_command = new MollieFilterCommand();

    $filter_form = $this->createForm(MollieFilterType::class, $filter_command);
    $filter_form->handleRequest($request);
    $filter_command = $filter_form->getData();

    $f_params = $request->query->all('f');
    $filter_form_error = isset($f_params['user']) && !isset($filter_command->user);

    $pag = $request->query->all('p');
    $sort = $request->query->all('s');

    $pag_params = [
      's'	=> [
        'order_by'	=> $sort['order_by'] ?? 'created_at',
        'asc'		=> $sort['asc'] ?? 0,
      ],
      'p'	=> [
        'start'		=> (int) ($pag['start'] ?? 0),
        'limit'		=> (int) ($pag['limit'] ?? 100),
      ],
    ];

    $sel = $request->request->all('sel');

    $bulk_email_command = new MollieBulkEmailCommand();
    $bulk_email_form = $this->createForm(MollieBulkEmailType::class, $bulk_email_command);
    $bulk_email_form->handleRequest($request);

    if ($bulk_email_form->isSubmitted()
      && $bulk_email_form->isValid()
      && $config_service->get_bool(
        config_id: 'mail.enabled',
        schema: $pp->schema_o(),
        )
      && !$su->is_master()
    )
    {
      $bulk_email_command = $bulk_email_form->getData();
      $selected = $bulk_email_command->selected;
      $subject = $bulk_email_command->subject;
      $content = $bulk_email_command->content;
      $sanitized_content = $html_sanitizer->sanitize($content);
      $copy = $bulk_email_command->copy;
      $select_ary = explode(',', $selected);
      $s_payment_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $s_payment_ids[] = (int) trim($sel_id);
      }
      $payment_ids_sent = [];
      $payment_ids_not_sent = [];

      $m_payments = $mollie_repository->get_payments_with_email_addresses(
        payment_ids: $s_payment_ids,
        schema: $pp->schema_o(),
      );

      $flash_sent_ary = [];
      $flash_not_sent_ary = [];

      foreach ($m_payments as $p)
      {
        $str = $account_render->link($p['user_id'], $pp->ary());
        $str .= ', ';
        $str .= strtr($p['amount'], '.', ',') . ' EUR, "';
        $str .= htmlspecialchars($p['description'], ENT_QUOTES);
        $str .= '"';
        if (count($p['email_addresses']))
        {
          $payment_ids_sent[] = $p['id'];
          $flash_sent_ary[] = $str;
        }
        else
        {
          $payment_ids_not_sent[] = $p['id'];
          $flash_not_sent_ary[] = $str;
        }
      }

      if (count($payment_ids_sent))
      {
        $mollie_repository->add_emails_sent(
          sanitized_content: $sanitized_content,
          subject: $subject,
          route: $request->attributes->get('_route'),
          payment_ids: $payment_ids_sent,
          created_by: $su->id(),
          schema: $pp->schema_o(),
        );

        $m_mollie = new EmailMollieBulkMessageMessage(
          sender_id: $su->id(),
          payment_ids: $payment_ids_sent,
          content: $content,
          subject: $subject,
          schema: $pp->schema_o(),
        );
        $bus->dispatch($m_mollie);
      }

      if ($copy)
      {
        $m_copy = new EmailMollieBulkCopyMessage(
          to_user_id: $su->id(),
          payment_ids_sent: $payment_ids_sent,
          payment_ids_not_sent: $payment_ids_not_sent,
          content: $content,
          subject: $subject,
          schema: $pp->schema_o(),
        );
        $bus->dispatch($m_copy);
      }

      $this->addFlash('success', [
        'key'   => 'flash.email.sent_to',
        'params'  => [
          'count' => count($flash_sent_ary),
        ],
      ]);

      foreach($flash_sent_ary as $msg)
      {
        $this->addFlash('success', $msg);
      }

      if (count($flash_not_sent_ary))
      {
        $this->addFlash('success', [
          'key'   => 'flash.email.not_sent_to',
          'params'  => [
            'count' => count($flash_not_sent_ary),
          ],
        ]);
      }

      foreach($flash_not_sent_ary as $msg)
      {
        $this->addFlash('success', $msg);
      }

      return $this->redirectToRoute('mollie_payments', $pp->ary());
    }

    $bulk_cancel_command = new MollieBulkCancelCommand();
    $bulk_cancel_form = $this->createForm(MollieBulkCancelType::class, $bulk_cancel_command);
    $bulk_cancel_form->handleRequest($request);

    if ($bulk_cancel_form->isSubmitted()
      && $bulk_cancel_form->isValid())
    {
      $bulk_cancel_command = $bulk_cancel_form->getData();
      $selected = $bulk_cancel_command->selected;
      $select_ary = explode(',', $selected);
      $cancel_payment_ids = [];
      foreach ($select_ary as $sel_id)
      {
        $cancel_payment_ids[] = (int) trim($sel_id);
      }
      $mollie_repository->cancel_payments(
        payment_ids: $cancel_payment_ids,
        canceled_by: $su->id(),
        schema: $pp->schema_o(),
      );
      $canceled_payments = $mollie_repository->get_canceled_payments(
        payment_ids: $cancel_payment_ids,
        schema: $pp->schema_o(),
      );
      $this->addFlash('success', [
        'key'   => 'mollie_payments.bulk.cancel.flash.success',
        'params'  => [
          'count' => count($canceled_payments),
        ],
      ]);
      foreach ($canceled_payments as $p)
      {
        $user_cache_service->clear((int) $p['user_id'], $pp->schema());

        $str = $account_render->link($p['user_id'], $pp->ary());
        $str .= ', ';
        $str .= strtr($p['amount'], '.', ',') . ' EUR, "';
        $str .= htmlspecialchars($p['description'], ENT_QUOTES);
        $str .= '"';
        $this->addFlash('success', $str);
      }

      return $this->redirectToRoute('mollie_payments', $pp->ary());
    }

    $ret = $mollie_repository->get_filtered_payments(
      filter_command: $filter_command,
      start: $pag_params['p']['start'],
      limit: $pag_params['p']['limit'],
      order_by: $pag_params['s']['order_by'],
      asc: $pag_params['s']['asc'] ? true : false,
      schema: $pp->schema_o(),
    );

    $count_ary = $ret['count_ary'];
    $payments = $ret['payments'];

    $filtered = isset($filter_command->q)
      || isset($filter_command->user)
      || isset($filter_command->status)
      || isset($filter_command->from_date)
      || isset($filter_command->to_date);

    $filter_collapse = !($filtered || $filter_form_error);

    return $this->render('mollie/mollie_payments.html.twig', [
      'sel'               => $sel,
      'row_count'         => $count_ary['rows'],
      'filtered'          => $filtered,
      'filter_collapse'   => $filter_collapse,
      'filter_form'       => $filter_form->createView(),
      'bulk_email_form'   => $bulk_email_form->createView(),
      'bulk_cancel_form'  => $bulk_cancel_form->createView(),
      'count_ary'         => $count_ary,
      'pag_params'        => $pag_params,
      'payments'          => $payments,
      'no_apikey'         => $no_apikey,
      'no_live_apikey'    => $no_live_apikey,
    ]);
  }
}
