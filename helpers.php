<?php

function env($string, $default = "notSet")
{
	$path = dirname(__FILE__);
	$exampleArray = include("$path/.env.example");

	if(file_exists("$path/.env")) {
		$array = include("$path/.env");
	}

	return $array[$string] ?? (isset($exampleArray[$string]) ? $exampleArray[$string] : ($default === "notSet" ? null : $default));
}

function data_get($target, $key, $default = null)
{
	if (is_null($key)) {
		return $target;
	}

	$key = is_array($key) ? $key : explode('.', $key);

	while (! is_null($segment = array_shift($key))) {
		if ($segment === '*') {
			if (! is_array($target)) {
				return value($default);
			}

			$result = [];

			foreach ($target as $item) {
				$result[] = data_get($item, $key);
			}

			return in_array('*', $key) ? collapse($result) : $result;
		}

		if (accessible($target) && exists($target, $segment)) {
			$target = $target[$segment];
			if($target == null) return $target;
		} elseif (is_object($target) && isset($target->{$segment})) {
			$target = $target->{$segment};
		} else {
			return value($default);
		}
	}

	return $target;
}

function preparePoint($measurement, $id, $fields)
{
	$point = new InfluxDB\Point(
        $measurement, // name of the measurement
        null, // the measurement value
        ['id' => $id], // optional tags
        $fields, // optional additional fields
        time());
	return $point;
}

function collapse($array)
{
	$results = [];

	foreach ($array as $values) {
		if (! is_array($values)) {
			continue;
		}

		$results[] = $values;
	}

	return array_merge([], ...$results);
}

function accessible($value)
{
	return is_array($value) || $value instanceof ArrayAccess;
}

function exists($array, $key)
{
	if ($array instanceof ArrayAccess) {
		return $array->offsetExists($key);
	}

	return array_key_exists($key, $array);
}

function flatten($array)
{
	//dotting the array
	$ritit = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));
	$result = array();
	foreach ($ritit as $leafValue) {
		$keys = array();
		foreach (range(0, $ritit->getDepth()) as $depth) {
			$keys[] = $ritit->getSubIterator($depth)->key();
		}
		$result[ join('.', $keys) ] = $leafValue;
	}
	return $result;
}

function scanDirectory($dir, $ignore = [])
{
	$files = array_slice(scandir($dir), 2);
	if(empty($ignore)) return $files;
	return array_values(array_diff($files, $ignore));
}
