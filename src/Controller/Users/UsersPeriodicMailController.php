<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cnst\ConfigCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class UsersPeriodicMailController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/periodic-mail',
        name: 'users_periodic_mail',
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
        AssetsService $assets_service,
        FormTokenService $form_token_service,
        MenuService $menu_service,
        LinkRender $link_render,
        ConfigService $config_service,
        PageParamsService $pp
    ):Response
    {
        if (!$config_service->get_bool('periodic_mail.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Periodic mail module not enabled');
        }

        $mollie_enabled = $config_service->get_bool('mollie.enabled', $pp->schema());
        $messages_enabled = $config_service->get_bool('messages.enabled', $pp->schema());
        $transactions_enabled = $config_service->get_bool('transactions.enabled', $pp->schema());
        $news_enabled = $config_service->get_bool('news.enabled', $pp->schema());
        $docs_enabled = $config_service->get_bool('docs.enabled', $pp->schema());
        $forum_enabled = $config_service->get_bool('forum.enabled', $pp->schema());

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

        if (!$config_service->get_intersystem_en($pp->schema()))
        {
            unset($block_ary['intersystem']);
        }

        $errors = [];
        $form_data = [];

        $layout_ary = $config_service->get_ary('periodic_mail.user.layout', $pp->schema());

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
                $read_select = $config_service->get_str('periodic_mail.user.render.' . $block . '.select', $pp->schema());

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

        $form_data['days'] = $config_service->get_int('periodic_mail.days', $pp->schema());
        $form_data['block_layout'] = json_encode($block_layout);
        $form_data['block_select_options'] = json_encode($block_select_options);

        $builder = $this->createFormBuilder($form_data);
        $builder->add('days', IntegerType::class);
        $builder->add('block_layout', HiddenType::class);
        $builder->add('block_select_options', HiddenType::class);
        $builder->add('submit', SubmitType::class);
        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($request->isMethod('POST'))
        {
            if ($token_error = $form_token_service->get_error())
            {
                $errors[] = $token_error;
            }

            if (count($errors))
            {
                $alert_service->error($errors);
            }
        }

        if ($form->isSubmitted()
            && $form->isValid()
            && !count($errors))
        {
            $form_data = $form->getData();
            $days = $form_data['days'];

            $posted_block_layout = json_decode($form_data['block_layout'], true);
            $posted_block_select_options = json_decode($form_data['block_select_options'], true);

            $config_service->set_int('periodic_mail.days', $days, $pp->schema());

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

                $config_service->set_str('periodic_mail.user.render.' . $b . '.select', $select, $pp->schema());
            }
            $config_service->set_ary('periodic_mail.user.layout', $block_layout, $pp->schema());

            $alert_service->success('Periodieke overzichts e-mail aangepast');
            $link_render->redirect('users_periodic_mail', $pp->ary(), []);
        }

        $assets_service->add([
            'sortable',
            'users_periodic_mail.js',
        ]);

        $menu_service->set('users_periodic_mail');

        return $this->render('users/users_periodic_mail.html.twig', [
            'form'                  => $form->createView(),
            'form_token'            => $form_token_service->get(),
            'block_layout'          => $block_layout,
            'block_inactive_layout' => $block_inactive_layout,
            'block_select_options'  => $block_select_options,
            'block_ary'             => $block_ary,
            'schema'                => $pp->schema(),
        ]);
    }
}
