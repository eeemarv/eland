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
use App\Controller\Users\UsersShowController;
use App\Form\Type\MailContact\MailContactType;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\CategoryRepository;
use App\Repository\ContactRepository;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\DistanceService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
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
        Db $db,
        ContactRepository $contact_repository,
        CategoryRepository $category_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        DistanceService $distance_service,
        ContactsUserShowInlineController $contacts_user_show_inline_controller,
        string $env_s3_url,
        string $env_map_access_token,
        string $env_map_tiles_url
    ):Response
    {
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $transactions_enabled = $config_service->get_bool('transactions.enabled', $pp->schema());

        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
        $service_stuff_enabled = $config_service->get_bool('messages.fields.service_stuff.enabled', $pp->schema());
        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $units_enabled = $config_service->get_bool('messages.fields.units.enabled', $pp->schema());
        $message = self::get_message($db, $id, $pp->schema());

        if ($category_enabled && isset($message['category_id']))
        {
            $category = $category_repository->get($message['category_id'], $pp->schema());
        }

        if ($message['access'] === 'user' && $pp->is_guest())
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot dit bericht.');
        }

        $user = $user_cache_service->get($message['user_id'], $pp->schema());

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

        $data_images = [
            'base_url'      => $env_s3_url,
            'files'         => array_values(json_decode($message['image_files'] ?? '[]', true)),
        ];

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

        $cati = '';

        if ($category_enabled)
        {
            $cati .= '<p>Categorie: ';
            $cati .= '<strong><i>';

            if (isset($category))
            {
                $cat_name = $category['parent_name'] ?? '';
                $cat_name .= isset($category['parent_name']) ? ' > ' : '';
                $cat_name .= $category['name'];

                $cati .= $link_render->link_no_attr($vr->get('messages'), $pp->ary(),
                    ['f' => ['cat' => $category['id']]], $cat_name);
            }
            else
            {
                $cati .= $link_render->link_no_attr($vr->get('messages'), $pp->ary(),
                    ['f' => ['cid' => 'null']], '** zonder categorie **');
            }

            $cati .= '</i></strong>';
            $cati .= '</p>';
        }

        /**
         * Images panel
         */

        $imp = '<div class="panel panel-default">';
        $imp .= '<div class="panel-body img-upload">';

        $imp .= '<div id="no_images" ';
        $imp .= 'class="text-center center-body">';
        $imp .= '<i class="fa fa-image fa-5x"></i> ';
        $imp .= '<p>Er zijn geen afbeeldingen voor ';
        $imp .= $message['label']['offer_want_this'] . '</p>';
        $imp .= '</div>';

        $imp .= '<div id="images_con" ';
        $imp .= 'data-images="';
        $imp .= htmlspecialchars(json_encode($data_images));
        $imp .= '">';
        $imp .= '</div>';

        $imp .= '</div>';

        if ($pp->is_admin() || $su->is_owner($message['user_id']))
        {
            $imp .= '<div class="panel-footer">';
            $imp .= '<span class="btn btn-success btn-lg btn-block fileinput-button">';
            $imp .= '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
            $imp .= '<input type="file" name="images[]" ';
            $imp .= 'data-url="';

            $imp .= $link_render->context_path('messages_images_upload',
                $pp->ary(), ['id' => $id]);

            $imp .= '" ';
            $imp .= 'data-fileupload ';
            $imp .= 'data-message-file-type-not-allowed="Bestandstype is niet toegelaten." ';
            $imp .= 'data-message-max-file-size="Het bestand is te groot." ';
            $imp .= 'data-message-min-file-size="Het bestand is te klein." ';
            $imp .= 'data-message-uploaded-bytes="Het bestand is te groot." ';
            $imp .= 'multiple></span>';

            $imp .= '<p class="text-warning">';
            $imp .= 'Toegestane formaten: jpg/jpeg, png, wepb, gif, svg. ';
            $imp .= 'Je kan ook afbeeldingen hierheen verslepen.</p>';

            $imp .= $link_render->link_fa('messages_images_del', $pp->ary(),
                ['id'		=> $id],
                'Afbeeldingen verwijderen', [
                    'class'	=> 'btn btn-danger btn-lg btn-block',
                    'id'	=> 'btn_remove',
                    'style'	=> 'display:none;',
                ],
                'times'
            );

            $imp .= '</div>';
        }

        $imp .= '</div>';

        /**
         * Message info
         */

        $mip = '<div class="panel panel-default printview">';
        $mip .= '<div class="panel-heading">';

        $mip .= '<p><b>Omschrijving</b></p>';
        $mip .= '</div>';
        $mip .= '<div class="panel-body">';
        $mip .= '<p>';

        if ($message['content'])
        {
            $mip .= nl2br($message['content']);
        }
        else
        {
            $mip .= '<i>Er werd geen omschrijving ingegeven.</i>';
        }

        $mip .= '</p>';
        $mip .= '</div></div>';

        $mip .= '<div class="panel panel-default printview">';
        $mip .= '<div class="panel-heading">';

        $mip .= '<dl>';

        if ($units_enabled)
        {
            $mip .= '<dt>';
            $mip .= 'Richtprijs';
            $mip .= '</dt>';
            $mip .= '<dd>';

            if (empty($message['amount']))
            {
                $mip .= 'niet opgegeven.';
            }
            else
            {
                $mip .= $message['amount'] . ' ';
                $mip .= $currency;
                $mip .= $message['units'] ? ' per ' . $message['units'] : '';
            }

            $mip .= '</dd>';
        }

        $mip .= '<dt>Van gebruiker: ';
        $mip .= '</dt>';
        $mip .= '<dd>';
        $mip .= $account_render->link($message['user_id'], $pp->ary());
        $mip .= '</dd>';

        $mip .= '<dt>Plaats</dt>';
        $mip .= '<dd>';
        $mip .= $user['postcode'];
        $mip .= '</dd>';

        $mip .= '<dt>Aangemaakt op</dt>';
        $mip .= '<dd>';
        $mip .= $date_format_service->get($message['created_at'], 'day', $pp->schema());
        $mip .= '</dd>';

        if ($expires_at_enabled)
        {
            $mip .= '<dt>Geldig tot</dt>';
            $mip .= '<dd>';

            if (isset($message['expires_at']))
            {
                $mip .= $date_format_service->get($message['expires_at'], 'day', $pp->schema());
                $mip .= '</dd>';

                if ($pp->is_admin() || $su->is_owner($message['user_id']))
                {
                    $mip .= '<dt>Verlengen</dt>';
                    $mip .= '<dd>';
                    $mip .= self::btn_extend($link_render, $pp, $id, 30, '1 maand');
                    $mip .= '&nbsp;';
                    $mip .= self::btn_extend($link_render, $pp, $id, 180, '6 maanden');
                    $mip .= '&nbsp;';
                    $mip .= self::btn_extend($link_render, $pp, $id, 365, '1 jaar');
                    $mip .= '</dd>';
                }
            }
            else
            {
                $mip .= '<span class="text-danger"><em><b>* Dit bericht vervalt niet *</b></em></span>';
                $mip .= '</dd>';
            }
        }

        if ($service_stuff_enabled)
        {
            $mip .= '<dt>Diensten / spullen</dt>';
            $mip .= '<dd>';

            if (isset($message['service_stuff']))
            {
                $se_st = MessageTypeCnst::SERVICE_STUFF_TPL_ARY[$message['service_stuff']];
                $mip .= '<span class="btn btn-' . $se_st['btn_class'] . '">';
                $mip .= $se_st['label'];
                $mip .= '</span>';
            }
            else
            {
                $mip .= '<span class="text-danger"><b><em>* Onbepaald *</em></b></span>';
            }

            $mip .= '</dd>';
        }

        if ($intersystems_service->get_count($pp->schema()))
        {
            $mip .= '<dt>Zichtbaarheid</dt>';
            $mip .= '<dd>';
            $mip .=  $item_access_service->get_label($message['access']);
            $mip .= '</dd>';
        }

        $mip .= '</dl>';

        $mip .= '</div>';
        $mip .= '</div>';

        $message['is_expired'] = isset($message['expires_at']) && strtotime($message['expires_at'] . ' UTC') < time();

        return $this->render('messages/messages_show.html.twig', [
            'message'   => $message,
            'id'        => $id,
            'mail_form' => $mail_form,
            'prev_id'   => $prev_id,
            'next_id'   => $next_id,
            'category_info_raw'         => $cati,
            'images_panel_raw'          => $imp,
            'message_info_panel_raw'    => $mip,
            'user_contacts_table_raw'   => $contacts_content,
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
