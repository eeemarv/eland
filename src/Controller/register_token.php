<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;
use cnst\access as cnst_access;

class register_token
{
    public function register_token(app $app, string $token):Response
    {
        if (!$app['config']->get('registration_en', $app['pp_schema']))
        {
            $app['alert']->warning('De inschrijvingspagina is niet ingeschakeld.');
            $app['link']->redirect('login', $app['pp_ary'], []);
        }

        $data = $app['data_token']->retrieve($token, 'register', $app['pp_schema']);

        if (!$data)
        {
            $app['alert']->error('Geen geldig token.');

            $out = '<div class="panel panel-danger">';
            $out .= '<div class="panel-heading">';

            $out .= '<h2>Registratie niet gelukt</h2>';

            $out .= '</div>';
            $out .= '<div class="panel-body">';

            $out .= $app['link']->link('register', $app['pp_ary'],
                [], 'Opnieuw proberen', ['class' => 'btn btn-default']);

            $out .= '</div>';
            $out .= '</div>';

            $app['menu']->set('register');

            return $app->render('base/navbar.html.twig', [
                'content'   => $out,
                'schema'    => $app['pp_schema'],
            ]);
        }

        $app['data_token']->del($token, 'register', $app['pp_schema']);

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
                    $name .= substr(hash('sha512', $app['pp_schema'] . time() . mt_rand(0, 100000)), 0, 4);
                }
            }

            if (!$app['db']->fetchColumn('select name
                from ' . $app['pp_schema'] . '.users
                where name = ?', [$name]))
            {
                break;
            }
        }

        $minlimit = $app['config']->get('preset_minlimit', $app['pp_schema']);
        $minlimit = $minlimit === '' ? -999999999 : $minlimit;

        $maxlimit = $app['config']->get('preset_maxlimit', $app['pp_schema']);
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

        $app['db']->beginTransaction();

        try
        {
            $app['db']->insert($app['pp_schema'] . '.users', $user);

            $user_id = $app['db']->lastInsertId($app['pp_schema'] . '.users_id_seq');

            $tc = [];

            $rs = $app['db']->prepare('select abbrev, id
                from ' . $app['pp_schema'] . '.type_contact');

            $rs->execute();

            while($row = $rs->fetch())
            {
                $tc[$row['abbrev']] = $row['id'];
            }

            $data['email'] = strtolower($data['email']);

            $mail = [
                'id_user'			=> $user_id,
                'flag_public'		=> cnst_access::TO_FLAG_PUBLIC['admin'],
                'value'				=> $data['email'],
                'id_type_contact'	=> $tc['mail'],
            ];

            $app['db']->insert($app['pp_schema'] . '.contact', $mail);

            if ($data['gsm'] || $data['tel'])
            {
                if ($data['gsm'])
                {
                    $gsm = [
                        'id_user'			=> $user_id,
                        'flag_public'		=> cnst_access::TO_FLAG_PUBLIC['admin'],
                        'value'				=> $data['gsm'],
                        'id_type_contact'	=> $tc['gsm'],
                    ];

                    $app['db']->insert($app['pp_schema'] . '.contact', $gsm);
                }

                if ($data['tel'])
                {
                    $tel = [
                        'id_user'			=> $user_id,
                        'flag_public'		=> cnst_access::TO_FLAG_PUBLIC['admin'],
                        'value'				=> $data['tel'],
                        'id_type_contact'	=> $tc['tel'],
                    ];

                    $app['db']->insert($app['pp_schema'] . '.contact', $tel);
                }
            }
            $app['db']->commit();
        }
        catch (\Exception $e)
        {
            $app['db']->rollback();
            throw $e;
        }

        $vars = [
            'user_id'		=> $user_id,
            'postcode'		=> $user['postcode'],
            'email'			=> $data['email'],
        ];

        $app['queue.mail']->queue([
            'schema'		=> $app['pp_schema'],
            'to' 			=> $app['mail_addr_system']->get_admin($app['pp_schema']),
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
            '%system_name%'	=> $app['config']->get('systemname', $app['pp_schema']),
        ], 'mail');

        $app['queue.mail']->queue([
            'schema'				=> $app['pp_schema'],
            'to' 					=> [$data['email'] => $user['fullname']],
            'reply_to'				=> $app['mail_addr_system']->get_admin($app['pp_schema']),
            'pre_html_template'		=> $app['config']->get('registration_success_mail', $app['pp_schema']),
            'template'				=> 'skeleton',
            'vars'					=> $vars,
        ], 8500);

        $app['alert']->success('Inschrijving voltooid.');

        $registration_success_text = $app['config']->get('registration_success_text', $app['pp_schema']);

        $app['menu']->set('register');

        return $app->render('base/sidebar.html.twig', [
            'content'   => $registration_success_text ?: '',
            'schema'    => $app['pp_schema'],
        ]);
    }
}
