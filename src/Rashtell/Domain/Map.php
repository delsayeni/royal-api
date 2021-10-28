<?php

trait Map
{
    private $baseUrl = 'https://maps.googleapis.com/maps/api';

    public function getCoordinates($address)
    {
        $address_found = false;
        $apiKey = $_ENV["MAP_KEY"];
        try {
            $geo = file_get_contents($this->baseUrl . '/geocode/json?address=' . urlencode($address) . '&sensor=false&key=' . $apiKey);
            $geo = json_decode($geo, true); // Convert the JSON to an array

            if (isset($geo['status']) && ($geo['status'] == 'OK')) {
                $latitude = $geo['results'][0]['geometry']['location']['lat']; // Latitude
                $longitude = $geo['results'][0]['geometry']['location']['lng']; // Longitude
                $address_found = true;
            }
        } catch (\Exception $e) {
            $address_found = false;
            $longitude = null;
            $latitude = null;
        }
        return [$address_found, $latitude, $longitude];
    }
}
