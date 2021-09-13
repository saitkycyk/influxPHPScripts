<?php

require __DIR__ . '/vendor/autoload.php';

include(__DIR__.'/vendor/influxdb/influxdb-php/src/InfluxDB/Client.php');
include('helpers.php');

// @ini_set( 'upload_max_size' , '999M' );
// @ini_set( 'post_max_size', '999M');
// @ini_set( 'max_execution_time', '300' );

date_default_timezone_set("Europe/Belgrade");
$start_time = microtime(true);

$client = new InfluxDB\Client(env("host"), env("port"), env("username"), env("password"));

$dbname = env("content_database");

// // set the UDP driver in the client
// $client->setDriver(new \InfluxDB\Driver\UDP($client->getHost(), 8089));

$database = $client->selectDB($dbname);

$cmtses = scanDirectory('/home/albismart/cmtsOnline');

$modems = [];
foreach($cmtses as $cmts)
{
    $modemDetails = json_decode(file_get_contents("/home/albismart/cmtsOnline/$cmts"));

    foreach ($modemDetails as $modemDetail) {
        array_push($modems, $modemDetail);
    }
}

$total = 0;
$pointsTotal = 0;
$i = 1;
$ignored = [];
$points = [];
foreach($modems as $modemContent)
{
    $cmmac = strtoupper($modemContent->cmmac);

    if(!file_exists("/home/albismart/tsdbData/$cmmac.json")) {
        array_push($ignored, ['cmip' => $modemContent->cmip, 'cmmac' => $cmmac]);
        continue;
    }

    $content = json_decode(file_get_contents("/home/albismart/tsdbData/$cmmac.json"), true);

    if(empty($content)) continue;
    if(!isset($content[$cmmac])) continue;

    $cmmacPoints = seperateFields($content, $cmmac);

    $points = array_merge($points, $cmmacPoints);

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
// file_put_contents('/home/influx/points.file', $points);
$total += $i;
echo ("Wrote $total data into influx!\n");

$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
echo " It takes ". $execution_time." seconds to prepare the payload for writing!\n";

$database->writePoints($points, InfluxDB\Database::PRECISION_SECONDS);

echo "Number of modems that had not .json file: ".count($ignored)."\n";

function seperateFields($modem, $cmmac)
{
    $dsListPoints = writeDsList($modem, $cmmac, 'modems_dsList');

    $usListPoints = writeUsList($modem, $cmmac, 'modems_usList');

    $lanInterfacePoints = writeLanInterface($modem, $cmmac, 'modems_lanInterface');

    $wifiInterfacePoints = writeWifiInterface($modem, $cmmac, 'modems_wifiInterface');

    $interfacePoints = writeInterfaces($modem, $cmmac, 'modems_interfaces');

    $mainFieldsPoints = writeMainFields($modem, $cmmac, 'modems_main');

    $mergedPoints = array_merge($dsListPoints, $usListPoints, $lanInterfacePoints, $wifiInterfacePoints, $interfacePoints, $mainFieldsPoints);

    return $mergedPoints;
}

function writeDsList($modem, $cmmac, $measurement)
{
    $dsLists = isset($modem[$cmmac]['dsList']) ? $modem[$cmmac]['dsList'] : [];
    $dsListPoints = [];

    foreach($dsLists as $dsList)
    {
        $tags = ['cmmac' => $cmmac, 'id' => (string)$dsList['index']];

        $parsedDsList = array_map('doubleval', $dsList);
        $parsedDsList['index'] = (string)$dsList['index'];

        $point = prepareSeperatedPoint($measurement, $tags, $parsedDsList);

        array_push($dsListPoints, $point);
    }

    return $dsListPoints;
}

function writeUsList($modem, $cmmac, $measurement)
{
    $usLists = isset($modem[$cmmac]['usList']) ? $modem[$cmmac]['usList'] : [];
    $usListPoints = [];

    foreach($usLists as $usList)
    {
        $tags = ['cmmac' => $cmmac, 'id' => (string)$usList['index']];

        $parsedUsList = array_map('doubleval', $usList);
        $parsedUsList['index'] = (string)$usList['index'];

        $point = prepareSeperatedPoint($measurement, $tags, $parsedUsList);

        array_push($usListPoints, $point);
    }

    return $usListPoints;
}

function writeLanInterface($modem, $cmmac, $measurement)
{
    $lanInterfaces = isset($modem[$cmmac]['lanInterface']) ? $modem[$cmmac]['lanInterface'] : [];
    $lanInterfacePoints = [];

    foreach($lanInterfaces as $lanInterface)
    {
        $tags = ['cmmac' => $cmmac, 'id' => (string)$lanInterface['lanIndex']];

        $parsedLanInterface = array_map('doubleval', $lanInterface);
        $parsedLanInterface['lanIndex'] = (string)$lanInterface['lanIndex'];

        $point = prepareSeperatedPoint($measurement, $tags, $parsedLanInterface);

        array_push($lanInterfacePoints, $point);
    }

    return $lanInterfacePoints;
}

function writeWifiInterface($modem, $cmmac, $measurement)
{
    $wifiInterfaces = isset($modem[$cmmac]['wifiInterface']) ? $modem[$cmmac]['wifiInterface'] : [];
    $wifiInterfacesPoints = [];

    foreach($wifiInterfaces as $wifiInterface)
    {
        $tags = ['cmmac' => $cmmac, 'id' => (string)$wifiInterface['wifiIndex']];

        $parsedWifiInterface = array_map('doubleval', $wifiInterface);
        $parsedWifiInterface['wifiIndex'] = (string)$wifiInterface['wifiIndex'];

        $point = prepareSeperatedPoint($measurement, $tags, $parsedWifiInterface);

        array_push($wifiInterfacesPoints, $point);
    }

    return $wifiInterfacesPoints;
}

function writeInterfaces($modem, $cmmac, $measurement)
{
    $interfaces = isset($modem[$cmmac]['interfaces']) ? $modem[$cmmac]['interfaces'] : [];
    $interfacePoints = [];

    foreach($interfaces as $interface)
    {
        $tags = ['cmmac' => $cmmac, 'id' => (string)$interface['ifIndex']];

        $parsedInterface = array_map('doubleval', $interface);
        $parsedInterface['ifIndex'] = (string)$interface['ifIndex'];

        $point = prepareSeperatedPoint($measurement, $tags, $parsedInterface);

        array_push($interfacePoints, $point);
    }

    return $interfacePoints;
}

function writeMainFields($modem, $cmmac, $measurement)
{
    $fields = $modem[$cmmac];

    $ignoreFields = [
        'interfaces', 'wifiInterface', 'dsList', 'usList', 'lanInterface', 'created_at'
    ];

    foreach ($ignoreFields as $ignoreField) {
        unset($fields[$ignoreField]);
    }

    foreach ($fields as $theKey => $numericField) {
        $fields[$theKey] = (float)$numericField;
    }

    return [prepareSeperatedPoint($measurement, ['cmmac' => $cmmac], $fields)];
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

