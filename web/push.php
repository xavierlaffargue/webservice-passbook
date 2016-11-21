<?php
require_once __DIR__.'/config.php';

$ctx = stream_context_create();
stream_context_set_option($ctx, 'ssl', 'passphrase', $push_cert_passphrase);
stream_context_set_option($ctx, 'ssl', 'local_cert', $push_cert_uri_pem);
$fp = stream_socket_client('ssl://gateway.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT, $ctx);
stream_set_blocking ($fp, 0); 


if (!$fp) {
    //ERROR
    echo "Failed to connect (stream_socket_client): $err $errstrn";
} else {

	// Create the payload body
	
	$body['aps'] = array(
	  'alert' => "Hello World",
	  'sound' => 'default',
	  'link_url' => "http://www.sgr.fr",
	);
	
	$payload = json_encode($body);
	
	//Enhanced Notification
	$msg = chr(0) . pack('n', 32) . pack('H*', $push_device_token) . pack('n', strlen($payload)) . $payload;

	//SEND PUSH
	$result = fwrite($fp, $msg); 
	//We can check if an error has been returned while we are sending, but we also need to check once more after we are done sending in case there was a delay with error response.
	checkAppleErrorResponse($fp);
	usleep(500000); //Pause for half a second. Note I tested this with up to a 5 minute pause, and the error message was still available to be retrieved
	checkAppleErrorResponse($fp);

	if (!$result)
	  echo 'Message not delivered' . PHP_EOL;
	else {
	  echo 'Message successfully delivered' . PHP_EOL;
	  var_dump($result);
	}

	// Close the connection to the server
	fclose($fp);
}


function checkAppleErrorResponse($fp) {

   //byte1=always 8, byte2=StatusCode, bytes3,4,5,6=identifier(rowID). Should return nothing if OK.
   $apple_error_response = fread($fp, 6);
   //NOTE: Make sure you set stream_set_blocking($fp, 0) or else fread will pause your script and wait forever when there is no response to be sent.
   if ($apple_error_response) {
        //unpack the error response (first byte 'command" should always be 8)
        $error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response);

        if ($error_response['status_code'] == '0') {
            $error_response['status_code'] = '0-No errors encountered';
        } else if ($error_response['status_code'] == '1') {
            $error_response['status_code'] = '1-Processing error';
        } else if ($error_response['status_code'] == '2') {
            $error_response['status_code'] = '2-Missing device token';
        } else if ($error_response['status_code'] == '3') {
            $error_response['status_code'] = '3-Missing topic';
        } else if ($error_response['status_code'] == '4') {
            $error_response['status_code'] = '4-Missing payload';
        } else if ($error_response['status_code'] == '5') {
            $error_response['status_code'] = '5-Invalid token size';
        } else if ($error_response['status_code'] == '6') {
            $error_response['status_code'] = '6-Invalid topic size';
        } else if ($error_response['status_code'] == '7') {
            $error_response['status_code'] = '7-Invalid payload size';
        } else if ($error_response['status_code'] == '8') {
            $error_response['status_code'] = '8-Invalid token';
        } else if ($error_response['status_code'] == '255') {
            $error_response['status_code'] = '255-None (unknown)';
        } else {
            $error_response['status_code'] = $error_response['status_code'] . '-Not listed';
        }

        echo '<br><b>+ + + + + + ERROR</b> Response Command:<b>' . $error_response['command'] . '</b>&nbsp;&nbsp;&nbsp;Identifier:<b>' . $error_response['identifier'] . '</b>&nbsp;&nbsp;&nbsp;Status:<b>' . $error_response['status_code'] . '</b><br>';
        echo 'Identifier is the rowID (index) in the database that caused the problem, and Apple will disconnect you from server. To continue sending Push Notifications, just start at the next rowID after this Identifier.<br>';

        return true;
   }
   return false;
}