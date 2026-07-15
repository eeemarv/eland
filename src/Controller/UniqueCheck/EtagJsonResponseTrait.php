<?php declare(strict_types=1);

namespace App\Controller\UniqueCheck;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait EtagJsonResponseTrait
{
  protected function etagJsonResponse(
    Request $request,
    mixed $data
  ): Response
  {
    $json = json_encode($data);
    $etag = hash('crc32b', $json);

    $response = new Response();
    $response->setContent($json);
    $response->headers->set('Content-Type', 'application/json');
    $response->setEtag($etag);
    $response->setPublic();
    // if etag is the same, removes content and sets 304 Not Modified
    $response->isNotModified($request);

    return $response;
  }
}