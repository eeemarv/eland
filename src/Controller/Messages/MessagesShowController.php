<?php declare(strict_types=1);

namespace App\Controller\Messages;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Doctrine\DBAL\Connection as Db;
use App\Cnst\MessageTypeCnst;
use App\Command\SendMessage\SendMessageCCCommand;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\LinkRender;
use App\Repository\CategoryRepository;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\DistanceService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use App\Controller\Contacts\ContactsUserShowInlineController;
use App\Form\Post\SendMessage\SendMessageCCType;
use App\Repository\ContactRepository;
use App\Repository\MessageRepository;

class MessagesShowController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        MessageRepository $message_repository,
        CategoryRepository $category_repository,
        ContactRepository $contact_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service,
        DistanceService $distance_service,
        ContactsUserShowInlineController $contacts_user_show_inline_controller,
        string $env_s3_url,
        string $env_map_access_token,
        string $env_map_tiles_url
    ):Response
    {
        $visible_ary = $item_access_service->get_visible_ary_for_page();

        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $units_enabled = $config_service->get_bool('messages.fields.units.enabled', $pp->schema());
        $mail_enabled = $config_service->get_bool('mail.enabled', $pp->schema());

        $message = $message_repository->get($id, $pp->schema());

        if ($message['access'] === 'user' && $pp->is_guest())
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot dit bericht.');
        }

        if ($category_enabled && isset($message['category_id']))
        {
            $category = $category_repository->get($message['category_id'], $pp->schema());
        }

        $user_id = $message['user_id'];
        $user = $user_cache_service->get($user_id, $pp->schema());

        // process mail form

        $mail_from_addr = $mail_addr_user_service->get($su->id(), $su->schema());
        $mail_to_addr = $mail_addr_user_service->get($user_id, $pp->schema());

        $can_reply = count($mail_from_addr) ? true : false;

        $send_message_cc_command = new SendMessageCCCommand();
        $mail_form_options = [];

        if (!$mail_enabled)
        {
            $mail_form_options['placeholder'] = 'mail_form.mail_disabled';
            $mail_form_options['disabled'] = true;
        }
        else if ($su->is_master())
        {
            $mail_form_options['placeholder'] = 'mail_form.master_not_allowed';
            $mail_form_options['disabled'] = true;
        }
        else if ($su->is_owner($user_id))
        {
            $mail_form_options['placeholder'] = 'mail_form.owner_not_allowed';
            $mail_form_options['disabled'] = true;
        }
        else if (!count($mail_to_addr))
        {
            $mail_form_options['placeholder'] = 'mail_form.no_to_address';
            $mail_form_options['disabled'] = true;
        }
        else if (!count($mail_from_addr))
        {
            $mail_form_options['placeholder'] = 'mail_form.no_from_address';
            $mail_form_options['disabled'] = true;
        }

        $mail_form = $this->createForm(SendMessageCCType::class,
                $send_message_cc_command, $mail_form_options)
            ->handleRequest($request);

        if ($mail_form->isSubmitted()
            && $mail_form->isValid()
            && !isset($mail_form_options['disabled']))
        {
            $send_message_cc_command = $mail_form->getData();

            $to_user = $user;

            if (!$pp->is_admin() && !in_array($to_user['status'], [1, 2]))
            {
                throw new AccessDeniedHttpException('Access Denied');
            }

            $from_user = $user_cache_service->get($su->id(), $su->schema());

            $vars = [
                'from_user'			=> $from_user,
                'from_schema'		=> $su->schema(),
                'is_same_system'	=> $su->is_system_self(),
                'to_user'			=> $to_user,
                'to_schema'			=> $pp->schema(),
                'msg_content'		=> $send_message_cc_command->message,
                'message'			=> $message,
            ];

            $mail_template = $su->is_system_self()
                ? 'message_msg/msg'
                : 'message_msg/msg_intersystem';

            $mail_queue->queue([
                'schema'	=> $pp->schema(),
                'to'		=> $mail_to_addr,
                'reply_to'	=> $mail_from_addr,
                'template'	=> $mail_template,
                'vars'		=> $vars,
            ], 8500);

            if ($cc = $send_message_cc_command->cc)
            {
                $mail_template = $su->is_system_self()
                    ? 'message_msg/copy'
                    : 'message_msg/copy_intersystem';

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to'		=> $mail_from_addr,
                    'template'	=> $mail_template,
                    'vars'		=> $vars,
                ], 8000);
            }

            $alert_service->success('messages_show.success.mail_sent', [
                '%to_user%'     => $account_render->str($user_id, $pp->schema()),
            ]);
            $link_render->redirect('messages_show', $pp->ary(),
                ['id' => $id]);
        }

        $prev_id = $message_repository->get_prev_id($id, $visible_ary, $pp->schema());
        $next_id = $message_repository->get_next_id($id, $visible_ary, $pp->schema());

        $image_files = array_values(json_decode($message['image_files'] ?? '[]', true));

        $data_images = [
            'base_url'      => $env_s3_url,
            'files'         => $image_files,
        ];

        $contacts_response = $contacts_user_show_inline_controller(
            $user['id'],
            $db,
            $assets_service,
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

        $msg_label_offer_want = $message['is_offer'] ? 'aanbod' : 'vraag';

        if ($pp->is_admin() || $su->is_owner($message['user_id']))
        {

            $btn_top_render->edit('messages_edit', $pp->ary(),
                ['id' => $id],	ucfirst($msg_label_offer_want) . ' aanpassen');

            $btn_top_render->del('messages_del', $pp->ary(),
                ['id' => $id], ucfirst($msg_label_offer_want) . ' verwijderen');
        }

        if ($message['is_offer']
            && ($pp->is_admin()
                || (!$su->is_owner($message['user_id'])
                    && $user['status'] !== 7
                    && !($pp->is_guest() && $su->is_system_self()))))
        {
            $tus = ['mid' => $id];

            if (!$su->is_system_self())
            {
                $tus['tus'] = $pp->schema();
            }

            $btn_top_render->add_trans('transactions_add', $su->ary(),
                $tus, 'Transactie voor dit aanbod');
        }

        $prev_ary = $prev_id ? ['id' => $prev_id] : [];
        $next_ary = $next_id ? ['id' => $next_id] : [];

        $btn_nav_render->nav('messages_show', $pp->ary(),
            $prev_ary, $next_ary, false);

        $btn_nav_render->nav_list($vr->get('messages'), $pp->ary(),
            [], 'Lijst', 'newspaper-o');

        $out = '';

        if ($category_enabled)
        {
            $out .= '<p>Categorie: ';
            $out .= '<strong><i>';

            if (isset($category))
            {
                $cat_name = $category['parent_name'] ?? '';
                $cat_name .= isset($category['parent_name']) ? ' > ' : '';
                $cat_name .= $category['name'];

                $out .= $link_render->link_no_attr($vr->get('messages'), $pp->ary(),
                    ['f' => ['cid' => $category['id']]], $cat_name);
            }
            else
            {
                $out .= $link_render->link_no_attr($vr->get('messages'), $pp->ary(),
                    ['f' => ['cid' => 'null']], '** zonder categorie **');
            }

            $out .= '</i></strong>';
            $out .= '</p>';
        }

        $out .= '<div class="row">';

        $out .= '<div class="col-md-6">';

        $out .= '<div class="card card-default">';
        $out .= '<div class="card-body">';

        $out .= '<div id="no_images" ';
        $out .= 'class="text-center center-body"';
        $out .= count($image_files) ? '' : ' hidden';
        $out .= '>';
        $out .= '<i class="fa fa-image fa-5x"></i> ';
        $out .= '<p>Er zijn geen afbeeldingen voor ';
//        $out .= $message['label']['offer_want_this'] . '</p>';
        $out .= '</div>';

        $out .= '<div id="jssor_1" ';
        $out .= 'class="row carousel slide" ';
        $out .= 'data-ride="carousel" ';
        $out .= 'data-touch="true" ';
        $out .= 'data-images="';
        $out .= htmlspecialchars(json_encode($data_images));
        $out .= '"  style="background-color: #777;" ';
        $out .= 'data-no-tourch-swipe>';
        $out .= '<div class="carousel-inner">';

        $crsl_ind = '';
        $crsl_items = '';

        foreach($image_files as $key => $image_file)
        {
            $crsl_ind .= '<li data-target="#images_con" ';
            $crsl_ind .= 'data-slide-to="';
            $crsl_ind .= $key;
            $crsl_ind .= '"';
            $crsl_ind .= $key === 0 ? ' class="active"' : '';
            $crsl_ind .= '></li>';

            $crsl_items .= '<div class="carousel-item text-center ';
            $crsl_items .= $key === 0 ? ' active' : '';
            $crsl_items .= '">';
            $crsl_items .= '<div class="d-flex justify-content-center" style="max-height:400px; max-width:400px;">';
            $crsl_items .= '<img src="';
            $crsl_items .= $env_s3_url . $image_file;

            $crsl_items .= '" class="d-block m-auto img-fluid" ';
//          $crsl_items .= '" class="d-block w-100 img-fluid" ';
            $crsl_items .= '>';
            $crsl_items .= '</div>';
            $crsl_items .= '</div>';
        }

        $out .= '<ol class="carousel-indicators">';
        $out .= $crsl_ind;
        $out .= '</ol>';

        $out .= $crsl_items;

        $out .= '<a class="carousel-control-prev" href="#images_con" role="button" data-slide="prev">';
        $out .= '<span class="carousel-control-prev-icon" aria-hidden="true"></span>';
        $out .= '<span class="sr-only">Previous</span>';
        $out .= '</a>';

        $out .= '<a class="carousel-control-next" href="#images_con" role="button" data-slide="next">';
        $out .= '<span class="carousel-control-next-icon" aria-hidden="true"></span>';
        $out .= '<span class="sr-only">Next</span>';
        $out .= '</a>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';

        if ($pp->is_admin() || $su->is_owner($message['user_id']))
        {
            $out .= '<div class="card-footer">';
            $out .= '<span class="btn btn-success btn-lg btn-block fileinput-button">';
            $out .= '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
            $out .= '<input type="file" name="images[]" ';
            $out .= 'data-url="';

/*
            $out .= $link_render->context_path('messages_images_upload',
                $pp->ary(), ['id' => $id]);
*/

            $out .= '" ';
            $out .= 'data-fileupload ';
            $out .= 'data-message-file-type-not-allowed="Bestandstype is niet toegelaten." ';
            $out .= 'data-message-max-file-size="Het bestand is te groot." ';
            $out .= 'data-message-min-file-size="Het bestand is te klein." ';
            $out .= 'data-message-uploaded-bytes="Het bestand is te groot." ';
            $out .= 'multiple></span>';

            $out .= '<p class="text-warning">';
            $out .= 'Toegestane formaten: jpg/jpeg, png, gif, svg. ';
            $out .= 'Je kan ook afbeeldingen hierheen verslepen.</p>';

            $out .= $link_render->link_fa('messages_images_del', $pp->ary(),
                ['id'		=> $id],
                'Afbeeldingen verwijderen', [
                    'class'	=> 'btn btn-danger btn-lg btn-block',
                    'id'	=> 'btn_remove',
                    'style'	=> 'display:none;',
                ],
                'times'
            );

            $out .= '</div>';
        }

        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="col-md-6">';

        $out .= '<div class="card bg-default printview">';
        $out .= '<div class="card-body">';

        $out .= '<p><b>Omschrijving</b></p>';
        $out .= '</div>';
        $out .= '<div class="card-body">';
        $out .= '<p>';

        if ($message['content'])
        {
            $out .= nl2br($message['content']);
        }
        else
        {
            $out .= '<i>Er werd geen omschrijving ingegeven.</i>';
        }

        $out .= '</p>';
        $out .= '</div></div>';

        $out .= '<div class="card bg-default printview">';
        $out .= '<div class="card-body">';

        $out .= '<dl>';

        if ($units_enabled)
        {
            $out .= '<dt>';
            $out .= 'Richtprijs';
            $out .= '</dt>';
            $out .= '<dd>';

            if (empty($message['amount']))
            {
                $out .= 'niet opgegeven.';
            }
            else
            {
                $out .= $message['amount'] . ' ';
                $out .= $currency;
                $out .= $message['units'] ? ' per ' . $message['units'] : '';
            }

            $out .= '</dd>';
        }

        $out .= '<dt>Van gebruiker: ';
        $out .= '</dt>';
        $out .= '<dd>';
        $out .= $account_render->link($message['user_id'], $pp->ary());
        $out .= '</dd>';

        $out .= '<dt>Plaats</dt>';
        $out .= '<dd>';
        $out .= $user['postcode'];
        $out .= '</dd>';

        $out .= '<dt>Aangemaakt op</dt>';
        $out .= '<dd>';
        $out .= $date_format_service->get($message['created_at'], 'day', $pp->schema());
        $out .= '</dd>';

        if ($expires_at_enabled)
        {
            $out .= '<dt>Geldig tot</dt>';
            $out .= '<dd>';

            if (isset($message['expires_at']))
            {
                $out .= $date_format_service->get($message['expires_at'], 'day', $pp->schema());
                $out .= '</dd>';

                if ($pp->is_admin() || $su->is_owner($message['user_id']))
                {
                    $out .= '<dt>Verlengen</dt>';
                    $out .= '<dd>';
                    $out .= self::btn_extend($link_render, $pp, $id, 30, '1 maand');
                    $out .= '&nbsp;';
                    $out .= self::btn_extend($link_render, $pp, $id, 180, '6 maanden');
                    $out .= '&nbsp;';
                    $out .= self::btn_extend($link_render, $pp, $id, 365, '1 jaar');
                    $out .= '</dd>';
                }
            }
            else
            {
                $out .= '<span class="text-danger"><em><b>* Dit bericht vervalt niet *</b></em></span>';
                $out .= '</dd>';
            }
        }

        if ($intersystems_service->get_count($pp->schema()))
        {
            $out .= '<dt>Zichtbaarheid</dt>';
            $out .= '<dd>';
            $out .=  $item_access_service->get_label($message['access']);
            $out .= '</dd>';
        }

        $out .= '</dl>';

        $out .= '</div>';
        $out .= '</div>';

        $out .= '</div>';
        $out .= '</div>';

        /*
        $out .= UsersShowController::get_mail_form(
            $message['user_id'],
            $user_mail_content,
            $user_mail_cc,
            $account_render,
            $form_token_service,
            $mail_addr_user_service,
            $pp,
            $su
        );
        */

        $out .= $contacts_content;

        $menu_service->set('messages');

        $message['is_expired'] = isset($message['expires_at']) && strtotime($message['expires_at'] . ' UTC') < time();

        return $this->render('messages/messages_show.html.twig', [
            'content'       => $out,
            'message'       => $message,
            'category'      => $category ?? null,
            'show_access'   => $intersystems_service->get_count($pp->schema()) ? true : false,
            'user'          => $user,
            'mail_form'     => $mail_form->createView(),
            'schema'        => $pp->schema(),
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
        $message = $db->fetchAssoc('select m.*
            from ' . $pp_schema . '.messages m
            where m.id = ?', [$id]);

        if (!$message)
        {
            throw new NotFoundHttpException('Dit bericht bestaat niet of werd verwijderd.');
        }

        $message['offer_want'] = $message['is_offer'] ? 'offer' : 'want';
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