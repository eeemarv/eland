<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\IntersystemsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class ElasGroupLoginController extends AbstractController
{
    public function elas_group_login(
        int $group_id,
        Db $db,
        LoggerInterface $logger,
        IntersystemsService $intersystems_service
    ):Response
    {
        if (!$app['s_schema'] || $app['s_elas_guest'])
        {
            return $this->json([
                'error' => 'Onvoldoende rechten.',
            ], 403);
        }

        if (!$app['intersystem_en'])
        {
            return $this->json([
                'error' => 'InterSysteem verbindingen zijn niet ingeschakeld in het eigen Systeem.',
            ], 404);
        }

        $elas_intersystems = $intersystems_service->get_elas($app['s_schema']);

        if (!isset($elas_intersystems[$group_id]))
        {
            return $this->json([
                'error' => 'Er is geen interSysteem verbinding met dit Systeem.',
            ], 404);
        }

        $group = $db->fetchAssoc('select *
            from ' . $app['s_schema'] . '.letsgroups
            where id = ?', [$group_id]);

        if (!$group)
        {
            return $this->json([
                'error' => 'InterSysteem niet gevonden.',
            ], 404);
        }

        if ($group['apimethod'] != 'elassoap')
        {
            return $this->json([
                'error' => 'De Api Methode voor dit interSysteem is niet elassoap.',
            ], 404);
        }

        if (!$group['remoteapikey'])
        {
            return $this->json([
                'error' => 'De Remote Apikey is niet ingesteld voor dit interSysteem.',
            ], 404);
        }

        if (!$group['presharedkey'])
        {
            return $this->json([
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

            $logger->error('elas-token: ' . $m . ' ' . $err,
                ['schema' => $app['pp_schema']]);

            return $this->json([
                'error' => $m,
            ], 503);
        }

        $token = $client->call('gettoken', ['apikey' => $apikey]);

        $err = $client->getError();

        if ($err)
        {
            $m = 'Kan geen token krijgen voor dit interSysteem.';

            $logger->error('elas-token: ' . $m . ' ' . $err,
                ['schema' => $app['pp_schema']]);

            return $this->json([
                'error' => $m,
            ], 503);
        }

        return $this->json([
            'login_url'	=> $group['url'] . '/login.php?token=' . $token,
        ]);
    }
}
