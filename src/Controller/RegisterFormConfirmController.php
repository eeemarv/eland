<?php declare(strict_types=1);

namespace App\Controller;

use App\Cnst\PagesCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Queue\MailQueue;
use App\Render\HeadingRender;
use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\ConfigService;
use App\Service\DataTokenService;
use App\Service\MailAddrSystemService;
use App\Service\MenuService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\StaticContentService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RegisterFormConfirmController extends AbstractController
{
    public function __invoke(
        string $token,
        Db $db,
        HeadingRender $heading_render,
        ConfigService $config_service,
        StaticContentService $static_content_service,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        LinkRender $link_render,
        MailAddrSystemService $mail_addr_system_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        SessionUserService $su,
        MenuService $menu_service
    ):Response
    {
        if (!$config_service->get_bool('register_form.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Register form not enabled.');
        }

        $heading_render->add('Inschrijving voltooid');
        $heading_render->fa('check-square-o');

        if ($pp->edit_enabled()
            && $token === PagesCnst::CMS_TOKEN
            && $su->is_admin())
        {
            $success_content = $static_content_service->get('register_form_confirm', 'success', $pp->schema());

            $menu_service->set('register_form');

            return $this->render('base/navbar.html.twig', [
                'content'   => $success_content,
                'schema'    => $pp->schema(),
            ]);
        }

        $data = $data_token_service->retrieve($token, 'register_form', $pp->schema());

        if (!$data)
        {
            $alert_service->error('Geen geldig token.');

            $out = '<div class="panel panel-danger">';
            $out .= '<div class="panel-heading">';

            $out .= '<h2>Inschrijven niet gelukt</h2>';

            $out .= '</div>';
            $out .= '<div class="panel-body">';

            $out .= $link_render->link('register_form', $pp->ary(),
                [], 'Opnieuw proberen', ['class' => 'btn btn-default']);

            $out .= '</div>';
            $out .= '</div>';

            $menu_service->set('register_form');

            return $this->render('base/navbar.html.twig', [
                'content'   => $out,
                'schema'    => $pp->schema(),
            ]);
        }

        $data_token_service->del($token, 'register_form', $pp->schema());

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

            if ($db->fetchOne('select name
                from ' . $pp->schema() . '.users
                where name = ?',
                [$name], [\PDO::PARAM_STR]) === false)
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
            'hobbies'		        => '',
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
                'user_id'			=> $user_id,
                'access'            => 'admin',
                'value'				=> $data['email'],
                'id_type_contact'	=> $tc['mail'],
            ];

            $db->insert($pp->schema() . '.contact', $mail);

            if ($data['gsm'] || $data['tel'])
            {
                if ($data['gsm'])
                {
                    $gsm = [
                        'user_id'			=> $user_id,
                        'access'            => 'admin',
                        'value'				=> $data['gsm'],
                        'id_type_contact'	=> $tc['gsm'],
                    ];

                    $db->insert($pp->schema() . '.contact', $gsm);
                }

                if ($data['tel'])
                {
                    $tel = [
                        'user_id'			=> $user_id,
                        'access'            => 'admin',
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

        $pre_html_template = $static_content_service->get('register_form_confirm', 'success_mail', $pp->schema());

        $mail_queue->queue([
            'schema'				=> $pp->schema(),
            'to' 					=> [$data['email'] => $user['fullname']],
            'reply_to'				=> $mail_addr_system_service->get_admin($pp->schema()),
            'pre_html_template'		=> $pre_html_template,
            'template'				=> 'register/success',
            'vars'					=> $vars,
        ], 8500);

        $success_content = $static_content_service->get('register_form_confirm', 'success', $pp->schema());

        $menu_service->set('register_form');

        return $this->render('base/navbar.html.twig', [
            'content'   => $success_content,
            'schema'    => $pp->schema(),
        ]);
    }
}
