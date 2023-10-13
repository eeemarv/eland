<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Doctrine\DBAL\Connection as Db;
use App\Cnst\MessageTypeCnst;
use App\Command\Messages\MessagesMailContactCommand;
use App\Controller\Contacts\ContactsUserShowInlineController;
use App\Form\Type\MailContact\MailContactType;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\CategoryRepository;
use App\Repository\ContactRepository;
use App\Repository\MessageRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DistanceService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesShowController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}',
        name: 'messages_show',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.guest%',
        ],
        defaults: [
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        MessageRepository $message_repository,
        ContactRepository $contact_repository,
        CategoryRepository $category_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        ConfigService $config_service,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        DistanceService $distance_service,
        ContactsUserShowInlineController $contacts_user_show_inline_controller,
        string $env_map_access_token,
        string $env_map_tiles_url
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());

        $message = $message_repository->get($id, $pp->schema());

        $category = null;

        if ($category_enabled && isset($message['category_id']))
        {
            $category = $category_repository->get($message['category_id'], $pp->schema());
        }

        if ($message['access'] === 'user' && $pp->is_guest())
        {
            throw new AccessDeniedHttpException('No sufficient rights to access this message');
        }

        $user = $user_cache_service->get($message['user_id'], $pp->schema());

        if (!$user['is_active'])
        {
            throw new AccessDeniedHttpException('Deactivated user account with id ' . $message['user_id']);
        }

        /**
         * mail contact form
         */

        $mail_command = new MessagesMailContactCommand();

        $mail_form = $this->createForm(MailContactType::class, $mail_command, [
            'to_user_id'    => $message['user_id'],
        ]);

        $mail_form->handleRequest($request);

        if ($mail_form->isSubmitted()
            && $mail_form->isValid())
        {
            $mail_command = $mail_form->getData();

            $from_user = $user_cache_service->get($su->id(), $su->schema());
            $to_user = $user;

            if (!$pp->is_admin() && !$to_user['is_active'])
            {
                throw new AccessDeniedHttpException('You dan\'t have enough rights
                    to send a message to a non-active user.');
            }

            $vars = [
                'from_user'			=> $from_user,
                'from_schema'		=> $su->schema(),
                'is_same_system'	=> $su->is_system_self(),
                'to_user'			=> $to_user,
                'to_schema'			=> $pp->schema(),
                'msg_content'		=> $mail_command->message,
                'message'			=> $message,
            ];

            $mail_template = $su->is_system_self()
                ? 'message_msg/msg'
                : 'message_msg/msg_intersystem';

            $mail_queue->queue([
                'schema'	=> $pp->schema(),
                'to'		=> $mail_addr_user_service->get_active($to_user['id'], $pp->schema()),
                'reply_to'	=> $mail_addr_user_service->get_active($su->id(), $su->schema()),
                'template'	=> $mail_template,
                'vars'		=> $vars,
            ], 8500);

            if ($mail_command->cc)
            {
                $mail_template = $su->is_system_self()
                    ? 'message_msg/copy'
                    : 'message_msg/copy_intersystem';

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to'		=> $mail_addr_user_service->get_active($su->id(), $su->schema()),
                    'template'	=> $mail_template,
                    'vars'		=> $vars,
                ], 8000);
            }

            $alert_service->success('Mail verzonden.');

            return $this->redirectToRoute('messages_show', [
                ...$pp->ary(),
                'id' => $id,
            ]);
        }

        /**
         *
         */

        $visible_ary = $item_access_service->get_visible_ary_for_page();

        $prev_id = $message_repository->get_prev_id($id, $visible_ary, $pp->schema());
        $next_id = $message_repository->get_next_id($id, $visible_ary, $pp->schema());

        /*
        $sql_where = [];

        if ($pp->is_guest())
        {
            $sql_where[] = 'm.access = \'guest\'';
        }

        if (!$pp->is_admin())
        {
            $sql_where[] = 'u.is_active';
            $sql_where[] = 'u.remote_schema is null';
            $sql_where[] = 'u.remote_email is null';
        }

        $sql_where = count($sql_where) ? ' and ' . implode(' and ', $sql_where) : '';

        $prev_id = $db->fetchOne('select m.id
            from ' . $pp->schema() . '.messages m,
                ' . $pp->schema() . '.users u
            where m.id > ?
            ' . $sql_where . '
            order by m.id asc
            limit 1',
            [$id], [\PDO::PARAM_INT]);

        $next_id = $db->fetchOne('select m.id
            from ' . $pp->schema() . '.messages m,
                ' . $pp->schema() . '.users u
            where m.id < ?
            ' . $sql_where . '
            order by m.id desc
            limit 1',
            [$id], [\PDO::PARAM_INT]);
        */

        /*
        $contacts_response = $contacts_user_show_inline_controller(
            $user['id'],
            $contact_repository,
            $item_access_service,
            $link_render,
            $pp,
            $su,
            $distance_service,
            $account_render,
            $env_map_access_token,
            $env_map_tiles_url
        );

        $contacts_content = $contacts_response->getContent();
        */

        $is_expired = isset($message['expires_at']) && strtotime($message['expires_at'] . ' UTC') < time();
        $show_access = $intersystems_service->get_count($pp->schema()) > 0;

        return $this->render('messages/messages_show.html.twig', [
            'message'   => $message,
            'id'        => $id,
            'mail_form' => $mail_form,
            'prev_id'   => $prev_id,
            'next_id'   => $next_id,
            'category'  => $category,
            'image_files'   =>  array_values(json_decode($message['image_files'] ?? '[]', true)),
            'user'          => $user,
            'is_expired'    => $is_expired,
            'show_access'   => $show_access,
        ]);
    }

    static public function btn_extend(
        LinkRender $link_render,
        PageParamsService $pp,
        int $id,
        int $days,
        string $label
    ):string
    {
        return $link_render->link('messages_extend', $pp->ary(), [
                'id' 	=> $id,
                'days' 	=> $days,
            ], $label, [
                'class' => 'btn btn-default',
            ]);
    }

    public static function get_message(Db $db, int $id, string $pp_schema):array
    {
        $message = $db->fetchAssociative('select m.*
            from ' . $pp_schema . '.messages m
            where m.id = ?', [$id], [\PDO::PARAM_INT]);

        if (!$message)
        {
            throw new NotFoundHttpException('Dit bericht bestaat niet of werd verwijderd.');
        }

        $message['label'] = self::get_label($message['offer_want']);

        return $message;
    }

    public static function get_label(string $offer_want):array
    {
        return [
            'offer_want'        => MessageTypeCnst::TO_LABEL[$offer_want],
            'offer_want_the'    => MessageTypeCnst::TO_THE_LABEL[$offer_want],
            'offer_want_this'   => MessageTypeCnst::TO_THIS_LABEL[$offer_want],
        ];
    }
}
