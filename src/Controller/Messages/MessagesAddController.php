<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Command\Messages\MessagesAddCommand;
use App\Form\Post\News\MessagesType;
use App\HtmlProcess\HtmlPurifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Render\SelectRender;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\S3Service;
use App\Service\SessionUserService;
use App\Service\TypeaheadService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;

class MessagesAddController extends AbstractController
{
    public function __invoke(
        Request $request,
        Db $db,
        LoggerInterface $logger,
        AlertService $alert_service,
        AssetsService $assets_service,
        ConfigService $config_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        SelectRender $select_render,
        LinkRender $link_render,
        MenuService $menu_service,
        TypeaheadService $typeahead_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        HtmlPurifier $html_purifier,
        S3Service $s3_service,
        string $env_s3_url
    ):Response
    {
        $messages_add_command = new MessagesAddCommand();

        $form = $this->createForm(MessagesType::class,
                $messages_add_command)
            ->handleRequest($request);

        if ($form->isSubmitted()
            && $form->isValid())
        {
            $messages_add_command = $form->getData();

            $user_id = $messages_add_command->user_id;
            $user_id = $su->id();

            $message = [
                'is_offer'      => $messages_add_command->offer_want === 'offer',
                'is_want'       => $messages_add_command->offer_want === 'want',
                'subject'       => $messages_add_command->subject,
                'content'       => $messages_add_command->content,
                'category_id'   => $messages_add_command->category_id,
                'expires_at'    => $messages_add_command->validity_days, // something
                'amount'        => $messages_add_command->amount,
                'units'         => $messages_add_command->units,
                'access'        => $messages_add_command->access,
                'user_id'       => $user_id,
            ];

            $id = $message_repository->insert($message, $pp->schema());

            if ($su->is_owner($user_id))
            {
                $alert_service->success('message_add.success.personal');
            }
            else
            {
                $alert_service->success('message_add.success.admin');
            }

            $link_render->redirect('news_show', $pp->ary(),
                ['id' => $id]);
        }






        $content = MessagesEditController::messages_form(
            $request,
            0,
            'add',
            $db,
            $logger,
            $alert_service,
            $assets_service,
            $config_service,
            $form_token_service,
            $heading_render,
            $intersystems_service,
            $item_access_service,
            $select_render,
            $link_render,
            $menu_service,
            $typeahead_service,
            $pp,
            $su,
            $vr,
            $user_cache_service,
            $html_purifier,
            $s3_service,
            $env_s3_url
        );

        return $this->render('messages/messages_add.html.twig', [
            'content'   => $content,
            'schema'    => $pp->schema(),
        ]);
    }
}
