<?php declare(strict_types=1);

namespace App\Controller\PasswordReset;

use App\Command\PasswordReset\PasswordResetSetCommand;
use App\Form\Post\PasswordReset\PasswordResetSetType;
use App\Queue\MailQueue;
use App\Render\LinkRender;
use App\Repository\UserRepository;
use App\Security\User;
use App\Service\AlertService;
use App\Service\DataTokenService;
use App\Service\MailAddrUserService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\PasswordStrengthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordResetSetController extends AbstractController
{
    public function __invoke(
        Request $request,
        EncoderFactoryInterface $encoder_factory,
        UserRepository $user_repository,
        TranslatorInterface $translator,
        string $token,
        DataTokenService $data_token_service,
        AlertService $alert_service,
        LinkRender $link_render,
        MenuService $menu_service,
        MailAddrUserService $mail_addr_user_service,
        MailQueue $mail_queue,
        PasswordStrengthService $password_strength_service,
        PageParamsService $pp
    ):Response
    {
        $data = $data_token_service->retrieve($token, 'password_reset', $pp->schema());
        $password = $request->request->get('password', '');

        if (!$data)
        {
            $alert_service->error($translator->trans('password_reset_set.error.not_valid_anymore', [], 'alert'));
            $link_render->redirect('password_reset_request', $pp->ary(), []);
        }

        $user_id = $data['user_id'];

        $password_reset_set_command = new PasswordResetSetCommand();

        $form = $this->createForm(PasswordResetSetType::class, $password_reset_set_command)
            ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $password_reset_set_command = $form->getData();
            $password = $password_reset_set_command->password;

            $encoder = $encoder_factory->getEncoder(new User());
            $hashed_password = $encoder->encodePassword($password, null);

            $user_repository->set_password($user_id, $hashed_password, $pp->schema());

            $alert_service->success($translator->trans('password_reset_set.success', [], 'alert'));

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


/*



        if ($request->isMethod('POST'))
        {
            if ($error_token = $form_token_service->get_error())
            {
                $alert_service->error($error_token);
            }
            else if (!($password_strength_service->get($password) < 50))
            {

            }
            else
            {
                $alert_service->error('Het paswoord is te zwak.');
            }
        }


        $heading_render->add('Nieuw paswoord ingeven.');
        $heading_render->fa('key');

        $assets_service->add([
            'generate_password.js',
        ]);

        $out = '<div class="card fcard fcard-info">';
        $out .= '<div class="card-body">';

        $out .= '<form method="post" role="form">';

        $out .= '<div class="form-group">';
        $out .= '<label for="password">Nieuw paswoord</label>';
        $out .= '<div class="input-group">';
        $out .= '<span class="input-group-prepend">';
        $out .= '<span class="input-group-text">';
        $out .= '<i class="fa fa-key"></i>';
        $out .= '</span>';
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
        $out .= '</form>';

        $out .= '</div>';
        $out .= '</div>';

*/

        $menu_service->set('login');

        return $this->render('password_reset/password_reset_set.html.twig', [
            'form'      => $form->createView(),
            'schema'    => $pp->schema(),
        ]);
    }
}
