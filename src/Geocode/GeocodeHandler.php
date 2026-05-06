<?php declare(strict_types=1);

namespace App\Geocode;

use App\Repository\ContactRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeocodeHandler
{
  public function __construct(
    private readonly ContactRepository $contact_repository,
    private readonly HttpClientInterface $http_client,
    private readonly LoggerInterface $logger,
    #[Autowire('%env(MAIL_HOSTER_ADDRESS)%')]
    private readonly string $env_mail_hoster_address,
  )
  {
  }

  public function __invoke(
    GeocodeMessage $message
  )
  {
    $schema = $message->schema;
    $contact_id = $message->contact_id;

    $contact = $this->contact_repository->get_address(
      contact_id: $contact_id,
      schema: $schema,
    );

    if (!$contact)
    {
      $this->logger->debug('Contact not found: ' . $contact_id, [
        'schema' => $schema->str(),
        'contact_id' => $contact_id,
      ]);
      return;
    }

    if (isset($contact['latitude'])
      && isset($contact['longitude'])
      && isset($contact['geocoded_at'])
    )
    {
      $this->logger->debug('Address {address} already geocoded, contact_id {contact_id}', [
        'schema' => $schema->str(),
        'contact_id' => $contact_id,
        'address' => $contact['value'],
      ]);
      return;
    }

    try {
      $response = $this->http_client->request('GET', 'https://nominatim.openstreetmap.org/search', [
        'query' => [
          'q' => $contact['value'],
          'format' => 'json',
          'limit' => 1,
        ],
        'headers' => [
          'Accept' => 'application/json',
          'User-Agent' => 'eeemarv/eland web app (' . $this->env_mail_hoster_address . ')',
        ],
        'timeout' => 5, // exception if no reply from API after 5 sec
      ]);

      $data = $response->toArray(); // throws HttpParseException or TransportException on errors

      if (empty($data))
      {
        // No result? Do NOT throw an exception,
        // trying again does not help for an unknown address.
        $this->logger->info('No coordinates found for address: {address} of contact_id {contact_id}', [
          'address' => $contact['value'],
          'contact_id' => $contact_id,
          'schema' => $schema->str(),
        ]);

        $this->contact_repository->set_address_geocode_failed(
          contact_id: $contact_id,
          schema: $schema,
        );

        return;
      }

      if (isset($data[0]['lat']) && isset($data[0]['lon']))
      {
        $this->contact_repository->set_address_geocoded(
          contact_id: $contact_id,
          latitude: $data[0]['lat'],
          longitude: $data[0]['lon'],
          schema: $schema,
        );
        $this->logger->info('Geocoded address {address} of contact_id {contact_id}', [
          'address' => $contact['value'],
          'contact_id' => $contact_id,
          'schema' => $schema->str(),
        ]);
        return;
      }

      $this->logger->error('Invalid coordinates found for address: {address} of contact_id {contact_id}', [
        'address' => $contact['value'],
        'contact_id' => $contact_id,
        'schema' => $schema->str(),
        'data' => $data,
      ]);

    } catch (\Exception $e) {
      $this->logger->error('Geocoding error for contact_id {contact_id}: {message}', [
        'contact_id' => $message->contact_id,
        'message' => $e->getMessage(),
        'schema' => $schema->str(),
      ]);
      // Network error? Throw exception to invoke a 'retry'
      throw $e;
    }
  }
}
