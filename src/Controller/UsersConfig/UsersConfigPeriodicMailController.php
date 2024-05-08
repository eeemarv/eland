<?php declare(strict_types=1);

namespace App\Controller\UsersConfig;

use App\Cache\ConfigCache;
use App\Cnst\ConfigCnst;
use App\Command\UsersConfig\UsersConfigPeriodicMailCommand;
use App\Form\Type\UsersConfig\UsersConfigPeriodicMailType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AlertService;
use App\Service\PageParamsService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class UsersConfigPeriodicMailController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/config/periodic-mail',
        name: 'users_config_periodic_mail',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        AlertService $alert_service,
        ConfigCache $config_cache,
        PageParamsService $pp
    ):Response
    {
        if (!$config_cache->get_bool('periodic_mail.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Periodic mail module not enabled');
        }

        $mollie_enabled = $config_cache->get_bool('mollie.enabled', $pp->schema());
        $messages_enabled = $config_cache->get_bool('messages.enabled', $pp->schema());
        $transactions_enabled = $config_cache->get_bool('transactions.enabled', $pp->schema());
        $news_enabled = $config_cache->get_bool('news.enabled', $pp->schema());
        $docs_enabled = $config_cache->get_bool('docs.enabled', $pp->schema());
        $forum_enabled = $config_cache->get_bool('forum.enabled', $pp->schema());
		$new_users_enabled = $config_cache->get_bool('users.new.enabled', $pp->schema());
		$leaving_users_enabled = $config_cache->get_bool('users.leaving.enabled', $pp->schema());

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

        if (!$config_cache->get_intersystem_en($pp->schema()))
        {
            unset($block_ary['intersystem']);
        }

        $layout_ary = $config_cache->get_ary('periodic_mail.user.layout', $pp->schema());

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
                $read_select = $config_cache->get_str('periodic_mail.user.render.' . $block . '.select', $pp->schema());

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

        $command = new UsersConfigPeriodicMailCommand();

        $command->days = $config_cache->get_int('periodic_mail.days', $pp->schema());
        $command->block_layout = json_encode($block_layout);
        $command->block_select_options = json_encode($block_select_options);

        $form = $this->createForm(UsersConfigPeriodicMailType::class,
            $command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $command = $form->getData();

            $days = $command->days;
            $posted_block_layout = json_decode($command->block_layout, true);
            $posted_block_select_options = json_decode($command->block_select_options, true);

            $changed = false;

            if ($config_cache->set_int('periodic_mail.days', $days, $pp->schema()))
            {
                $changed = true;
            }

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

                if ($config_cache->set_str('periodic_mail.user.render.' . $b . '.select', $select, $pp->schema()))
                {
                    $changed = true;
                }
            }

            if ($config_cache->set_ary('periodic_mail.user.layout', $block_layout, $pp->schema()))
            {
                $changed = true;
            }

            if ($changed)
            {
                $alert_service->success('Configuratie periodieke overzichts e-mail aangepast');
            }
            else
            {
                $alert_service->warning('Configuratie periodieke overzichts e-mail niet gewijzigd');
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
