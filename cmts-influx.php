<?php

use InfluxDB\Driver\Guzzle;

require __DIR__ . '/vendor/autoload.php';

include(__DIR__.'/vendor/influxdb/influxdb-php/src/InfluxDB/Client.php');
include(__DIR__.'/vendor/guzzlehttp/guzzle/src/Client.php');
include('helpers.php');

// @ini_set( 'upload_max_size' , '999M' );
// @ini_set( 'post_max_size', '999M');
// @ini_set( 'max_execution_time', '300' );

date_default_timezone_set("Europe/Belgrade");
$start_time = microtime(true);

$client = new InfluxDB\Client(env("host"), env("port"), env("username"), env("password"));

$dbname = env("cmts_database");
$community = env("cmts_community");
// // set the UDP driver in the client
// $client->setDriver(new \InfluxDB\Driver\UDP($client->getHost(), 8089));

$database = $client->selectDB($dbname);

$cmtses = scanDirectory('/home/albismart/cmtsOnline');

$total = 0;
$pointsTotal = 0;
$i = 1;
$ignored = [];
$points = [];
foreach($cmtses as $cmts)
{
    $cmts = str_replace(".json", "", trim($cmts));
    if(empty($cmts)) continue;

    $preparedData = seperateFields($cmts, $community);

    $points = array_merge($points, $preparedData);

    if(count($points) >= 10000) {
        $database->writePoints($points, InfluxDB\Database::PRECISION_SECONDS);
        $pointsTotal += count($points);
        $total += $i;
        echo ("Wrote $total modems into influx!\n");
        echo ("Wrote $pointsTotal points into influx!\n\n");
        $points = [];
        $i = 1;
    } else {
        $i += count($preparedData);
    }
}

function seperateFields($cmts, $community)
{
    $cmtsTrafficPoints = prepareTrafficData($cmts, "cmts_traffic", $community);

    $cmtsUsPoints = prepareCmtsUsData($cmts, "cmts_us", $community);

    $mergedPoints = array_merge($cmtsTrafficPoints, $cmtsUsPoints);

    return $mergedPoints;
}

function prepareTrafficData($cmts, $measurement, $community)
{
    $client = new GuzzleHttp\Client();

    try {
        $response = $client->get("127.0.0.1:9000/traffic?cmtsip=$cmts&community=$community");
    } catch (\Throwable $e) {return [];}

    if($response->getStatusCode() != 200) return [];

    $data = json_decode((string)$response->getBody(), true);

    $preparedData = [];
    foreach ($data as $cmtsIp => $dataContent) {
        unset($dataContent["timestamp"],$dataContent["FNs"],$dataContent["sysDescr"],$dataContent["sysName"]);

        foreach($dataContent as $key => $single)
        {
            $id = (string)$key;
            $tags = ['cmts' => $cmts, 'id' => $id];

            $content['ifHCInOctets'] = isset($single['ifHCInOctets']) ? ((int)$single['ifHCInOctets']) : 0;
            $content['ifHCOutOctets'] = isset($single['ifHCOutOctets']) ? ((int)$single['ifHCOutOctets']) : 0;
            $content['index'] = $id;

            $point = prepareSeperatedPoint($measurement, $tags, $content);

            $preparedData[] = $point;
        }
    }

    return $preparedData;
}

function prepareCmtsUsData($cmts, $measurement, $community)
{
    $client = new GuzzleHttp\Client();

    try {
        $response = $client->get("127.0.0.1:9000/cmts_us?cmtsip=$cmts&community=$community");
    } catch (\Throwable $e) {return [];}
    if($response->getStatusCode() != 200) return [];

    $data = json_decode((string)$response->getBody(), true);

    $preparedData = [];
    foreach ($data as $cmtsIp => $dataContent) {
        unset($dataContent["timestamp"]);

        foreach($dataContent as $key => $single)
        {
            $id = (string)$key;
            $tags = ['cmts' => $cmts, 'id' => $id];

            $content['index'] = $id;
            $content["docsIfSigQUnerroreds"] = (int)$single["docsIfSigQUnerroreds"];
            $content["docsIfSigQCorrecteds"] = (int)$single["docsIfSigQCorrecteds"];
            $content["docsIfSigQUncorrectables"] = (int)$single["docsIfSigQUncorrectables"];
            $content["docsIfSigQSignalNoise"] = (int)$single["docsIfSigQSignalNoise"];
            $content["docsIfUpChannelWidth"] = (int)$single["docsIfUpChannelWidth"];
            $content["docsIfUpChannelModulationProfile"] = (int)$single["docsIfUpChannelModulationProfile"];
            $content["docsIfUpChannelFrequency"] = (int)$single["docsIfUpChannelFrequency"];

            $point = prepareSeperatedPoint($measurement, $tags, $content);

            array_push($preparedData, $point);
        }
    }

    return $preparedData;
}

function prepareSeperatedPoint($measurement, $tags, $fields)
{
    $point = new InfluxDB\Point(
        $measurement, // name of the measurement
        null, // the measurement value
        $tags, // optional tags
        $fields, // optional additional fields
        time());
    return $point;
}


$total += $i;
echo ("Wrote $total data into influx!\n");

$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
echo " It takes ". $execution_time." seconds to prepare the payload for writing!\n";

$database->writePoints($points, InfluxDB\Database::PRECISION_SECONDS);

echo "Number of modems that had not .json file: ".count($ignored)."\n";
