<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Doctrine\DBAL\Connection as Db;
use App\Cnst\MessageTypeCnst;
use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\BtnNavRender;
use App\Render\BtnTopRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Repository\CategoryRepository;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\ConfigService;
use App\Service\DateFormatService;
use App\Service\DistanceService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;

class MessagesShowController extends AbstractController
{
    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        CategoryRepository $category_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        BtnNavRender $btn_nav_render,
        BtnTopRender $btn_top_render,
        ConfigService $config_service,
        DateFormatService $date_format_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
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
        if (!$config_service->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $transactions_enabled = $config_service->get_bool('transactions.enabled', $pp->schema());

        $errors = [];

        $currency = $config_service->get_str('transactions.currency.name', $pp->schema());
        $category_enabled = $config_service->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_service->get_bool('messages.fields.expires_at.enabled', $pp->schema());
        $units_enabled = $config_service->get_bool('messages.fields.units.enabled', $pp->schema());
        $message = self::get_message($db, $id, $pp->schema());

        if ($category_enabled && isset($message['category_id']))
        {
            $category = $category_repository->get($message['category_id'], $pp->schema());
        }

        $user_mail_content = $request->request->get('user_mail_content', '');
        $user_mail_cc = $request->request->get('user_mail_cc', '') ? true : false;
        $user_mail_submit = $request->request->get('user_mail_submit', '') ? true : false;

        $user_mail_cc = $request->isMethod('POST') ? $user_mail_cc : true;

        if ($message['access'] === 'user' && $pp->is_guest())
        {
            throw new AccessDeniedHttpException('Je hebt geen toegang tot dit bericht.');
        }

        $user = $user_cache_service->get($message['user_id'], $pp->schema());

        // process mail form

        if ($user_mail_submit && $request->isMethod('POST'))
        {
            $to_user = $user;

            if (!$pp->is_admin() && !in_array($to_user['status'], [1, 2]))
            {
                throw new AccessDeniedHttpException('Je hebt geen rechten om een
                    bericht naar een niet-actieve gebruiker te sturen');
            }

            if ($su->is_master())
            {
                throw new AccessDeniedHttpException('Het master account
                    kan geen berichten versturen.');
            }

            $token_error = $form_token_service->get_error();

            if ($token_error)
            {
                $errors[] = $token_error;
            }

            if (!$user_mail_content)
            {
                $errors[] = 'Fout: leeg bericht. E-mail niet verzonden.';
            }

            $reply_ary = $mail_addr_user_service->get_active($su->id(), $su->schema());

            if (!count($reply_ary))
            {
                $errors[] = 'Fout: Je kan geen berichten naar een andere gebruiker
                    verzenden als er geen E-mail adres is ingesteld voor je eigen account.';
            }

            if (!count($errors))
            {
                $from_user = $user_cache_service->get($su->id(), $su->schema());

                $vars = [
                    'from_user'			=> $from_user,
                    'from_schema'		=> $su->schema(),
                    'is_same_system'	=> $su->is_system_self(),
                    'to_user'			=> $to_user,
                    'to_schema'			=> $pp->schema(),
                    'msg_content'		=> $user_mail_content,
                    'message'			=> $message,
                ];

                $mail_template = $su->is_system_self()
                    ? 'message_msg/msg'
                    : 'message_msg/msg_intersystem';

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to'		=> $mail_addr_user_service->get_active($to_user['id'], $pp->schema()),
                    'reply_to'	=> $reply_ary,
                    'template'	=> $mail_template,
                    'vars'		=> $vars,
                ], 8500);

                if ($user_mail_cc)
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
                $link_render->redirect('messages_show', $pp->ary(),
                    ['id' => $id]);
            }

            $alert_service->error($errors);
        }

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
            $sql_where[] = 'u.status in (1, 2)';
        }

        $sql_where = count($sql_where) ? ' and ' . implode(' and ', $sql_where) : '';

