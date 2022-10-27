<?php declare(strict_types=1);

namespace App\Service;

use Geocoder\Query\GeocodeQuery;
use Psr\Http\Client\ClientInterface;
use Geocoder\Provider\bpost\bpost;
use Geocoder\StatefulGeocoder;

class GeocodeService
{
    public function __construct(
        protected ClientInterface $client_interface
    )
    {
    }

    public function getCoordinates(string $address):array
    {
        $provider = new bpost($this->client_interface);
        $geocoder = new StatefulGeocoder($provider, 'nl');
        $geocoder->setLimit(1);

        try
        {
            $addressCollection = $geocoder->geocodeQuery(GeocodeQuery::create($address));

            if (is_object($addressCollection))
            {
                $location = $addressCollection->first();

                $coordinates = $location->getCoordinates();

                $ary = [
                    'lat'	=> $coordinates->getLatitude(),
                    'lng'	=> $coordinates->getLongitude(),
                ];

                return $ary;
            }

            return [];
        }

        catch (\Exception $e)
		{
			error_log('Geocode adr: ' . $address . ' exception: ' . $e->getMessage());
			return [];
		}
    }
}
