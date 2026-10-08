<?php declare(strict_types=1);

namespace App\Controller\Transactions;

use App\Command\Transactions\TransactionsMassOneToManyCommand;
use App\Email\TransactionsMass\OneToMany\EmailTransactionsMassOneToManyMessage;
use App\Email\TransactionsMass\OneToManyCopy\EmailTransactionsMassOneToManyCopyMessage;
use App\Form\Type\Filter\QTextSearchFilterType;
use App\Form\Type\Transactions\TransactionsMassOneToManyType;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[AsController]
class TransactionsMassOneToManyController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/transactions/one-to-many/{status}',
    name: 'transactions_mass_one_to_many',
    methods: ['GET', 'POST'],
    requirements: [
      'status' => '%assert.account.status2%',
      'schema' => '%assert.schema%',
      'role_short' => '%assert.role_short.admin%',
    ],
    defaults: [
      'status' => 'active',
      'module' => 'transactions',
      'sub_module' => 'autominlimit',
    ],
  )]

  public function __invoke(
    Request $request,
    string $status,
    UserRepository $user_repository,
    TransactionRepository $transaction_repository,
    PageParamsService $pp,
    SessionUserService $su,
    ConfigService $config_service,
    MessageBusInterface $bus,
  ): Response {
    if (!$config_service->get_bool(
        config_id: 'transactions.enabled',
        schema: $pp->schema_o(),
      )
    )
    {
      throw $this->createNotFoundException(
        'Transactions module not enabled.'
      );
    }

    if (!$config_service->get_bool(
      config_id: 'transactions.mass.enabled',
      schema: $pp->schema_o(),
      )
    )
    {
      throw $this->createNotFoundException(
        'Mass transactions sub module not enabled.'
      );
    }

    if ($status === 'intersystem')
    {
      throw $this->createNotFoundException(
        'Intersystem status not supported'
      );
    }

    $autominlimit_percentage = $config_service->get_int(
      config_id: 'accounts.limits.auto_min.percentage',
      schema: $pp->schema_o(),
    );

    $autominlimit_enabled = $config_service->get_bool(
      config_id: 'accounts.limits.auto_min.enabled',
      schema: $pp->schema_o(),
    );

    $limits_enabled = $config_service->get_bool(
      config_id: 'accounts.limits.enabled',
      schema: $pp->schema_o(),
    );

    $global_min_limit = $config_service->get_int(
      config_id: 'accounts.limits.global.min',
      schema: $pp->schema_o(),
    );

    $currency = $config_service->get_str(
      config_id: 'transactions.currency.name',
      schema: $pp->schema_o(),
    );

    $filter_form = $this->createForm(QTextSearchFilterType::class);
    $filter_form->handleRequest($request);

    $users_wis = $user_repository->get_all_by_status(
      status: $status,
      schema: $pp->schema_o(),
    );

    $users = array_filter($users_wis, fn($u) => empty($u['remote_email']) && empty($u['remote_schema']));

    $command = new TransactionsMassOneToManyCommand();
    $command->amounts = array_fill_keys(array_keys($users), null);

    $form = $this->createForm(
      type: TransactionsMassOneToManyType::class,
      data: $command,
    );
    $form->handleRequest($request);

    if (
      $form->isSubmitted()
      && $form->isValid()
    )
    {
      $amounts = array_filter($command->amounts, fn($v) => isset($v));
      $description = $command->description;
      $service_stuff = $command->service_stuff;
      $from_account_id = $command->from_account_id;
      $email_notify_en = $command->email_notify_en;
      $email_copy_en = $command->email_copy_en;

      $autominlimit_percentage_post = $limits_enabled
        && $autominlimit_enabled
        ? $autominlimit_percentage : null;
      $global_min_limit_post = $limits_enabled
        && $autominlimit_enabled
        ? $global_min_limit : null;

      $bulk_id = Uuid::v7();

      $transaction_repository->insert_mass_one_to_many(
        from_account_id: $from_account_id,
        to_account_ids_amounts: $amounts,
        description: $description,
        service_stuff: $service_stuff,
        created_by: $su->id() ?: null,
        autominlimit_percentage: $autominlimit_percentage_post,
        global_min_limit: $global_min_limit_post,
        bulk_id: $bulk_id,
        schema: $pp->schema_o(),
      );

      if ($email_notify_en)
      {
        $m_notify = new EmailTransactionsMassOneToManyMessage(
          bulk_id: $bulk_id,
          schema: $pp->schema_o(),
        );
        $bus->dispatch($m_notify);
      }

      if ($email_copy_en)
      {
        $m_copy = new EmailTransactionsMassOneToManyCopyMessage(
          bulk_id: $bulk_id,
          schema: $pp->schema_o(),
        );
        $bus->dispatch($m_copy);
      }

      $from_account_ary = $user_repository->get(
        id: $from_account_id,
        schema: $pp->schema_o(),
      );

      $from_account_str = $from_account_ary['code'] . ' ' . $from_account_ary['name'];

      $from_account_link = $this->generateUrl(
        route: 'transactions_mass_many_to_one',
        parameters: [
          'id'  => $from_account_id,
          ...$pp->ary(),
        ],
      );

      if (count($amounts)) {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'transactions_mass_one_to_many.flash.success',
            'params' => [
              'count' => count($amounts),
              'total_amount'  => array_sum($amounts),
              'currency'  => $currency,
              'from_account' => $from_account_str,
              'oa' => '<a href="' . $from_account_link . '">',
              'ca' => '</a>',
              'description' => $description,
              'service_stuff' => $service_stuff,
            ],
            'is_raw' => true,
          ],
        );

        foreach ($amounts as $uid => $amount)
        {
          $this->addFlash(
            type: 'success',
            message: [
              'user'  => $users[$uid],
              'user_info' => strtr((string) $amount, '.', ',') . ' ' . $currency,
            ],
          );
        }
      }
      else
      {
        $this->addFlash(
          type: 'warning',
          message: [
            'key' => 'flash.no_change',
          ]
        );
      }

      return $this->redirectToRoute(
        route: 'transactions',
        parameters: $pp->ary(),
      );
    }

    return $this->render('transactions/transactions_mass_one_to_many.html.twig', [
      'form' => $form->createView(),
      'filter_form' => $filter_form->createView(),
      'users' => $users,
      'status'  => $status,
    ]);
  }
}
