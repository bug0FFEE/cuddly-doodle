<?php

namespace Geocoder;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/subwayDB.php';

define('EARTH_RADIUS', '6372797');
define('SEARCH_LIMITATION', '5000');

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * TODO: добавить api key
 * @param $address
 * @param $city
 * @return array ['lat', 'lng']
 */
$getGeocode = function ($address, $city) {
    $url = "https://maps.googleapis.com/maps/api/geocode/json?components=locality:" . urlencode($city) . "&language=ru&address=" . urlencode($address);
    $client = new \GuzzleHttp\Client();
    try {
        $res = $client->request('GET', $url);
        if ($res->getStatusCode() != 200) {
            throw new \Exception('Failed connect to Maps API');
        };
        $res = $res->getBody();
        $content = json_decode($res, true);
        if ($content['status'] != 'OK') {
            throw new \Exception("Maps API error -  " . $content["status"].
                "\n\n\tDetailed error description: https://developers.google.com/maps/documentation/geocoding/intro#StatusCodes \n\n");
        }
    } catch (\Exception $e) {
        echo $e;
        die(1);
    }
    return $content['results'][0]['geometry']['location'];
};


/**
 * @param array $placeGeocode
 * @return array ['name', 'distance', 'time']
 */
$getNearestSubway = function ($placeGeocode) use ($subwayDB) {
    return array_reduce($subwayDB, function ($acc, $subway) use ($placeGeocode) {
        $subwayLat = $subway['lat'];
        $subwayLng = $subway['lng'];
        $subwayName = $subway['name'];
        $placeLat = $placeGeocode['lat'];
        $placeLng = $placeGeocode['lng'];
        $dLat = deg2rad($subwayLat - $placeLat);
        $dLng = deg2rad($subwayLng - $placeLng);
        $d = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($placeLat)) * cos(deg2rad($subwayLat)) * sin($dLng/2) * sin($dLng/2);
        $d = 2 * asin(sqrt($d));
        $d = EARTH_RADIUS * $d;
        if ($d < SEARCH_LIMITATION) {
            $distance = round($d);
            $time = round($d / 60);
            $acc[] = [
                'name' => $subwayName,
                'distance' => $distance,
                'time' => $time,
            ];
            return $acc;
        }
        return $acc;
    }, []);
};



$o = getopt("address:city:");
$geocode = $getGeocode($o['address'], $o['city']);
$nearestSubway = $getNearestSubway($geocode);
print_r($geocode);
print_r($nearestSubway);

/* 
Array
(
    [city] => Санкт-Петербург
    [geocode] => Array
        (
            [lat] => 59.935641