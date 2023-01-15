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
        $comma_count = substr_count($address, ',');

        if ($comma_count !== 1){
            error_log('Geocode adr: ' . $address . ' malformation comma-count');
            return [];
        }

        [$street, $loc] = explode(',', $address);

        $street_parts = explode(' ', $street);
        $loc_parts = explode(' ', $loc);

        $postal_code = '';
        $locality = '';

        foreach($loc_parts as $lp)
        {
            if ($postal_code !== '')
            {
                $postal_code += $lp;
                continue;
            }
            if ($locality !== '')
            {
                $locality += ' ';
            }
            $locality += $lp;
        }

        $street_name = '';
        $street_number = '';
        $street_bus = '';

        foreach ($street_parts as $st)
        {
            if ($street_name !== '')
            {
                if ($street_number === '')
                {
                    if (ctype_digit(substr($st, 0, 1)))
                    {
                        $street_number = $st;
                        continue;
                    }
                }
                else
                {
                    if ($street_bus !== '')
                    {
                        $street_bus += ' ';
                    }
                    $street_bus += $st;
                    continue;
                }
                $street_name += ' ';
            }

            $street_name += $st;
        }

        error_log('-- GEOCDDE parsed address ---');
        error_log('street_nmae: ' . $street_name);
        error_log('street_number: ' . $street_number);
        error_log('street_bus: ' . $street_bus);
        error_log('postal_code' . $postal_code);
        error_log('locality' . $locality);
        error_log('-- -- -- -- -- -- -- --');

        $provider = new bpost($this->client_interface);
        $geocoder = new StatefulGeocoder($provider, 'nl');
        $geocoder->setLimit(1);

        try
        {
            $query = GeocodeQuery::create($address)
                ->withData('streetNumber', $street_number)
                ->withData('streetName', $street_name)
                ->withData('postalCode', $postal_code)
                ->withData('locality', $locality);
            $addressCollection = $geocoder->geocodeQuery($query);

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
