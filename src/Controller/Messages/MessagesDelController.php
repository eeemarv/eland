<?php declare(strict_types=1);

namespace App\Controller\Messages;

use App\Cache\ConfigCache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Render\AccountRender;
use App\Render\LinkRender;
use App\Repository\CategoryRepository;
use App\Service\AlertService;
use App\Service\DateFormatService;
use App\Service\FormTokenService;
use App\Service\IntersystemsService;
use App\Service\ItemAccessService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class MessagesDelController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/messages/{id}/del',
        name: 'messages_del',
        methods: ['GET', 'POST'],
        priority: 10,
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'module'        => 'messages',
        ],
    )]

    public function __invoke(
        Request $request,
        int $id,
        Db $db,
        CategoryRepository $category_repository,
        AccountRender $account_render,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        IntersystemsService $intersystems_service,
        ItemAccessService $item_access_service,
        LinkRender $link_render,
        ConfigCache $config_cache,
        DateFormatService $date_format_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr
    ):Response
    {
        if (!$config_cache->get_bool('messages.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Messages (offers/wants) module not enabled.');
        }

        $message = MessagesShowController::get_message($db, $id, $pp->schema());
        $category_enabled = $config_cache->get_bool('messages.fields.category.enabled', $pp->schema());
        $expires_at_enabled = $config_cache->get_bool('messages.fields.expires_at.enabled', $pp->schema());

        if ($category_enabled && isset($message['category_id']))
        {
            $category = $category_repository->get($message['category_id'], $pp->schema());
        }

        if (!($su->is_owner($message['user_id']) || $pp->is_admin()))
        {
            throw new AccessDeniedHttpException(
                'Je hebt onvoldoende rechten om dit bericht te verwijderen.');
        }

        if($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
            }

            if ($db->delete($pp->schema() . '.messages', ['id' => $id]))
            {
                $alert_service->success(ucfirst($message['label']['offer_want_this']) . ' is verwijderd.');

                return $this->redirectToRoute($vr->get('messages'), $pp->ary());
            }

            $alert_service->error(ucfirst($message['label']['offer_want_this']) . ' is niet verwijderd.');
        }

        $out = '<div class="panel panel-info printview">';
        $out .= '<div class="panel-heading">';

        $out .= '<dl>';

        $out .= '<dt>Wie</dt>';
        $out .= '<dd>';
        $out .= $account_render->link($message['user_id'], $pp->ary());
        $out .= '</dd>';

        if ($category_enabled)
        {
            $out .= '<dt>Categorie</dt>';
            $out .= '<dd>';

            if (isset($message['category_id']))
            {
                $cat_name = $category['parent_name'] ?? '';
                $cat_name .= isset($category['parent_name']) ? ' > ' : '';
                $cat_name .= $category['name'];
                $out .= $link_render->link_no_attr($vr->get('messages'), $pp->ary(),
                    ['f' => ['cid' => $message['category_id']]], $cat_name);
            }
            else
            {
                $out .= '<i>** onbepaald **</i>';
            }

            $out .= '</dd>';
        }

        if ($expires_at_enabled && isset($message['expires_at']))
        {
            $out .= '<dt>Geldig tot</dt>';
            $out .= '<dd>';
            $out .= $date_format_service->get($message['expires_at'], 'day', $pp->schema());
            $out .= '</dd>';
        }

        if ($config_cache->get_intersystem_en($pp->schema()) && $intersystems_service->get_count($pp->schema()))
        {
            $out .= '<dt>Zichtbaarheid</dt>';
            $out .= '<dd>';
            $out .= $item_access_service->get_label($message['access']);
            $out .= '</dd>';
        }

        $out .= '</dl>';

        $out .= '</div>';

        $out .= '<div class="panel-body">';
        $out .= nl2br($message['content']);
        $out .= '</div>';

        $out .= '<div class="panel-heading">';
        $out .= '<h3>';
        $out .= '<span class="danger">';
        $out .= 'Ben je zeker dat ' . $message['label']['offer_want_this'];
        $out .= ' moet verwijderd worden?</span>';

        $out .= '</h3>';

        $out .= '<form method="post">';

        $out .= $link_render->btn_cancel('messages_show', $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Verwijderen" name="zend" class="btn btn-danger btn-lg">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form></p>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('messages/messages_del.html.twig', [
            'content'   => $out,
            'message'   => $message,
        ]);
    }
}
