<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection as Db;
use Psr\Log\LoggerInterface;

class ElasSoapStatusController extends AbstractController
{
    public function elas_soap_status(
        int $group_id,
        Db $db,
        LoggerInterface $logger
    ):Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/plain');

        if (!$su->schema())
        {
            $response->setContent('Onvoldoende rechten');
            $response->setStatusCode(403);
            return $response;
        }

        $group = $db->fetchAssoc('select *
            from ' . $su->schema() . '.letsgroups
            where id = ?', [$group_id]);

        if (!$group)
        {
            $response->setContent('InterSysteem niet gevonden.');
            $response->setStatusCode(404);
            return $response;
        }

        if ($group['apimethod'] != 'elassoap')
        {
            $response->setContent('De apimethod voor dit interSysteem is niet elassoap.');
            $response->setStatusCode(404);
            return $response;
        }

        if (!$group['remoteapikey'])
        {
            $response->setContent('De Remote Apikey is niet ingesteld voor dit interSysteem.');
            $response->setStatusCode(404);
            return $response;
        }

        $soapurl = ($group['elassoapurl']) ? $group['elassoapurl'] : $group['url'] . '/soap';
        $soapurl = $soapurl . '/wsdlelas.php?wsdl';

        $apikey = $group['remoteapikey'];

        $client = new \nusoap_client($soapurl, true);

        $err = $client->getError();

        if ($err)
        {
            $m = 'Kan geen verbinding maken.';

            $logger->error('elas-token: ' . $m . ' ' . $err,
                ['schema' => $pp->schema()]);

            $response->setContent($m);
            $response->setStatusCode(404);
            return $response;
        }

        $message = $client->call('getstatus', ['apikey' => $apikey]);

        $err = $client->getError();

        if ($err)
        {
            $m = 'Kan geen status verkrijgen. ' . $err;

            $logger->error('elas-token: ' . $m . ' ' . $err,
                ['schema' => $pp->schema()]);

            $response->setContent($m);
            $response->setStatusCode(404);
            return $response;
        }

        $response->setContent($message);
        return $response;
    }
}
