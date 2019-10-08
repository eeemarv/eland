<?php declare(strict_types=1);

namespace App\Controller;

use util\app;
use Symfony\Component\HttpFoundation\Response;

class login_elas_token
{
    public function login_elas_token(app $app, string $elas_token):Response
    {
        if($apikey = $app['predis']->get($app['pp_schema'] . '_token_' . $elas_token))
        {
            $s_logins = array_merge($app['s_logins'], [
                $app['pp_schema'] 	=> 'elas',
            ]);

            $app['session']->set('logins', $s_logins);

            $referrer = $app['request']->server->get('HTTP_REFERER');

            if ($referrer !== null)
            {
                // record logins to link the apikeys to domains and systems
                $domain_referrer = strtolower(parse_url($referrer, PHP_URL_HOST));
                $app['xdb']->set('apikey_login', $apikey, [
                    'domain' => $domain_referrer
                ], $app['pp_schema']);
            }

            $app['monolog']->info('eLAS guest login using token ' .
                $elas_token . ' succeeded. referrer: ' . $referrer,
                ['schema' => $app['pp_schema']]);

            return $app['link']->redirect($app['r_default'], [
                'role_short'	=> 'g',
                'system'	    => $app['pp_system'],
                'welcome'	    => '1',
            ], []);
        }

        $app['alert']->error('De interSysteem login is mislukt.');
        $app['link']->redirect('login', $app['pp_ary'], []);

        return new Response('');
    }
}