<?php

require_once '../vendor/autoload.php';

$cacheDir  = '/tmp/ddb-cache/';
$cacheHash = getCacheHash();
$cacheFile = $cacheDir . $cacheHash;
$jsonData  = null;

if (file_exists($cacheFile) && filemtime($cacheFile) > (new DateTime('-10 minutes'))->format('U')) {
    $jsonData = file_get_contents($cacheFile);
}

if (!$jsonData) {
    try {
        $data = ['devices' => doQuery()];
        $jsonData = json_encode($data, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        header('HTTP/1.0 501 Not Implemented');
        echo '{"errormsg":"' . $e->getMessage() . '"}';
        exit;
    }
    file_put_contents($cacheFile . '.tmp', $jsonData);
    rename($cacheFile . '.tmp', $cacheFile);
}


if (!empty($_GET['j']) || !empty($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/json') {
    outputJson($jsonData);
} else {
    outputCsv(json_decode($jsonData, true));
}


function getCacheHash(): string
{
    $filters = [
        't',
        'device_id',
        'from_id',
        'till_id',
        'registration',
        'cn',
    ];

    $cacheIdentifier = '';
    foreach ($filters as $filter) {
        $cacheIdentifier .= $_GET[$filter];
    }
    return md5($cacheIdentifier);
}

function doQuery(): array
{
    # sleep randomly up to 500ms to dispatch queries
    usleep(rand(0,500000));
    
    $dbh = Database::connect();

    $t = !empty($_GET['t']);
    $actype = $t ? ', ac_cat AS aircraft_type ' : '';

    $params = array();
    $filter = array();

    if (!empty($_GET['device_id'])) {
        $regs = explode(',', $_GET['device_id']);
        $qm = implode(',', array_fill(0, count($regs), '?'));
        $filter[] = 'dev_id IN ('.$qm.')';
        $params = array_merge($params, $regs);

    }

    if (!empty($_GET['from_id'])) {
        $filter[] = 'dev_id >= ?';
        array_push($params, $_GET['from_id']);
    }

    if (!empty($_GET['till_id'])) {
        if (!empty($_GET['from_id'])) {
        $filter[count($filter)-1] .= ' AND dev_id <= ?';
        array_push($params, $_GET['till_id']);
        } else {
        $filter[] = 'dev_id <= ?';
        array_push($params, $_GET['till_id']);
        }
    }

    if (!empty($_GET['registration'])) {
        $regs = explode(',', $_GET['registration']);
        $qm = implode(',', array_fill(0, count($regs), '?'));
        $filter[] = ' ( dev_acreg IN ('.$qm.') AND dev_notrack = 0 AND dev_noident = 0 ) ';
        $params = array_merge($params, $regs);
    }

    if (!empty($_GET['cn'])) {
        $regs = explode(',', $_GET['cn']);
        $qm = implode(',', array_fill(0, count($regs), '?'));
        $filter[] = ' ( dev_accn IN ('.$qm.') AND dev_notrack = 0 AND dev_noident = 0 ) ';
        $params = array_merge($params, $regs);
    }

    if (count($filter)) {
        $filterstring = 'WHERE ' . implode(' OR ', $filter);
    } else {
        $filterstring = '';
    }

    $sql = <<<eot
    SELECT
        CASE dev_type WHEN 1 THEN "I" WHEN 2 THEN "F" WHEN 3 THEN "O" ELSE "" END AS device_type,
        dev_id AS device_id,
        IF(!dev_notrack AND !dev_noident, ac_type, "" ) AS aircraft_model,
        IF(!dev_notrack AND !dev_noident, dev_acreg, "") AS registration,
        IF(!dev_notrack AND !dev_noident, dev_accn, "") AS cn,
        IF(!dev_notrack,"Y","N") AS tracked,
        IF(!dev_noident,"Y","N") AS identified
        $actype
        FROM devices
        LEFT JOIN aircrafts
        ON dev_actype = ac_id
        $filterstring
        ORDER BY dev_id ASC
eot;
    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function outputJson(string $output): never
{
    // Allow from any origin
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
	echo $output; 
    exit;
}

function outputCsv(array $output): never
{
    header('Content-Type: text/plain; charset="UTF-8"');
    $keys = array_keys($output['devices'][0] ?? []);
    echo '#' . implode(',', array_map('strtoupper', $keys));
    echo "\r\n";
    foreach ($output['devices'] as $row) {
        echo "'";
        echo implode("','", $row);
        echo "'\r\n";
    }
    exit;
}
