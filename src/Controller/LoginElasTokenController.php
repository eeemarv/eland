<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\XdbService;
use Predis\Client as Predis;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class LoginElasTokenController extends AbstractController
{
    public function login_elas_token(
        string $elas_token,
        Request $request,
        Predis $predis,
        LoggerInterface $logger,
        Session $session,
        XdbService $xdb_service,
        AlertService $alert_service,
        LinkRender $link_render
    ):Response
    {
        if($apikey = $predis->get($app['pp_schema'] . '_token_' . $elas_token))
        {
            $s_logins = array_merge($app['s_logins'], [
                $app['pp_schema'] 	=> 'elas',
            ]);

            $session->set('logins', $s_logins);

            $referrer = $request->server->get('HTTP_REFERER');

            if ($referrer !== null)
            {
                // record logins to link the apikeys to domains and systems
                $domain_referrer = strtolower(parse_url($referrer, PHP_URL_HOST));
                $xdb_service->set('apikey_login', $apikey, [
                    'domain' => $domain_referrer
                ], $app['pp_schema']);
            }

            $logger->info('eLAS guest login using token ' .
                $elas_token . ' succeeded. referrer: ' . $referrer,
                ['schema' => $app['pp_schema']]);

            return $link_render->redirect($app['r_default'], [
                'role_short'	=> 'g',
                'system'	    => $app['pp_system'],
                'welcome'	    => '1',
            ], []);
        }

        $alert_service->error('De interSysteem login is mislukt.');
        $link_render->redirect('login', $app['pp_ary'], []);

        return new Response('');
    }
}