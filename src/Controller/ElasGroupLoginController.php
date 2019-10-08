<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ElasGroupLoginController extends AbstractController
{
    public function elas_group_login(app $app, int $group_id):Response
    {
        if (!$app['s_schema'] || $app['s_elas_guest'])
        {
            return $app->json([
                'error' => 'Onvoldoende rechten.',
            ], 403);
        }

        if (!$app['intersystem_en'])
        {
            return $app->json([
                'error' => 'InterSysteem verbindingen zijn niet ingeschakeld in het eigen Systeem.',
            ], 404);
        }

        $elas_intersystems = $app['intersystems']->get_elas($app['s_schema']);

        if (!isset($elas_intersystems[$group_id]))
        {
            return $app->json([
                'error' => 'Er is geen interSysteem verbinding met dit Systeem.',
            ], 404);
        }

        $group = $app['db']->fetchAssoc('select *
            from ' . $app['s_schema'] . '.letsgroups
            where id = ?', [$group_id]);

        if (!$group)
        {
            return $app->json([
                'error' => 'InterSysteem niet gevonden.',
            ], 404);
        }

        if ($group['apimethod'] != 'elassoap')
        {
            return $app->json([
                'error' => 'De Api Methode voor dit interSysteem is niet elassoap.',
            ], 404);
        }

        if (!$group['remoteapikey'])
        {
            return $app->json([
                'error' => 'De Remote Apikey is niet ingesteld voor dit interSysteem.',
            ], 404);
        }

        if (!$group['presharedkey'])
        {
            return $app->json([
                'error' => 'De Preshared Key is niet ingesteld voor dit interSysteem.',
            ], 404);
        }

        $soapurl = $group['elassoapurl'] ? $group['elassoapurl'] : $group['url'] . '/soap';
        $soapurl = $soapurl . '/wsdlelas.php?wsdl';

        $apikey = $group['remoteapikey'];

        $client = new \nusoap_client($soapurl, true);

        $err = $client->getError();

        if ($err)
        {
            $m = 'Kan geen verbinding maken.';

            $app['monolog']->error('elas-token: ' . $m . ' ' . $err,
                ['schema' => $app['pp_schema']]);

            return $app->json([
                'error' => $m,
            ], 503);
        }

        $token = $client->call('gettoken', ['apikey' => $apikey]);

        $err = $client->getError();

        if ($err)
        {
            $m = 'Kan geen token krijgen voor dit interSysteem.';

            $app['monolog']->error('elas-token: ' . $m . ' ' . $err,
                ['schema' => $app['pp_schema']]);

            return $app->json([
                'error' => $m,
            ], 503);
        }

        return $app->json([
            'login_url'	=> $group['url'] . '/login.php?token=' . $token,
        ]);
    }
}
