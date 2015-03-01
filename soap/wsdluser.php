<?php
$rootpath="../";
// Pull in the NuSOAP code
require_once('lib/nusoap.php');
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_elassoap.php");
require_once($rootpath."includes/inc_userinfo.php");

// Create the server instance
$server = new soap_server();
// Initialize WSDL support
$server->configureWSDL('interletswsdl', 'urn:interletswsdl');

// Register the method to expose
$server->register('userbyletscode',                // method name
   array('letscode' => 'xsd:string'),        // input parameters
    array('return' => 'xsd:string'),      // output parameters
    'urn:interletswsdl',                      // namespace
    'urn:interletswsdl#userbyletscode',                // soapaction
    'rpc',                                // style
    'encoded',                            // use
    'Get the user'            // documentation
);

function userbyletscode($letscode){
	$user = get_user_by_letscode($letscode);
	return $user["fullname"];
}

// Use the request to (try to) invoke the service
$HTTP_RAW_POST_DATA = isset($HTTP_RAW_POST_DATA) ? $HTTP_RAW_POST_DATA : '';
$server->service($HTTP_RAW_POST_DATA);
?>
