<?php

include('helpers.php');

date_default_timezone_set("Europe/Belgrade");

$start_time = microtime(true);
deleteOldFiles();

$cmtses = scanDirectory('/home/albismart/cmtsOnline');

$modems = [];
foreach($cmtses as $cmts)
{
	$modemDetails = json_decode(file_get_contents("/home/albismart/cmtsOnline/$cmts"));

	foreach ($modemDetails as $modemDetail) {
		array_push($modems, $modemDetail);
	}
}

$data = [];
$i = 0;
$j = 1;
foreach($modems as $modemContent)
{
	$cmmac = strtoupper($modemContent->cmmac);

	if(!file_exists("/home/albismart/tsdbData/$cmmac.json") || !file_exists("/home/albismart/cmData/$cmmac.json")) continue;

	$tsdb = json_decode(file_get_contents("/home/albismart/tsdbData/$cmmac.json"), true);
	$cm = json_decode(file_get_contents("/home/albismart/cmData/$cmmac.json"), true);

	if(empty($tsdb) || empty($cm)) continue;
	if(!isset($tsdb[$cmmac]) || !isset($cm[$cmmac])) continue;

	// $parsedContent = parseModemContent($content[$cmmac]);

	$tsdb = $tsdb[$cmmac];
	$cm = $cm[$cmmac];

	foreach ($tsdb as $key => $value) {
		if(is_array($value)) {
			foreach ($value as $subKey => $subValue) {
				if(isset($cm[$key][$subKey])) {
					$value[$subKey] = array_merge($subValue, $cm[$key][$subKey]);
				}
			}
		}
		$cm[$key] = $value;
	}

	array_push($data, $cm);

	$i++;
	if($i == 1000) {
		file_put_contents("/home/albismart/modemsContent/$j.json", json_encode($data));
		$j ++;
		$i = 0;
		$data = [];
	}
}

file_put_contents("/home/albismart/modemsContent/$j.json", json_encode($data));

zipBuild();

sleep(1);

copy("/home/albismart/modemsLatestContent.zip", "/home/albismart/api/modemsLatestContent.zip");

function zipBuild()
{
	$rootPath = realpath("/home/albismart/modemsContent");

	$zip = new ZipArchive();

	$zip->open("/home/albismart/modemsLatestContent.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE);

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

function parseModemContent($content)
{
	$parsedContent = [];
	$selectedFields = [
		'cmmac', 'cip', 'mtamac', 'gwmac', 'cmsn', 'DocsisBaseCapability', 'cm_mode', 'cmstatus', 'cmvendor', 'cmmodel', 'cmhwrevision', 'bootR', 'cmswrevision', 'configFile', 'cmts', 'alias'
	];

	foreach ($selectedFields as $selectedField) {
		$parsedContent[$selectedField] = $content[$selectedField] ?? null;
	}

	$parsedContent['wifiSSID'] = $content['wifiInterface'][0]['wifiSSID'] ?? null;
	$parsedContent['wifiSSID'] = $content['wifiInterface'][0]['wifiPass'] ?? null;

	return $parsedContent;
}

function deleteOldFiles()
{
	exec("rm -r /home/albismart/modemsContent/*");
}

echo "\nIt took ".(microtime(true) - $start_time)." seconds!\n";
