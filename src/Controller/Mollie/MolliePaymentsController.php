<?php declare(strict_types=1);

namespace App\Controller\Mollie;

use App\Cnst\BulkCnst;
use App\Cnst\StatusCnst;
use App\Command\Mollie\MollieFilterCommand;
use App\Email\MollieBulk\Copy\EmailMollieBulkCopyMessage;
use App\Email\MollieBulk\Message\EmailMollieBulkMessageMessage;
use App\Form\Type\Mollie\MollieFilterType;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\MollieRepository;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsController]
class MolliePaymentsController extends AbstractController
{
  const STATUS_RENDER = [
    'open'      => [
      'label'         => 'open',
      'class'         => 'warning',
    ],
    'paid'     => [
      'label'         => 'betaald',
      'class'         => 'success',
    ],
    'canceled'  => [
      'label'     => 'geannuleerd',
      'class'     => 'default-2',
    ],
  ];

  #[Route(
    '/{system}/{role_short}/mollie/payments',
    name: 'mollie_payments',
    methods: ['GET', 'POST'],
    requirements: [
      'system'        => '%assert.system%',
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
    FormTokenService $form_token_service,
    ConfigService $config_service,
    ItemAccessService $item_access_service,
    UrlGeneratorInterface $url_generator,
    LinkRender $link_render,
    DateFormatService $date_format_service,
    MessageBusInterface $bus,
    PageParamsService $pp,
    SessionUserService $su,
    UserCacheService $user_cache_service,
    #[Autowire(service: 'html_sanitizer.sanitizer.admin_email_sanitizer')] HtmlSanitizerInterface $html_sanitizer,
    LoggerInterface $logger
  ):Response
  {
    if (!$config_service->get_bool('mollie.enabled', $pp->schema()))
    {
        throw new NotFoundHttpException('Mollie submodule (users) not enabled.');
    }

    $errors = [];

    $new_users_enabled = $config_service->get_bool('users.new.enabled', $pp->schema());
    $leaving_users_enabled = $config_service->get_bool('users.leaving.enabled', $pp->schema());

    $show_new_status = $new_users_enabled;

    if ($show_new_status)
    {
      $new_users_access = $config_service->get_str('users.new.access', $pp->schema());
      $show_new_status = $item_access_service->is_visible($new_users_access);
    }

    $show_leaving_status = $leaving_users_enabled;

    if ($show_leaving_status)
    {
      $leaving_users_access = $config_service->get_str('users.leaving.access', $pp->schema());
      $show_leaving_status = $item_access_service->is_visible($leaving_users_access);
    }

    $filter_command = new MollieFilterCommand();

    $filter_form = $this->createForm(MollieFilterType::class, $filter_command);
    $filter_form->handleRequest($request);
    $filter_command = $filter_form->getData();

    $f_params = $request->query->all('f');
    $filter_form_error = isset($f_params['user']) && !isset($filter_command->user);

    $pag = $request->query->all('p');
    $sort = $request->query->all('s');

    $selected = $request->request->all('sel');
    $bulk_mail_subject = $request->request->get('bulk_mail_subject', '');
    $bulk_mail_content = $request->request->get('bulk_mail_content', '');
    $bulk_mail_cc = $request->request->has('bulk_mail_cc');
    $bulk_mail_verify = $request->request->has('bulk_mail_verify');
    $bulk_mail_submit = $request->request->has('bulk_mail_submit');
    $bulk_cancel_verify = $request->request->has('bulk_cancel_verify');
    $bulk_cancel_submit = $request->request->has('bulk_cancel_submit');

    $mollie_apikey = $config_service->get_str('mollie.apikey', $pp->schema());

    if (!$mollie_apikey ||
        !(str_starts_with($mollie_apikey, 'test_')
        || str_starts_with($mollie_apikey, 'live_')))
    {
      if ($request->isMethod('GET'))
      {
        $this->addFlash('warning', 'Betalingen met Mollie zijn niet mogelijk want
          er is geen Mollie apikey ingesteld in de ' .
          $link_render->link('mollie_config', $pp->ary(), [], 'configuratie', []), false);
      }

      $no_mollie_apikey = true;
    }
    else if (!str_starts_with($mollie_apikey, 'live_'))
    {
      if ($request->isMethod('GET'))
      {
        $this->addFlash('warning', 'Er is geen <code>live_</code> Mollie apikey ingsteld in de ' .
          $link_render->link('mollie_config', $pp->ary(), [], 'configuratie', []) .
          '. Betalingen kunnen niet uitgevoerd worden!', false);
      }
    }

    $pag_params = [
      's'	=> [
        'order_by'	=> $sort['order_by'] ?? 'p.created_at',
        'asc'		=> $sort['asc'] ?? 0,
      ],
      'p'	=> [
        'start'		=> (int) ($pag['start'] ?? 0),
        'limit'		=> (int) ($pag['limit'] ?? 100),
      ],
    ];

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

    $asc_preset_ary = [
      'asc'	=> 0,
      'fa' 	=> 'sort',
    ];

    $tableheader_ary = [
      'p.amount' => [
        ...$asc_preset_ary,
        'lbl' => 'Bedrag (EUR)',
      ],
      'r.description' => [
        ...$asc_preset_ary,
        'lbl' 		=> 'Omschrijving',
      ],
      'u.code' => [
        ...$asc_preset_ary,
        'lbl' => 'Account',
      ],
      'status'	=> [
        ...$asc_preset_ary,
        'lbl' 	=> 'Status',
        'no_sort' => true,
      ],
      'p.created_at' => [
        ...$asc_preset_ary,
        'lbl' 		=> 'Tijdstip',
      ],
      'emails' => [
        ...$asc_preset_ary,
        'lbl' 		=> 'E-mails',
        'title'     => 'Aantal verzonden E-mails',
        'no_sort'   => true,
      ],
    ];

    $tableheader_ary[$pag_params['s']['order_by']]['asc']
      = $pag_params['s']['asc'] ? 0 : 1;
    $tableheader_ary[$pag_params['s']['order_by']]['fa']
      = $pag_params['s']['asc'] ? 'sort-asc' : 'sort-desc';

    if ($request->isMethod('POST'))
    {
      if ($error_token = $form_token_service->get_error())
      {
        $errors[] = $error_token;
      }

      if (!$selected)
      {
        $errors[] = 'Er is geen enkel betaalverzoek geselecteerd.';
      }
    }

    if ($request->isMethod('POST')
      && $bulk_cancel_submit
      && !count($errors))
    {
      if (!$bulk_cancel_verify)
      {
        $errors[] = 'Het nazichtsvakje is niet aangevinkt.';
      }

      $cancel_ary = [];
      $users_cancel_ary = [];

      foreach ($selected as $payment_id => $dummy)
      {
        $payment = $payments[$payment_id];

        if (!$payment['is_paid'] && !$payment['is_canceled'])
        {
          $cancel_ary[] = (int) $payment_id;
          $users_cancel_ary[$payment['user_id']] = true;
        }
      }

      if (!count($cancel_ary))
      {
        $errors[] = 'Geen betaalverzoeken geselecteerd die geannuleerd kunnen worden.';
      }

      if (!count($errors))
      {
        $mollie_repository->cancel_payments(
          payment_ids: $cancel_ary,
          canceled_by: $su->id(),
          schema: $pp->schema_o(),
        );

        foreach ($users_cancel_ary as $user_id => $dummy)
        {
          $user_cache_service->clear((int) $user_id, $pp->schema());
        }

        $success = [];

        switch(count($cancel_ary))
        {
          case 0:
            //
          break;
          case 1:
            $success[] = 'Betaalverzoek geannuleerd:';
          break;
          default:
            $success[] = 'Betaalverzoeken geannuleerd:';
          break;
        }

        $cancel_log_ary = $mollie_repository->get_payments_basic_info(
          payment_ids: $cancel_ary,
          schema: $pp->schema_o(),
        );

        foreach ($cancel_log_ary as $cl)
        {
          $cancel_str = $account_render->link($cl['user_id'], $pp->ary());
          $cancel_str .= ', ';
          $cancel_str .= strtr($cl['amount'], '.', ',') . ' EUR, "';
          $cancel_str .= htmlspecialchars($cl['description'], ENT_QUOTES);
          $cancel_str .= '"';
          $success[] = $cancel_str;
        }

        foreach ($success as $s_str)
        {
          $this->addFlash('success', $s_str);
        }

        return $this->redirectToRoute('mollie_payments', $pp->ary());
      }
    }

    if ($request->isMethod('POST')
      && $bulk_mail_submit
      && !count($errors))
    {
      $sent_to_ary = [];
      $not_sent_ary = [];

      if (!$config_service->get_bool('mail.enabled', $pp->schema()))
      {
        $errors[] = 'De E-mail functies zijn niet ingeschakeld. Zie instellingen.';
      }

      if (!$bulk_mail_verify)
      {
        $errors[] = 'Het nazichtsvakje is niet aangevinkt.';
      }

      if (isset($no_mollie_apikey))
      {
        $errors[] = 'Er is geen Mollie Apikey ingesteld.';
      }

      if ($su->is_master())
      {
        $errors[] = 'Het master account kan geen E-mails verzenden.';
      }

      if (!$bulk_mail_subject)
      {
        $errors[] = 'Vul een onderwerp in voor je E-mail.';
      }

      if (!$bulk_mail_content)
      {
        $errors[] = 'De E-mail is leeg.';
      }

      $payment_ids_sent = [];
      $payment_ids_not_sent = [];

      $m_payments = $mollie_repository->get_payments_with_email_ary(
        payment_ids: array_keys($selected),
        schema: $pp->schema_o(),
      );

      foreach ($m_payments as $payment_id => $p)
      {
        if (count($p['email_ary']))
        {
          $sent_to_ary[] = (int) $p['user_id'];
          $payment_ids_sent[] = $payment_id;
        }
        else
        {
          $not_sent_ary[] = (int) $p['user_id'];
          $payment_ids_not_sent[] = $payment_id;
        }
      }

      if (!count($sent_to_ary))
      {
        $errors[] = 'Geen enkele gebruiker van de geselecteerde betaalverzoeken met E-mail adres.';
      }

      if (!count($errors))
      {
        $sanitized_content = $html_sanitizer->sanitize($bulk_mail_content);

        $mollie_repository->add_emails_sent(
          sanitized_content: $sanitized_content,
          subject: $bulk_mail_subject,
          route: $request->attributes->get('_route'),
          payment_ids: $payment_ids_sent,
          created_by: $su->id(),
          schema: $pp->schema_o(),
        );

        $m_mollie = new EmailMollieBulkMessageMessage(
          sender_id: $su->id(),
          payment_ids: $payment_ids_sent,
          message: $bulk_mail_content,
          subject: $bulk_mail_subject,
          schema: $pp->schema_o(),
        );
        $bus->dispatch($m_mollie);

        $success = [];

        switch(count($sent_to_ary))
        {
          case 0:
            //
          break;
          case 1:
            $success[] = 'E-mail verzonden naar:';
          break;
          default:
            $success[] = 'E-mails verzonden naar:';
          break;
        }

        foreach($sent_to_ary as $user_id)
        {
          $success[] = $account_render->link($user_id, $pp->ary());
        }

        switch(count($not_sent_ary))
        {
          case 0:
          break;
          case 1:
              $success[] = 'Wegens ontbreken adres, geen E-mail verzonden naar:';
          break;
          default:
              $success[] = 'Wegens ontbreken adressen, geen E-mails verzonden naar:';
          break;
        }

        foreach($not_sent_ary as $user_id)
        {
          $success[] = $account_render->link($user_id, $pp->ary());
        }

        if ($bulk_mail_cc)
        {
          $m_copy = new EmailMollieBulkCopyMessage(
            to_user_id: $su->id(),
            payment_ids_sent: $payment_ids_sent,
            payment_ids_not_sent: $payment_ids_not_sent,
            message: $sanitized_content,
            subject: $bulk_mail_subject,
            schema: $pp->schema_o(),
          );
          $bus->dispatch($m_copy);
        }

        $mail_info = implode('<br />', $success);
        $mail_info .= '<hr /><br />';

        $logger->debug('mollie_payments mail:: ' .
          $mail_info . $bulk_mail_content,
          ['schema' => $pp->schema()]);

        foreach ($success as $s_str)
        {
          $this->addFlash('success', $s_str);
        }

        return $this->redirectToRoute('mollie_payments', $pp->ary());
      }
    }

    if (count($errors))
    {
      foreach ($errors as $error)
      {
        $this->addFlash('error', $error);
      }
    }

    $filtered = isset($filter_command->q)
      || isset($filter_command->user)
      || isset($filter_command->status)
      || isset($filter_command->from_date)
      || isset($filter_command->to_date);

    $filter_collapse = !($filtered || $filter_form_error);

    $out = '<div class="panel panel-info">';

    $out .= '<table class="table table-bordered table-striped ';
    $out .= 'table-hover panel-body footable csv" ';
    $out .= 'data-filter="#combined-filter" data-filter-minimum="1" ';
    $out .= 'data-sort="false">';
    $out .= '<thead>';

    $out .= '<tr>';

    foreach ($tableheader_ary as $key_order_by => $data)
    {
      $out .= '<th';
      $out .= isset($data['title']) ? ' title="' . $data['title'] . '"' : '';
      $out .= '>';

      if (isset($data['no_sort']))
      {
        $out .= $data['lbl'];
      }
      else
      {
        $h_params = $pag_params;

        $h_params['s'] = [
          'order_by' 	=> $key_order_by,
          'asc'		=> $data['asc'],
        ];

        $out .= $link_render->link_fa('mollie_payments', $pp->ary(),
          $h_params, $data['lbl'], [], $data['fa']);
      }

      $out .= '</th>';
    }

    $out .= '</tr>';

    $out .= '</thead>';
    $out .= '<tbody>';

    $new_user_treshold = $config_service->get_new_user_treshold($pp->schema());

    foreach($payments as $id => $payment)
    {
      $user_status = $payment['status'];

      if (isset($payment['adate'])
        && $new_users_enabled
        && $payment['status'] === 1
        && $new_user_treshold->getTimestamp() < strtotime($payment['adate'] . ' UTC'))
      {
        $user_status = 3;
      }

      if ($payment['status'] === 2
        && !$leaving_users_enabled
      )
      {
        $user_status = 1;
      }

      $out .= '<tr><td>';

      $out .= strtr(BulkCnst::TPL_CHECKBOX_ITEM, [
        '%id%'      => $id,
        '%attr%'    => isset($selected[$id]) ? ' checked' : '',
        '%label%'   => strtr($payment['amount'], '.', ','),
      ]);

      $out .= '</td><td>';

      $out .= $link_render->link('mollie_payments',
        $pp->ary(), [
          'request_id'    => $payment['request_id'],
          'f' => [
            'q' => $payment['description'],
          ],
        ],
        $payment['description'], []);

      $out .= '</td><td';

      if (isset(StatusCnst::CLASS_ARY[$user_status]))
      {
        $out .= ' class="';
        $out .= StatusCnst::CLASS_ARY[$user_status];
        $out .= '"';
      }

      $out .= '>';

      $out .= $account_render->link($payment['user_id'], $pp->ary());

      $out .= '</td><td>';

      $out .= '<span class="label label-';

      if ($payment['is_canceled'])
      {
        $out .= 'default">geannuleerd';
      }
      else if ($payment['is_paid'])
      {
        $out .= 'success">betaald';
      }
      else
      {
        $out .= 'warning">open';
      }

      $out .= '</span>';

      $out .= '</td><td>';

      $out .= $date_format_service->get($payment['created_at'], 'day', $pp->schema());

      $out .= '</td><td>';

      $td_emails = count($payment['emails_sent']);

      if (!count($payment['email']))
      {
        $td_emails .= '&nbsp;<span class="label label-danger" title="Er is geen ';
        $td_emails .= 'E-mail adres ingesteld voor de gebruiker.">';
        $td_emails .= '<i class="fa fa-exclamation-triangle"></i></span>';
      }

      $out .= $td_emails;
      $out .= '</td></tr>';
    }

    $out .= '</tbody>';
    $out .= '</table>';

    $out .= '</div>';

    $blk = BulkCnst::TPL_SELECT_BUTTONS;

    $blk .= '<h3>Bulk acties met geselecteerde betaalverzoeken</h3>';
    $blk .= '<div class="panel panel-info">';
    $blk .= '<div class="panel-heading">';

    $blk .= '<ul class="nav nav-tabs" role="tablist">';

    $blk .= '<li class="active">';
    $blk .= '<a href="#mail_tab" data-toggle="tab">Mail</a></li>';
    $blk .= '<li>';

    $blk .= '<a href="#cancel_tab" data-toggle="tab">';
    $blk .= 'Annuleren';
    $blk .= '</a>';
    $blk .= '</li>';
    $blk .= '</ul>';

    $blk .= '<div class="tab-content">';

    $blk .= '<div role="tabpanel" class="tab-pane active" id="mail_tab">';

    $blk .= '<form method="post">';

    $blk .= '<h3>E-Mail verzenden</h3>';

    $blk .= '<div class="form-group">';
    $blk .= '<input type="text" class="form-control" ';
    $blk .= 'id="bulk_mail_subject" name="bulk_mail_subject" ';
    $blk .= 'placeholder="Onderwerp" ';
    $blk .= 'value="';
    $blk .= $bulk_mail_subject;
    $blk .= '" required>';
    $blk .= '</div>';

    $blk .= '<div class="form-group">';
    $blk .= '<textarea name="bulk_mail_content" ';
    $blk .= 'class="form-control summernote" ';
    $blk .= 'id="bulk_mail_content" rows="8" ';
    $blk .= 'data-template-vars="';
    $blk .= implode(',', array_keys(BulkCnst::MOLLIE_TPL_VARS));
    $blk .= '" ';
    $blk .= 'required>';
    $blk .= $bulk_mail_content;
    $blk .= '</textarea>';
    $blk .= '<ul><li>Een betaalknop wordt toegevoegd boven je eigen bericht ';
    $blk .= 'bij openstaande betaalverzoeken.';
    $blk .= '</li>';
    $blk .= '<li>Bedrag en omschrijving van betaalverzoeken worden altijd ';
    $blk .= 'bovenaan weergegeven in de verzonden e-mails.</li></ul>';
    $blk .= '</div>';

    $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
      '%name%'    => 'bulk_mail_cc',
      '%label%'   => 'Stuur een kopie met verzendinfo naar mijzelf',
      '%attr%'    => $bulk_mail_cc ? ' checked' : '',
    ]);

    $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
      '%name%'    => 'bulk_mail_verify',
      '%label%'   => 'Ik heb alles nagekeken.',
      '%attr%'    => ' required',
    ]);

    $blk .= '<input type="submit" value="Verzend" name="bulk_mail_submit" ';
    $blk .= 'class="btn btn-info btn-lg">';

    $blk .= $form_token_service->get_hidden_input();
    $blk .= '</form>';

    $blk .= '</div>';

