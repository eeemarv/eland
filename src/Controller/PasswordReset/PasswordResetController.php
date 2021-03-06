<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use App\Queue\MailQueue;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\DataTokenService;
use App\Service\FormTokenService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\Routing\Annotation\Route;

class PasswordResetController extends AbstractController
{
    #[Route(
        '/{system}/password-reset',
        name: 'password_reset',
        methods: ['GET', 'POST'],
        priority: 30,
        requirements: [
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'users',
            'sub_module'    => 'password_reset',
        ],
    )]

    public function __invoke(
        Request $request,
        Db $db,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        FormTokenService $form_token_service,
        MailQueue $mail_queue,
        LinkRender $link_render,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $errors = [];

        if ($request->isMethod('POST'))
        {
            $email = $request->request->get('email');

            if ($error_token = $form_token_service->get_error())
            {
                $errors[] = $error_token;
            }

            if(!$email)
            {
                $errors[] = 'Geef een E-mail adres op';
            }

            if (!count($errors))
            {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                {
                    $errors[] =  $email . ' is geen geldig email adres.';
                }
            }

            if (!count($errors))
            {
                $users = $db->fetchAllAssociative('select u.id, u.name, u.code
                    from ' . $pp->schema() . '.contact c, ' .
                        $pp->schema() . '.type_contact tc, ' .
                        $pp->schema() . '.users u
                    where c. value = ?
                        and tc.id = c.id_type_contact
                        and tc.abbrev = \'mail\'
                        and c.user_id = u.id
                        and u.status in (1, 2)',
                    [$email], [\PDO::PARAM_STR]);

                if (!count($users))
                {
                    $errors[] = 'E-Mail adres niet bekend';
                }

                if (count($users) > 1)
                {
                    $errors[] = 'Het E-Mail adres is niet uniek in dit Systeem.';
                }
            }

            if (!count($errors))
            {
                $user = reset($users);

                $user_id = $user['id'];

                $token = $data_token_service->store([
                    'user_id'	=> $user_id,
                    'email'		=> $email,
                ], 'password_reset', $pp->schema(), 86400);

                $mail_queue->queue([
                    'schema'	=> $pp->schema(),
                    'to' 		=> [$email => $user['code'] . ' ' . $user['name']],
                    'template'	=> 'password_reset/confirm',
                    'vars'		=> [
                        'token'			=> $token,
                        'user_id'		=> $user_id,
                    ],
                ], 10000);

                $alert_service->success('Een link om je paswoord te resetten werd
                    naar je E-mailbox verzonden. Deze link blijft 24 uur geldig.');

                $link_render->redirect('login', $pp->ary(), []);
            }

            $alert_service->error($errors);
        }

        $out = '<div class="panel panel-info">';
        $out .= '<div class="panel-heading">';

        $out .= '<form method="post">';

        $out .= '<div class="form-group">';
        $out .= '<label for="email" class="control-label">Je E-mail adres</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-addon">';
        $out .= '<i class="fa fa-envelope-o"></i>';
        $out .= '</span>';
        $out .= '<input type="email" class="form-control" id="email" name="email" ';
        $out .= 'value="';
        $out .= $email ?? '';
        $out .= '" required>';
        $out .= '</div>';
        $out .= '<p>';
        $out .= 'Vul hier het E-mail adres in waarmee je geregistreerd staat in het Systeem. ';
        $out .= 'Een link om je paswoord te resetten wordt naar je E-mailbox gestuurd.';
        $out .= '</p>';
        $out .= '</div>';

        $out .= '<input type="submit" class="btn btn-info btn-lg" value="Reset paswoord" name="zend">';
        $out .= $form_token_service->get_hidden_input();
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

        $menu_service->set('login');

        return $this->render('password_reset/password_reset.html.twig', [
            'content'   => $out,
        ]);
    }
}
