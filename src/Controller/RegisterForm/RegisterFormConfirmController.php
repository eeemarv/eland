<?php declare(strict_types=1);

namespace App\Controller\RegisterForm;

use App\Cache\ConfigCache;
use App\Cnst\PagesCnst;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Queue\MailQueue;
use App\Service\AlertService;
use App\Service\DataTokenService;
use App\Service\MailAddrSystemService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\StaticContentService;
use Doctrine\DBAL\Connection as Db;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class RegisterFormConfirmController extends AbstractController
{
    #[Route(
        '/{system}/register/{token}',
        name: 'register_form_confirm',
        methods: ['GET'],
        priority: 30,
        requirements: [
            'token'         => '%assert.token%',
            'system'        => '%assert.system%',
        ],
        defaults: [
            'module'        => 'register_form',
        ],
    )]

    public function __invoke(
        string $token,
        Db $db,
        Request $request,
        ConfigCache $config_cache,
        StaticContentService $static_content_service,
        AlertService $alert_service,
        DataTokenService $data_token_service,
        MailAddrSystemService $mail_addr_system_service,
        MailQueue $mail_queue,
        PageParamsService $pp,
        SessionUserService $su
    ):Response
    {
        if (!$config_cache->get_bool('register_form.enabled', $pp->schema()))
        {
            throw new NotFoundHttpException('Register form not enabled.');
        }

        $postcode_enabled = $config_cache->get_bool('users.fields.postcode.enabled', $pp->schema());

        if ($pp->edit_en()
            && $token === PagesCnst::CMS_TOKEN
            && $su->is_admin())
        {
            $fail = $request->query->has('fail');

            $template = 'register_form/register_form_confirm_';
            $template .= $fail ? 'fail' : 'success';
            $template .= '.html.twig';

            return $this->render($template, [
            ]);
        }

        $data = $data_token_service->retrieve($token, 'register_form', $pp->schema());

        if (!$data)
        {
            $alert_service->error('Geen geldig token.');

            return $this->render('register_form/register_form_confirm_fail.html.twig', [
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
            'full_name'		        => $data['full_name'],
            'status'		        => 5,
            'role'	                => 'user',
            'periodic_overview_en'	=> 't',
        ];

        if (isset($data['postcode'])
            && $postcode_enabled)
        {
            $user['postcode'] = $data['postcode'];
        }

        $db->beginTransaction();

        try
        {
            $db->insert($pp->schema() . '.users', $user);

            $user_id = $db->lastInsertId($pp->schema() . '.users_id_seq');

            $tc = [];

            $stmt = $db->prepare('select abbrev, id
                from ' . $pp->schema() . '.type_contact');

            $res = $stmt->executeQuery();

            while($row = $res->fetchAssociative())
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
            'email'			=> $data['email'],
        ];

        if ($postcode_enabled)
        {
            $vars['postcode'] = $user['postcode'];
        }

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

        $pre_html_template = $static_content_service->get(null, 'register_form_confirm', null, 'mail', $pp->schema());

        if ($pre_html_template !== '')
        {
            $mail_queue->queue([
                'schema'				=> $pp->schema(),
                'to' 					=> [new Address($data['email'], $user['full_name'])],
                'reply_to'				=> $mail_addr_system_service->get_admin($pp->schema()),
                'pre_html_template'		=> $pre_html_template,
                'template'				=> 'register/success',
                'vars'					=> $vars,
            ], 8500);
        }

        return $this->render('register_form/register_form_confirm_success.html.twig', [
        ]);
    }
}
