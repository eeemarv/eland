<?php declare(strict_types=1);

namespace App\Service;

use Geocoder\Query\GeocodeQuery;
use Http\Adapter\Guzzle6\Client as HttpClient;
use Geocoder\Provider\bpost\bpost;
use Geocoder\StatefulGeocoder;

class GeocodeService
{
    protected StatefulGeocoder $geocoder;

    public function __construct()
    {
        $http_client = new HttpClient();
        $provider = new bpost($http_client);
        $this->geocoder = new StatefulGeocoder($provider, 'nl');
        $this->geocoder->setLimit(1);
    }

    public function getCoordinates(string $address):array
    {
        try
        {
            $addressCollection = $this->geocoder->geocodeQuery(GeocodeQuery::create($address));

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