//--------------------------------------

    $blk .= '<div role="tabpanel" class="tab-pane" ';
    $blk .= 'id="cancel_tab">';

    $blk .= '<form method="post">';

    $blk .= '<h3>Betaalverzoek annuleren</h3>';

    $blk .= '<p>Annuleer geselecteerde ';
    $blk .= '<span class="label label-warning">open</span> ';
    $blk .= 'betaalverzoeken</p>';

    $blk .= strtr(BulkCnst::TPL_CHECKBOX, [
      '%name%'    => 'bulk_cancel_verify',
      '%label%'   => 'Ik heb alles nagekeken.',
      '%attr%'    => ' required',
    ]);

    $blk .= '<input type="submit" value="Annuleer" ';
    $blk .= 'name="bulk_cancel_submit" class="btn btn-primary btn-lg">';

    $blk .= $form_token_service->get_hidden_input();
    $blk .= '</form>';

    $blk .= '</div>';

//--------------------------------

    $blk .= '</div>';
    $blk .= '</div>';
    $blk .= '</div>';

    return $this->render('mollie/mollie_payments.html.twig', [
      'data_list_raw'     => $out,
      'bulk_actions_raw'  => $blk,
      'row_count'         => $count_ary['rows'],
      'filtered'          => $filtered,
      'filter_collapse'   => $filter_collapse,
      'filter_form'       => $filter_form->createView(),
      'count_ary'         => $count_ary,
    ]);
  }
}
