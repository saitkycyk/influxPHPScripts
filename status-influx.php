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

$measurement = 'modems_status';
$writeZip = env("status_writeToZip");
$dbname = env("status_database");

$database = $client->selectDB($dbname);

$onlineCmtses = scanDirectory('/home/albismart/cmtsOnline');
$offlineCmtses = scanDirectory('/home/albismart/cmtsOffline');

$onlineCmmacs = [];
foreach($onlineCmtses as $onlineCmts)
{
	if($onlineCmts == ".json") continue;
	$cmtsOnModems = json_decode(file_get_contents("/home/albismart/cmtsOnline/$onlineCmts"));
	foreach ($cmtsOnModems as $onlineModem) {
		$onlineCmmac = strtoupper($onlineModem->cmmac);
		array_push($onlineCmmacs, $onlineCmmac);
	}
}

$offlineCmmacs = [];
foreach($offlineCmtses as $offlineCmts)
{
	if($offlineCmts == ".json") continue;
	$cmtsOffModems = json_decode(file_get_contents("/home/albismart/cmtsOffline/$offlineCmts"));
	foreach ($cmtsOffModems as $offlineModem) {
		$offlineCmmac = strtoupper($offlineModem->cmmac);
		array_push($offlineCmmacs, $offlineCmmac);
	}
}

$points = [];
$dataForFile = [];
foreach($onlineCmmacs as $onlineCmmac)
{
	$fields = ['cmmac' => $onlineCmmac, 'active' => 1];

	$point = preparePoint($measurement, $onlineCmmac, $fields);

	array_push($points, $point);

	if($writeZip) {
		$fields['time'] = time();
		array_push($dataForFile, $fields);
	}
}

foreach($offlineCmmacs as $offlineCmmac)
{
	$fields = ['cmmac' => $offlineCmmac, 'active' => 0];

	$point = preparePoint($measurement, $offlineCmmac, $fields);

	array_push($points, $point);

	if($writeZip) {
		$fields['time'] = time();
		array_push($dataForFile, $fields);
	}
}

if($writeZip) {
	file_put_contents("/home/albismart/modemsStatus/modemStatus.json", json_encode($dataForFile));

	zipBuild();

	sleep(1);

	copy("/home/albismart/modemsLatestStatus.zip", "/home/albismart/api/modemsLatestStatus.zip");
}

function zipBuild()
{
	$rootPath = realpath("/home/albismart/modemsStatus");

	$zip = new ZipArchive();

	$zip->open("/home/albismart/modemsLatestStatus.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE);

	if($rootPath == null) return false;

	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($rootPath),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ($files as $name => $file)
	{
		if (!$file->isDir())
		{
			$filePath = $file->getRealPath();
			$relativePath = substr($filePath, strlen($rootPath) + 1);

			$zip->addFile($filePath, $relativePath);
		}
	}

	$zip->close();


	return true;
}


$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
echo " It takes ". $execution_time." seconds to prepare the payload for writing!\n";
$start_time = microtime(true);

$database->writePoints($points, InfluxDB\Database::PRECISION_SECONDS);

$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
echo " It takes ". $execution_time." seconds to write payload into influx!\n";
