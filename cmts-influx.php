<?php

use InfluxDB\Driver\Guzzle;

require __DIR__ . '/vendor/autoload.php';

include(__DIR__.'/vendor/influxdb/influxdb-php/src/InfluxDB/Client.php');
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
    }
    $i++;
}

function seperateFields($cmts, $community)
{
    $cmtsMainPoints = prepareMainData($ctms, "cmts_main", $community);

    $cmtsUsPoints = prepareCmtsUsData($ctms, "cmts_us", $community);

    $mergedPoints = array_merge($cmtsMainPoints, $cmtsUsPoints);

    return $mergedPoints;
}

function prepareMainData($cmts, $measurement, $community)
{
    $client = new InfluxDB\Driver\Guzzle(new \GuzzleHttp\Client());

    $response = $client->get("127.0.0.1:9000/?cmtsip=$cmts&community=$community");
    if($response->getStatusCode() != 200) return [];

    $data = json_decode((string)$response->getBody(), true);

    $preparedData = [];
    foreach($data as $single)
    {
        $tags = ['cmmac' => $cmmac, 'id' => (string)$single['index']];

        $content = array_map('doubleval', $single);
        $content['index'] = (string)$single['index'];

        $point = prepareSeperatedPoint($measurement, $tags, $content);

        array_push($preparedData, $point);
    }

    return $preparedData;
}



$total += $i;
echo ("Wrote $total data into influx!\n");

$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
echo " It takes ". $execution_time." seconds to prepare the payload for writing!\n";

$database->writePoints($points, InfluxDB\Database::PRECISION_SECONDS);

echo "Number of modems that had not .json file: ".count($ignored)."\n";
