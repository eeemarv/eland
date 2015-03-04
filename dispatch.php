<?php

// load Tonic library
require_once 'contrib/includes/tonic/tonic.php';

// load Task Handler
//require_once 'resources/task/TaskHandler.php';
//require_once 'resources/auth/auth.php';
//require_once 'resources/htmlform/htmlform.php';

// Load handlers
require_once 'resources/system/uuid/UuidHandler.php';
require_once 'resources/system/apiversion/ApiHandler.php';
require_once 'resources/interlets/status/StatusHandler.php';
require_once 'resources/user/openid/openidHandler.php';
require_once 'resources/user/subscription/subscriptionHandler.php';
require_once 'resources/mailinglist/mailinglistHandler.php';
require_once 'resources/mailmessage/messageHandler.php';
require_once 'resources/news/newsHandler.php';

// handle request
$request = new Request();
try {
    $resource = $request->loadResource();
    $response = $resource->exec($request);

} catch (ResponseException $e) {
    switch ($e->getCode()) {
    case Response::UNAUTHORIZED:
        $response = $e->response($request);
        $response->addHeader('WWW-Authenticate', 'Basic realm="eLAS"');
        break;
    default:
        $response = $e->response($request);
    }
}
$response->output();

?>
