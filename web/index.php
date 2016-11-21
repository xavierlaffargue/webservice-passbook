<?php
// web/index.php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/config.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application();
$app['debug'] = true;


//Connection to database
try {
	$pdo = new PDO('mysql:host='.$db_host.';dbname='.$db_name, $db_user, $db_pass);
} 
catch(Exception $ex) { 
    echo $ex->getMessage();
    die();
}


//For test Silex
$app->get('/hello/{name}', function ($name) use ($app) {
	return 'Hello '.$name;
});


//Registering a Device to Receive Push Notifications for a Pass
$app->post('/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}', 
	function (Request $request, $deviceLibraryIdentifier, $passTypeIdentifier, $serialNumber) use ($app, $pdo) {
    if(strstr($request->headers->get('Authorization'), 'ApplePass')) {
        $query = 'SELECT count(*) as nb FROM passbook_registrations WHERE device_library_identifier = "' . $deviceLibraryIdentifier . '"';
        $nb = $pdo->query($query)->fetchColumn();
        $data = json_decode(file_get_contents("php://input"));

        $pushtoken=$data->pushToken;

    	//If a passbook registration exist : It's updated with a new push token
        if($nb > 0) {
            $query = 'INSERT INTO passbook_log VALUES(null, "Num série déjà enregistré", '.time().')';
            $pdo->exec($query);

            $queryUpdatePushToken = 'UPDATE passbook_registrations SET push_token = "'.$pushtoken.'", updated_at = NOW() WHERE device_library_identifier = "'.$deviceLibraryIdentifier.'"';
            $pdo->exec($queryUpdatePushToken);

            return new Response('', 200);
        } 
        else {
            $query = 'INSERT INTO passbook_log VALUES(null, "Enregistrement du num de série", '.time().')';
            $pdo->exec($query);

            $queryRegistration = 'INSERT INTO passbook_registrations VALUES(null, 1, "'.$deviceLibraryIdentifier.'", "'.$pushtoken.'", NOW(), NOW())';
            $pdo->exec($queryRegistration);

            return new Response('', 201);
        }
    } else {
        $query = 'INSERT INTO passbook_log VALUES(null, "Error Header", '.time().')';
        $pdo->exec($query);

       return new Response('', 401);
    }
});


//Getting the Serial Numbers for Passes Associated with a Device
$app->get('/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}', 
	function (Request $request, $deviceLibraryIdentifier, $passTypeIdentifier) use ($app, $pdo) {

    $passesUpdatedSince = $request->query->get('passesUpdatedSince');

    $query = 'SELECT MAX(pp.updated_at) as updateMax, pp.serial_number FROM passbook_passes pp
    INNER JOIN passbook_registrations pr ON pr.pass_id = pp.id
    WHERE device_library_identifier = "' . $deviceLibraryIdentifier . '"
    AND pp.pass_type_identifier = "'.$passTypeIdentifier.'"';

    if(!empty($passesUpdatedSince)) {
        $dateTime = new DateTime($passesUpdatedSince);
        $dateTime->setTimezone(new DateTimeZone('Europe/Paris'));
        $query .=' AND pp.updated_at > "'.$dateTime->format('Y-m-d H:i:s').'"';
    }

    $query .= 'GROUP BY pp.serial_number';

    $pdo->setAttribute(PDO::ATTR_FETCH_TABLE_NAMES, true);
    $result = $pdo->query($query);
    $pdo->setAttribute(PDO::ATTR_FETCH_TABLE_NAMES, false);

    $returnJSON = array();
    $returnJSON['serialNumbers'] =  array();
    $returnJSON['lastUpdated'] = null;


    $returnJSON = array();
    while($myPass = $result->fetch()) {
      $returnJSON['lastUpdated'] = $myPass['.updateMax'];
      $returnJSON['serialNumbers'][] = $myPass['pp.serial_number'];
    }

    if(!empty($returnJSON))	
        return json_encode($returnJSON);
    else
        return new Response('', 204);
});


//Getting the Latest Version of a Pass
$app->get('/v1/passes/{passTypeIdentifier}/{serialNumber}', 
    function (Request $request, $passTypeIdentifier, $serialNumber) use ($app, $pdo) {
    
    $passesUpdatedSince = $request->headers->get('if-modified-since');

    $json = null;
    $query = 'SELECT * FROM passbook_passes
    WHERE serial_number = "' . $serialNumber . '"
    AND pass_type_identifier = "'.$passTypeIdentifier.'"';

    if(!empty($passesUpdatedSince)) {
        $dateTime = new DateTime($passesUpdatedSince);
        $dateTime->setTimezone(new DateTimeZone('Europe/Paris'));
        $query .=' AND updated_at > "'.$dateTime->format('Y-m-d H:i:s').'" ';
    }

    $result = $pdo->query($query);

    if($result->rowCount() > 0) {

        $myPass = $result->fetch();

        $data = $myPass['data'];
        $nameFile = 'passbook_'.time().'.pkpass';
        file_put_contents($nameFile, $data);

        $stream = function () use ($nameFile) {
            readfile($nameFile);
        };

        return $app->stream($stream, 200, array(
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-length' => filesize($nameFile),
            'Content-Disposition' => 'attachment; filename="passbook.pkpass"',
            'Last-Modified' => gmdate('D, d M Y H:i:s T')
        ));

    } else {
        return new Response('', 304);
    }
});


//Unregistering a Device
$app->delete('/v1/devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}', 
	function ($deviceLibraryIdentifier, $passTypeIdentifier, $serialNumber) use ($app,$pdo) {

    $query = 'DELETE passbook_registrations FROM passbook_registrations 
    INNER JOIN passbook_passes ON passbook_passes.id = passbook_registrations.pass_id
    WHERE passbook_registrations.device_library_identifier = "'.$deviceLibraryIdentifier.'"
    AND passbook_passes.pass_type_identifier = "'.$passTypeIdentifier.'"
    AND passbook_passes.serial_number = "'.$serialNumber.'"';

    if($pdo->exec($query) > 0)
        return new Response('', 200);
    else
        return new Response('', 404);
});


//Logging Errors
$app->post('/v1/log', function (Request $request) use ($app, $pdo) {
    $data = json_decode(file_get_contents("php://input"));
    $logs=$data->logs;

    foreach ($logs as $value) {
        $query = 'INSERT INTO passbook_log VALUES(null,"' . $value . '", '.time().')';
        $pdo->exec($query);
    }	

    return new Response('', 200);
});

$app->run();
