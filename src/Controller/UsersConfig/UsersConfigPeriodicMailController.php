<?php declare(strict_types=1);

namespace App\Controller\UsersConfig;

use App\Cnst\ConfigCnst;
use App\Command\UsersConfig\UsersConfigPeriodicMailCommand;
use App\Form\Type\UsersConfig\UsersConfigPeriodicMailType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\ConfigService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersConfigPeriodicMailController extends AbstractController
{
  #[Route(
    '/{schema}/{role_short}/users/config_periodic-mail',
    name: 'users_config_periodic_mail',
    methods: ['GET', 'POST'],
    requirements: [
      'schema'        => '%assert.schema%',
      'role_short'    => '%assert.role_short.admin%',
    ],
    defaults: [
      'module'        => 'users',
    ],
  )]

  public function __invoke(
    Request $request,
    ConfigService $config_service,
    PageParamsService $pp,
    SessionUserService $su,
  ):Response
  {
    if (!$config_service->get_bool(
      config_id: 'periodic_mail.enabled',
      schema: $pp->schema_o(),
    ))
    {
      throw $this->createNotFoundException('Periodic mail module not enabled');
    }

    $mollie_enabled = $config_service->get_bool(
      config_id: 'mollie.enabled',
      schema: $pp->schema_o(),
    );
    $messages_enabled = $config_service->get_bool(
      config_id: 'messages.enabled',
      schema: $pp->schema_o(),
    );
    $transactions_enabled = $config_service->get_bool(
      config_id: 'transactions.enabled',
      schema: $pp->schema_o(),
    );
    $news_enabled = $config_service->get_bool(
      config_id: 'news.enabled',
      schema: $pp->schema_o(),
    );
    $docs_enabled = $config_service->get_bool(
      config_id: 'docs.enabled',
      schema: $pp->schema_o(),
    );
    $forum_enabled = $config_service->get_bool(
      config_id: 'forum.enabled',
      schema: $pp->schema_o(),
    );
    $new_users_enabled = $config_service->get_bool(
      config_id: 'users.new.enabled',
      schema: $pp->schema_o(),
    );
    $leaving_users_enabled = $config_service->get_bool(
      config_id: 'users.leaving.enabled',
      schema: $pp->schema_o(),
    );

    $block_ary = ConfigCnst::BLOCK_ARY;

    if (!$mollie_enabled)
    {
      unset($block_ary['mollie']);
    }

    if (!$forum_enabled)
    {
      unset($block_ary['forum']);
    }

    if (!$transactions_enabled)
    {
      unset($block_ary['transactions']);
    }

    if (!$messages_enabled)
    {
      unset($block_ary['messages']);
      unset($block_ary['messages_self']);
      unset($block_ary['intersystem']);
    }

    if (!$news_enabled)
    {
      unset($block_ary['news']);
    }

    if (!$docs_enabled)
    {
      unset($block_ary['docs']);
    }

    if (!$new_users_enabled)
    {
      unset($block_ary['new_users']);
    }

    if (!$leaving_users_enabled)
    {
      unset($block_ary['leaving_users']);
    }

    if (!$config_service->get_intersystem_en(schema: $pp->schema_o()))
    {
      unset($block_ary['intersystem']);
    }

    $layout_ary = $config_service->get_ary(
       config_id: 'periodic_mail.user.layout',
       schema: $pp->schema_o(),
    );

    $block_select_options = [];
    $block_layout = [];
    $map_inactive_layout = $block_ary;

    foreach ($layout_ary as $block)
    {
      if (!$block)
      {
          continue;
      }

      if (!isset($block_ary[$block]))
      {
        continue;
      }

      $block_layout[] = $block;
      unset($map_inactive_layout[$block]);
    }

    $block_inactive_layout = array_keys($map_inactive_layout);

    foreach ($block_ary as $block => $block_options)
    {
      if (count($block_options) === 1)
      {
        $select = key($block_options);
      }
      else
      {
        $read_select = $config_service->get_str(
          config_id: 'periodic_mail.user.render.' . $block . '.select',
          schema: $pp->schema_o(),
        );

        if (isset($block_options[$read_select]))
        {
          $select = $read_select;
        }
        else
        {
          $select = 'recent';
        }
      }

      $block_select_options[$block] = $select;
    }

    $users_periodic_mail_command = new UsersConfigPeriodicMailCommand();

    $users_periodic_mail_command->days = $config_service->get_int(
      config_id: 'periodic_mail.days',
      schema: $pp->schema_o(),
    );
    $users_periodic_mail_command->user_new_default_enabled = $config_service->get_bool(
      config_id: 'periodic_mail.user.new.default.enabled',
      schema: $pp->schema_o(),
    );
    $users_periodic_mail_command->block_layout = json_encode($block_layout);
    $users_periodic_mail_command->block_select_options = json_encode($block_select_options);

    $config_service->load_command(
      command: $users_periodic_mail_command,
      schema: $pp->schema_o(),
    );

    $form = $this->createForm(
      type: UsersConfigPeriodicMailType::class,
      data: $users_periodic_mail_command);

    $form->handleRequest($request);

    if ($form->isSubmitted()
      && $form->isValid())
    {
      $users_periodic_mail_command = $form->getData();

      $days = $users_periodic_mail_command->days;
      $user_new_default_enabled = $users_periodic_mail_command->user_new_default_enabled;
      $posted_block_layout = json_decode($users_periodic_mail_command->block_layout, true);
      $posted_block_select_options = json_decode($users_periodic_mail_command->block_select_options, true);
      $user_id = $su->id() ?: null;
      $changed_ary = [];

      $changed_ary[] = $config_service->set_int(
        config_id: 'periodic_mail.days',
        value: $days,
        route: $pp->route(),
        user_id: $user_id,
        schema: $pp->schema_o(),
      );
      $changed_ary[] = $config_service->set_bool(
        config_id: 'periodic_mail.user.new.default.enabled',
        value: $user_new_default_enabled,
        route: $pp->route(),
        user_id: $user_id,
        schema: $pp->schema_o(),
      );

      $block_layout = [];
      foreach($posted_block_layout as $b)
      {
        if (!isset($block_ary[$b]))
        {
          continue;
        }

        $block_layout[] = $b;

        if (count($block_ary[$b]) < 2)
        {
          continue;
        }

        $select = 'recent';

        if (isset($posted_block_select_options[$b])
          && $posted_block_select_options[$b] === 'all')
        {
          $select = 'all';
        }

        $changed_ary[] = $config_service->set_str(
          config_id: 'periodic_mail.user.render.' . $b . '.select',
          value: $select,
          route: $pp->route(),
          user_id: $user_id,
          schema: $pp->schema_o(),
        );
      }
      $changed_ary[] = $config_service->set_ary(
        config_id: 'periodic_mail.user.layout',
        value: $block_layout,
        route: $pp->route(),
        user_id: $user_id,
        schema: $pp->schema_o(),
      );

      if (array_any($changed_ary, fn($el) => $el === true))
      {
        $this->addFlash(
          type: 'success',
          message: [
            'key' => 'users_config_periodic_mail.flash.change',
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

      return $this->redirectToRoute('users_config_periodic_mail', $pp->ary());
    }

    return $this->render('users_config/users_config_periodic_mail.html.twig', [
      'form'                  => $form->createView(),
      'block_layout'          => $block_layout,
      'block_inactive_layout' => $block_inactive_layout,
      'block_select_options'  => $block_select_options,
      'block_ary'             => $block_ary,
    ]);
  }
}