        $prev = $db->fetchOne('select m.id
            from ' . $pp->schema() . '.messages m,
                ' . $pp->schema() . '.users u
            where m.id > ?
            ' . $sql_where . '
            order by m.id asc
            limit 1',
            [$id], [\PDO::PARAM_INT]);

        $next = $db->fetchOne('select m.id
            from ' . $pp->schema() . '.messages m,
                ' . $pp->schema() . '.users u
            where m.id < ?
            ' . $sql_where . '
            order by m.id desc
            limit 1',
            [$id], [\PDO::PARAM_INT]);

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

        $assets_service->add([
            'jssor',
            'messages_show_images_slider.js',
        ]);

        if ($pp->is_admin() || $su->is_owner($message['user_id']))
        {
            $assets_service->add([
                'fileupload',
                'messages_show_images_upload.js',
            ]);
        }

        if ($pp->is_admin() || $su->is_owner($message['user_id']))
        {
            $btn_top_render->edit('messages_edit', $pp->ary(),
                ['id' => $id],	ucfirst($message['label']['offer_want']) . ' aanpassen');

            $btn_top_render->del('messages_del', $pp->ary(),
                ['id' => $id], ucfirst($message['label']['offer_want']) . ' verwijderen');
        }

        if ($message['offer_want'] === 'offer'
            && $transactions_enabled
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

            $self_transactions_enabled = $config_service->get_bool('transactions.enabled', $su->schema());

            if ($self_transactions_enabled)
            {
                $btn_top_render->add_trans('transactions_add', $su->ary(),
                    $tus, 'Transactie voor dit aanbod');
            }
        }

        $prev_ary = $prev ? ['id' => $prev] : [];
        $next_ary = $next ? ['id' => $next] : [];

        $btn_nav_render->nav('messages_show', $pp->ary(),
            $prev_ary, $next_ary, false);

        $btn_nav_render->nav_list($vr->get('messages'), $pp->ary(),
            [], 'Lijst', 'newspaper-o');

        $heading_render->add(ucfirst($message['label']['offer_want']));
        $heading_render->add(': ' . $message['subject']);

        if ($expires_at_enabled)
        {
            $heading_render->add_raw(isset($message['expires_at']) && strtotime($message['expires_at']) < time() ? ' <small><span class="text-danger">Vervallen</span></small>' : '');
        }

        $heading_render->fa('newspaper-o');

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

        $out .= '<div class="panel panel-default">';
        $out .= '<div class="panel-body img-upload">';

        $out .= '<div id="no_images" ';
        $out .= 'class="text-center center-body">';
        $out .= '<i class="fa fa-image fa-5x"></i> ';
        $out .= '<p>Er zijn geen afbeeldingen voor ';
        $out .= $message['label']['offer_want_this'] . '</p>';
        $out .= '</div>';

        $out .= '<div id="images_con" ';
        $out .= 'data-images="';
        $out .= htmlspecialchars(json_encode($data_images));
        $out .= '">';
        $out .= '</div>';

        $out .= '</div>';

        if ($pp->is_admin() || $su->is_owner($message['user_id']))
        {
            $out .= '<div class="panel-footer">';
            $out .= '<span class="btn btn-success btn-lg btn-block fileinput-button">';
            $out .= '<i class="fa fa-plus" id="img_plus"></i> Afbeelding opladen';
            $out .= '<input type="file" name="images[]" ';
            $out .= 'data-url="';

            $out .= $link_render->context_path('messages_images_upload',
                $pp->ary(), ['id' => $id]);

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

        $out .= '<div class="panel panel-default printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<p><b>Omschrijving</b></p>';
        $out .= '</div>';
        $out .= '<div class="panel-body">';
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

        $out .= '<div class="panel panel-default printview">';
        $out .= '<div class="panel-heading">';

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

        $out .= UsersShowAdminController::get_mail_form(
            $message['user_id'],
            $user_mail_content,
            $user_mail_cc,
            $account_render,
            $form_token_service,
            $mail_addr_user_service,
            $pp,
            $su
        );

        $out .= $contacts_content;

        $menu_service->set('messages');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
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
