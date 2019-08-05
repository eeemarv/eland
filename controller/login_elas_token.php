<?php declare(strict_types=1);

namespace controller;

use util\app;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class login_elas_token
{
    public function get(app $app, string $elas_token):Response
    {
        if($apikey = $app['predis']->get($app['tschema'] . '_token_' . $elas_token))
        {
            $s_logins = array_merge($app['s_logins'], [
                $app['tschema'] 	=> 'elas',
            ]);

            $app['session']->set('logins', $s_logins);
            $app['session']->set('schema', $app['tschema']);

            $referrer = $app['request']->server->get('HTTP_REFERER');

            if ($referrer !== null)
            {
                // record logins to link the apikeys to domains and systems
                $domain_referrer = strtolower(parse_url($referrer, PHP_URL_HOST));
                $app['xdb']->set('apikey_login', $apikey, [
                    'domain' => $domain_referrer
                ], $app['tschema']);
            }

            $app['monolog']->info('eLAS guest login using token ' .
                $elas_token . ' succeeded. referrer: ' . $referrer,
                ['schema' => $app['tschema']]);

            $location = $app['config']->get('default_landing_page', $app['tschema']);

            return $app['link']->redirect($location, [
                'welcome'	    => '1',
                'role_short'	=> 'g',
                'system'	    => $app['pp_system'],
            ]);
        }

        $app['alert']->error('De interSysteem login is mislukt.');
        return $app['link']->redirect('login', $app['pp_ary'], []);
    }
}
