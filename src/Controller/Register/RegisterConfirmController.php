<?php declare(strict_types=1);

namespace App\Controller\Register;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Queue\MailQueue;
use App\Repository\UserRepository;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\MailAddrSystemService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\StaticContentService;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterConfirmController extends AbstractController
{
    public function __invoke(
        string $token,
        UserRepository $user_repository,
        TranslatorInterface $translator,
        ConfigService $config_service,
        StaticContentService $static_content_service,
        DataTokenService $data_token_service,
        MailAddrSystemService $mail_addr_system_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        MenuService $menu_service
    ):Response
    {
        $data = $data_token_service->retrieve($token, 'register', $pp->schema());

        if (!$data)
        {
            $menu_service->set('register');

            return $this->render('register/register_confirm_fail.html.twig', [
                'schema'    => $pp->schema(),
            ]);
        }

        $data_token_service->del($token, 'register', $pp->schema());

        for ($i = 0; $i < 20; $i++)
        {
            $name = $data['first_name'];

            if ($i)
            {
                $name .= ' ';

                if ($i < strlen($data['last_name']))
                {
                    $name .= substr($data['last_name'], 0, $i);
                }
                else
                {
                    $name .= substr(hash('sha512', $pp->schema() . time() . mt_rand(0, 100000)), 0, 4);
                }
            }

            if ($user_repository->count_by_name($name, $pp->schema()) === 0)
            {
                break;
            }
        }

        $user = [
            'name'			        => $name,
            'fullname'		        => $data['first_name'] . ' ' . $data['last_name'],
            'postcode'		        => $data['postcode'],
            'status'		        => 5,
            'role'	                => 'user',
            'periodic_overview_en'	=> 't',
            'email'                 => strtolower($data['email']),
            'phone'                 => $data['phone'],
            'mobile'                => $data['mobile'],
        ];

        $user_id = $user_repository->register($user, $pp->schema());

        $vars = [
            'user_id'		=> $user_id,
            'postcode'		=> $user['postcode'],
            'email'			=> $data['email'],
        ];

        $mail_queue->queue([
            'schema'		=> $pp->schema(),
            'to' 			=> $mail_addr_system_service->get_admin($pp->schema()),
            'vars'			=> $vars,
            'template'		=> 'register/register_admin',
        ], 8000);

        $success_mail = $static_content_service->get('register_form_confirm', 'success_mail', $pp->schema());

        if ($success_mail)
        {
            $map_template_vars = [
                'voornaam' 			=> 'first_name',
                'achternaam'		=> 'last_name',
                'postcode'			=> 'postcode',
            ];

            foreach ($map_template_vars as $k => $v)
            {
                $vars[$k] = $data[$v];
            }

            $vars['subject'] = $translator->trans('register_success.subject', [
                '%system_name%'	=> $config_service->get('systemname', $pp->schema()),
            ], 'mail');

            $mail_queue->queue([
                'schema'				=> $pp->schema(),
                'to' 					=> [$data['email'] => $user['fullname']],
                'reply_to'				=> $mail_addr_system_service->get_admin($pp->schema()),
                'pre_html_template'		=> $success_mail,
                'template'				=> 'skeleton',
                'vars'					=> $vars,
            ], 8500);
        }

        $menu_service->set('register');

        return $this->render('register/register_confirm_success.html.twig', [
            'schema'    => $pp->schema(),
        ]);
    }
}