<?php
include '../sql.php';
$dbh = Database::connect();
$devtype = array(1=>"I", 2=>"F", 3=>"O");

$t = !empty($_GET['t']);
$actype = $t ? ', ac_cat AS aircraft_type ' : '';

$params = array();
$filter = array();

if (!empty($_GET['device_id']))
{
    $regs = explode(',',$_GET['device_id']);
    $qm = implode(',', array_fill(0, count($regs), '?'));
    $filter[] = 'dev_id IN ('.$qm.')';
    $params = array_merge($params,$regs);
}
if (!empty($_GET['registration']))
{
    $regs = explode(',',$_GET['registration']);
    $qm = implode(',', array_fill(0, count($regs), '?'));
    $filter[] = 'dev_acreg IN ('.$qm.')';
    $params = array_merge($params,$regs);
}
if (!empty($_GET['cn']))
{
    $regs = explode(',',$_GET['cn']);
    $qm = implode(',', array_fill(0, count($regs), '?'));
    $filter[] = 'dev_accn IN ('.$qm.')';
    $params = array_merge($params,$regs);
}
if (count($filter))
{
    $filterstring = 'WHERE ' . implode(' AND ' , $filter);
}
else
{
    $filterstring = '';
}


$sql = 'SELECT
            dev_type AS device_type,
            dev_id AS device_id,

            IF(!dev_notrack AND !dev_noident,ac_type,"" ) AS aircraft_model,
            IF(!dev_notrack AND !dev_noident,dev_acreg,"") AS registration,
            IF(!dev_notrack AND !dev_noident,dev_accn,"") AS cn,

            IF(!dev_notrack,"Y","N") AS tracked,
            IF(!dev_noident,"Y","N") AS identified
            '.$actype.'
        FROM devices
         LEFT JOIN aircrafts
          ON dev_actype = ac_id
         ' .$filterstring. '
         ORDER BY dev_id ASC';

$stmt = $dbh->prepare($sql);
$stmt->execute($params);

$output['devices']=array();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
{
    $row['device_type'] = $devtype[$row['device_type']];
    $output['devices'][] = $row;
}

if (!empty($_GET['j']) || $_SERVER['HTTP_ACCEPT'] == 'application/json')
{
    // Allow from any origin
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    echo json_encode($output);
}
else
{
    //header('Content-Type: text/plain; charset="UTF-8"');
    echo "#DEVICE_TYPE,DEVICE_ID,AIRCRAFT_MODEL,REGISTRATION,CN,TRACKED,IDENTIFIED";
    if ($t) echo ",AIRCRAFT_TYPE";
    echo "\r\n";
    foreach ($output['devices'] as $row)
    {
        echo "'";
        echo implode("','", $row);
        echo "'\r\n";
    }
}

