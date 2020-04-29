<?php declare(strict_types=1);

namespace App\Controller;

use App\Queue\MailQueue;
use App\Render\AccountRender;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Security\User;
use App\Service\AlertService;
use App\Service\AssetsService;
use App\Service\FormTokenService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\PasswordStrengthService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use App\Service\VarRouteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class UsersPasswordAdminController extends AbstractController
{
    public function __invoke(
        Request $request,
        EncoderFactoryInterface $encoder_factory,
        int $id,
        Db $db,
        AccountRender $account_render,
        AlertService $alert_service,
        AssetsService $assets_service,
        FormTokenService $form_token_service,
        HeadingRender $heading_render,
        LinkRender $link_render,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        PasswordStrengthService $password_strength_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        MenuService $menu_service
    ):Response
    {
        $errors = [];

        $password = trim($request->request->get('password', ''));
        $notify = $request->request->get('notify', '');

        if($request->isMethod('POST'))
        {
            if ($password === '')
            {
                $errors[] = 'Vul paswoord in!';
            }

            if (!$pp->is_admin()
                && $password_strength_service->get($password) < 50)
            {
                $errors[] = 'Te zwak paswoord.';
            }

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if (!count($errors))
            {
                $encoder = $encoder_factory->getEncoder(new User());
                $hashed_password = $encoder->encodePassword($password, null);

                $update = [
                    'password'	=> $hashed_password,
                    'mdate'		=> gmdate('Y-m-d H:i:s'),
                ];

                if ($db->update($pp->schema() . '.users',
                    $update,
                    ['id' => $id]))
                {
                    $user_cache_service->clear($id, $pp->schema());
                    $user = $user_cache_service->get($id, $pp->schema());
                    $alert_service->success('Paswoord opgeslagen.');

                    if (($user['status'] === 1 || $user['status'] === 2)
                        && $notify)
                    {
                        $to = $db->fetchColumn('select c.value
                            from ' . $pp->schema() . '.contact c, ' .
                                $pp->schema() . '.type_contact tc
                            where tc.id = c.id_type_contact
                                and tc.abbrev = \'mail\'
                                and c.user_id = ?', [$id]);

                        if ($to)
                        {
                            $vars = [
                                'user_id'		=> $id,
                                'password'		=> $password,
                            ];

                            $mail_queue->queue([
                                'schema'	=> $pp->schema(),
                                'to' 		=> $mail_addr_user_service->get_active($id, $pp->schema()),
                                'reply_to'	=> $mail_addr_system_service->get_support($pp->schema()),
                                'template'	=> 'password_reset/user',
                                'vars'		=> $vars,
                            ], 8000);

                            $alert_service->success('Notificatie mail verzonden');
                        }
                        else
                        {
                            $alert_service->warning('Geen E-mail adres bekend voor deze gebruiker, stuur het paswoord op een andere manier door!');
                        }
                    }

                    $link_render->redirect($vr->get('users_show'), $pp->ary(), ['id' => $id]);
                }
                else
                {
                    $alert_service->error('Paswoord niet opgeslagen.');
                }
            }
            else
            {
                $alert_service->error($errors);
            }

        }

        $user = $user_cache_service->get($id, $pp->schema());

        $assets_service->add([
            'generate_password.js',
        ]);

        $heading_render->add('Paswoord aanpassen');

        if ($pp->is_admin() && $id !== $su->id())
        {
            $heading_render->add(' voor ');
            $heading_render->add_raw($account_render->link($id, $pp->ary()));
        }

        $heading_render->fa('key');

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="password" class="control-label">';
        $out .= 'Paswoord</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<span class="fa fa-key"></span></span>';
        $out .= '<input type="text" class="form-control" ';
        $out .= 'id="password" name="password" ';
        $out .= 'value="';
        $out .= $password;
        $out .= '" required>';
        $out .= '<span class="input-group-btn">';
        $out .= '<button class="btn btn-default" type="button" ';
        $out .= 'data-generate-password>Genereer</button>';
        $out .= '</span>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<div class="form-group">';
        $out .= '<label for="notify" class="control-label">';
        $out .= '<input type="checkbox" name="notify" id="notify"';
        $out .= $user['status'] == 1 || $user['status'] == 2 ? ' checked="checked"' : ' readonly';
        $out .= '>';
        $out .= ' Verzend notificatie E-mail met nieuw paswoord. ';

        if ($pp->is_admin())
        {
            $out .= 'Dit is enkel mogelijk wanneer de Status ';
            $out .= 'actief is en E-mail adres ingesteld.';
        }

        $out .= '</label>';
        $out .= '</div>';

        $out .= $link_render->btn_cancel($vr->get('users_show'), $pp->ary(), ['id' => $id]);

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" name="zend" ';
        $out .= 'class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('users');

        return $this->render('base/navbar.html.twig', [
            'content'   => $out,
            'schema'    => $pp->schema(),
        ]);
    }
}
