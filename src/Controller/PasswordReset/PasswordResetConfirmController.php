<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use App\Cnst\PagesCnst;
use App\Queue\MailQueue;
use App\Render\LinkRender;
use App\Security\User;
use App\Service\AlertService;
use App\Service\DataTokenService;
use App\Service\FormTokenService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\PasswordStrengthService;
use App\Service\SessionUserService;
use App\Service\UserCacheService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class PasswordResetConfirmController extends AbstractController
{
    #[Route(
        '/{system}/password-reset/{token}',
        name: 'password_reset_confirm',
        methods: ['GET', 'POST'],
        priority: 30,
        requirements: [
            'token'         => '%assert.token%',
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'password_reset',
        ],
    )]

    public function __invoke(
        Request $request,
        EncoderFactoryInterface $encoder_factory,
        string $token,
        Db $db,
        DataTokenService $data_token_service,
        FormTokenService $form_token_service,
        AlertService $alert_service,
        LinkRender $link_render,
        MenuService $menu_service,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        PasswordStrengthService $password_strength_service,
        PageParamsService $pp,
        SessionUserService $su,
        UserCacheService $user_cache_service
    ):Response
    {
        $password = $request->request->get('password', '');

        if ($pp->edit_en()
            && $token === PagesCnst::CMS_TOKEN
            && $su->is_admin())
        {
            $user_id = $su->id();
        }
        else
        {
            $data = $data_token_service->retrieve($token, 'password_reset', $pp->schema());

            if (!$data)
            {
                $alert_service->error('Het reset-token is niet meer geldig.');
                $link_render->redirect('password_reset', $pp->ary(), []);
            }

            $user_id = $data['user_id'];
        }

        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
            }
            else if (!($password_strength_service->get($password) < 50))
            {
                $encoder = $encoder_factory->getEncoder(new User());
                $hashed_password = $encoder->encodePassword($password, null);

                $db->update($pp->schema() . '.users',
                    ['password' => $hashed_password],
                    ['id' => $user_id]);

                $user_cache_service->clear($user_id, $pp->schema());
                $alert_service->success('Paswoord opgeslagen.');

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to' 		=> $mail_addr_user_service->get_active($user_id, $pp->schema()),
                    'template'	=> 'password_reset/user',
                    'vars'		=> [
                        'password'		=> $password,
                        'user_id'		=> $user_id,
                    ],
                ], 10000);

                $data = $data_token_service->del($token, 'password_reset', $pp->schema());
                $link_render->redirect('login', $pp->ary(), []);
            }
            else
            {
                $alert_service->error('Het paswoord is te zwak.');
            }
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post" role="form">';
        $out .= '<fieldset';
        $out .= $pp->edit_en() ? ' disabled' : '';
        $out .= '>';

        $out .= $pp->edit_en() ? '<p class="text-danger">Dit formulier is niet actief in CMS Edit Mdous.</p>' : '';

        $out .= '<div class="form-group">';
        $out .= '<label for="password">Nieuw paswoord</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-key"></i>';
        $out .= '</span>';
        $out .= '<input type="text" class="form-control" id="password" name="password" ';
        $out .= 'value="';
        $out .= $password;
        $out .= '" required>';
        $out .= '<span class="input-group-btn">';
        $out .= '<button class="btn btn-default" type="button" ';
        $out .= 'data-generate-password>Genereer</button>';
        $out .= '</span>';
        $out .= '</div>';
        $out .= '</div>';

        $out .= '<input type="submit" class="btn btn-primary btn-lg" value="Bewaar paswoord" name="zend">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</fieldset>';
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('login');

        return $this->render('password_reset/password_reset_confirm.html.twig', [
            'content'   => $out,
        ]);
    }
}
