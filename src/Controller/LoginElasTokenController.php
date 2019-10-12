<?php declare(strict_types=1);

namespace App\Controller;

use App\Render\LinkRender;
use App\Service\AlertService;
use App\Service\PageParamsService;
use App\Service\SessionUserService;
use App\Service\VarRouteService;
use App\Service\XdbService;
use Predis\Client as Predis;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginElasTokenController extends AbstractController
{
    public function login_elas_token(
        string $elas_token,
        Request $request,
        Predis $predis,
        LoggerInterface $logger,
        XdbService $xdb_service,
        AlertService $alert_service,
        PageParamsService $pp,
        SessionUserService $su,
        VarRouteService $vr,
        LinkRender $link_render
    ):Response
    {
        if($apikey = $predis->get($pp->schema() . '_token_' . $elas_token))
        {
            $su->set_elas_guest_login($pp->schema());

            $referrer = $request->server->get('HTTP_REFERER');

            if ($referrer !== null)
            {
                // record logins to link the apikeys to domains and systems
                $domain_referrer = strtolower(parse_url($referrer, PHP_URL_HOST));
                $xdb_service->set('apikey_login', $apikey, [
                    'domain' => $domain_referrer
                ], $pp->schema());
            }

            $logger->info('eLAS guest login using token ' .
                $elas_token . ' succeeded. referrer: ' . $referrer,
                ['schema' => $pp->schema()]);

            return $link_render->redirect($vr->get('default'), [
                'role_short'	=> 'g',
                'system'	    => $pp->system(),
                'welcome'	    => '1',
            ], []);
        }

        $alert_service->error('De interSysteem login is mislukt.');
        $link_render->redirect('login', $pp->ary(), []);

        return new Response('');
    }
}