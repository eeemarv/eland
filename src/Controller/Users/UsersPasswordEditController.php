<?php declare(strict_types=1);

namespace App\Controller\Users;

use App\Cnst\BulkCnst;
use App\Queue\MailQueue;
use App\Render\LinkRender;
use App\Security\User;
use App\Service\AlertService;
use App\Service\FormTokenService;
use App\Service\MailAddrSystemService;
use App\Service\MailAddrUserService;
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

class UsersPasswordEditController extends AbstractController
{
    #[Route(
        '/{system}/{role_short}/users/{id}/password-edit',
        name: 'users_password_edit',
        methods: ['GET', 'POST'],
        requirements: [
            'id'            => '%assert.id%',
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.admin%',
        ],
        defaults: [
            'is_self'       => false,
            'module'        => 'users',
        ],
    )]

    #[Route(
        '/{system}/{role_short}/users/{id}/password-edit-self',
        name: 'users_password_edit_self',
        methods: ['GET', 'POST'],
        requirements: [
            'system'        => '%assert.system%',
            'role_short'    => '%assert.role_short.user%',
        ],
        defaults: [
            'id'            => 0,
            'is_self'       => true,
            'module'        => 'users',
        ],
    )]

    public function __invoke(
        Request $request,
        EncoderFactoryInterface $encoder_factory,
        int $id,
        bool $is_self,
        Db $db,
        AlertService $alert_service,
        FormTokenService $form_token_service,
        LinkRender $link_render,
        MailAddrSystemService $mail_addr_system_service,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        PasswordStrengthService $password_strength_service,
        UserCacheService $user_cache_service,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        $errors = [];

        $password = trim($request->request->get('password', ''));
        $notify = $request->request->get('notify', '');

        if ($is_self)
        {
            $id = $su->id();
        }

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
                        $to = $db->fetchOne('select c.value
                            from ' . $pp->schema() . '.contact c, ' .
                                $pp->schema() . '.type_contact tc
                            where tc.id = c.id_type_contact
                                and tc.abbrev = \'mail\'
                                and c.user_id = ?',
                                [$id], [\PDO::PARAM_INT]);

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

                    if ($is_self)
                    {
                        $link_render->redirect('users_show_self', $pp->ary(), []);
                    }

                    $link_render->redirect('users_show', $pp->ary(), ['id' => $id]);
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

        $notify_lbl = ' Verzend notificatie E-mail met nieuw paswoord. ';

        if ($pp->is_admin())
        {
            $notify_lbl .= 'Dit is enkel mogelijk wanneer de Status ';
            $notify_lbl .= 'actief is en E-mail adres ingesteld.';
        }

        $out .= strtr(BulkCnst::TPL_CHECKBOX, [
            '%name%'        => 'notify',
            '%label%'       => $notify_lbl,
            '%attr%'        => $user['status'] == 1 || $user['status'] == 2 ? ' checked' : ' readonly',
        ]);

        if ($is_self)
        {
            $out .= $link_render->btn_cancel('users_show_self', $pp->ary(), []);
        }
        else
        {
            $out .= $link_render->btn_cancel('users_show', $pp->ary(), ['id' => $id]);
        }

        $out .= '&nbsp;';
        $out .= '<input type="submit" value="Opslaan" name="zend" ';
        $out .= 'class="btn btn-primary btn-lg">';
        $out .= $form_token_service->get_hidden_input();

        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        return $this->render('users/users_password_edit.html.twig', [
            'content'   => $out,
            'is_self'   => $is_self,
            'id'        => $id,
        ]);
    }
}
