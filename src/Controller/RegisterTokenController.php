<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Cnst\AccessCnst;
use App\Queue\MailQueue;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\MenuService;
use Doctrine\DBAL\Connection as Db;

class RegisterTokenController extends AbstractController
{
    public function register_token(
        string $token,
        Db $db,
        ConfigService $config_service,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        LinkRender $link_render,
        MailQueue $mail_queue,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get('registration_en', $pp->schema()))
        {
            $alert_service->warning('De inschrijvingspagina is niet ingeschakeld.');
            $link_render->redirect('login', $pp->ary(), []);
        }

        $data = $data_token_service->retrieve($token, 'register', $pp->schema());

        if (!$data)
        {
            $alert_service->error('Geen geldig token.');

            $out = '<div class="panel panel-danger">';
            $out .= '<div class="panel-heading">';

            $out .= '<h2>Registratie niet gelukt</h2>';

            $out .= '</div>';
            $out .= '<div class="panel-body">';

            $out .= $link_render->link('register', $pp->ary(),
                [], 'Opnieuw proberen', ['class' => 'btn btn-default']);

            $out .= '</div>';
            $out .= '</div>';

            $menu_service->set('register');

            return $this->render('base/navbar.html.twig', [
                'content'   => $out,
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

            if (!$db->fetchColumn('select name
                from ' . $pp->schema() . '.users
                where name = ?', [$name]))
            {
                break;
            }
        }

        $minlimit = $config_service->get('preset_minlimit', $pp->schema());
        $minlimit = $minlimit === '' ? -999999999 : $minlimit;

        $maxlimit = $config_service->get('preset_maxlimit', $pp->schema());
        $maxlimit = $maxlimit === '' ? 999999999 : $maxlimit;

        $user = [
            'name'			=> $name,
            'fullname'		=> $data['first_name'] . ' ' . $data['last_name'],
            'postcode'		=> $data['postcode'],
        //			'letscode'		=> '',
            'login'			=> sha1(microtime()),
            'minlimit'		=> $minlimit,
            'maxlimit'		=> $maxlimit,
            'status'		=> 5,
            'accountrole'	=> 'user',
            'cron_saldo'	=> 't',
            'lang'			=> 'nl',
            'hobbies'		=> '',
            'cdate'			=> gmdate('Y-m-d H:i:s'),
        ];

        $db->beginTransaction();

        try
        {
            $db->insert($pp->schema() . '.users', $user);

            $user_id = $db->lastInsertId($pp->schema() . '.users_id_seq');

            $tc = [];

            $rs = $db->prepare('select abbrev, id
                from ' . $pp->schema() . '.type_contact');

            $rs->execute();

            while($row = $rs->fetch())
            {
                $tc[$row['abbrev']] = $row['id'];
            }

            $data['email'] = strtolower($data['email']);

            $mail = [
                'id_user'			=> $user_id,
                'flag_public'		=> AccessCnst::TO_FLAG_PUBLIC['admin'],
                'value'				=> $data['email'],
                'id_type_contact'	=> $tc['mail'],
            ];

            $db->insert($pp->schema() . '.contact', $mail);

            if ($data['gsm'] || $data['tel'])
            {
                if ($data['gsm'])
                {
                    $gsm = [
                        'id_user'			=> $user_id,
                        'flag_public'		=> AccessCnst::TO_FLAG_PUBLIC['admin'],
                        'value'				=> $data['gsm'],
                        'id_type_contact'	=> $tc['gsm'],
                    ];

                    $db->insert($pp->schema() . '.contact', $gsm);
                }

                if ($data['tel'])
                {
                    $tel = [
                        'id_user'			=> $user_id,
                        'flag_public'		=> AccessCnst::TO_FLAG_PUBLIC['admin'],
                        'value'				=> $data['tel'],
                        'id_type_contact'	=> $tc['tel'],
                    ];

                    $db->insert($pp->schema() . '.contact', $tel);
                }
            }
            $db->commit();
        }
        catch (\Exception $e)
        {
            $db->rollback();
            throw $e;
        }

        $vars = [
            'user_id'		=> $user_id,
            'postcode'		=> $user['postcode'],
            'email'			=> $data['email'],
        ];

        $mail_queue->queue([
            'schema'		=> $pp->schema(),
            'to' 			=> $mail_addr_system_service->get_admin($pp->schema()),
            'vars'			=> $vars,
            'template'		=> 'register/admin',
        ], 8000);

        $map_template_vars = [
            'voornaam' 			=> 'first_name',
            'achternaam'		=> 'last_name',
            'postcode'			=> 'postcode',
        ];

        foreach ($map_template_vars as $k => $v)
        {
            $vars[$k] = $data[$v];
        }

        $vars['subject'] = $app['translator']->trans('register_success.subject', [
            '%system_name%'	=> $config_service->get('systemname', $pp->schema()),
        ], 'mail');

        $mail_queue->queue([
            'schema'				=> $pp->schema(),
            'to' 					=> [$data['email'] => $user['fullname']],
            'reply_to'				=> $mail_addr_system_service->get_admin($pp->schema()),
            'pre_html_template'		=> $config_service->get('registration_success_mail', $pp->schema()),
            'template'				=> 'skeleton',
            'vars'					=> $vars,
        ], 8500);

        $alert_service->success('Inschrijving voltooid.');

        $registration_success_text = $config_service->get('registration_success_text', $pp->schema());

        $menu_service->set('register');

        return $this->render('base/sidebar.html.twig', [
            'content'   => $registration_success_text ?: '',
            'schema'    => $pp->schema(),
        ]);
    }
}
