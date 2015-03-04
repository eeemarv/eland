<?php

// PROCESS THE LOGIN CREDENTIALS AND BUILD THE SESSION

ob_start();
$rootpath = "./";
require_once($rootpath."includes/inc_default.php");
require_once($rootpath."includes/inc_adoconnection.php");
require_once($rootpath."includes/inc_userinfo.php");
require_once($rootpath."includes/inc_auth.php");

// OpenID includes
#require_once($rootpath."contrib/includes/Auth/OpenID/Consumer.php");
#require_once($rootpath."contrib/includes/Auth/OpenID/FileStore.php");
require_once($rootpath."contrib/includes/lightopenid/openid.php");

// Example
#require 'openid.php';
/*
try {
    # Change 'localhost' to your domain name.
    $openid = new LightOpenID('localhost');
    if(!$openid->mode) {
        if(isset($_GET['login'])) {
            $openid->identity = 'https://www.google.com/accounts/o8/id';
            header('Location: ' . $openid->authUrl());
        }
?>
<form action="?login" method="post">
    <button>Login with Google</button>
</form>
<?php
    } elseif($openid->mode == 'cancel') {
        echo 'User has canceled authentication!';
    } else {
        echo 'User ' . ($openid->validate() ? $openid->identity . ' has ' : 'has not ') .
'logged in.';
    }
} catch(ErrorException $e) {
    echo $e->getMessage();
}
*/

$myopenid = $_POST['openid'];
echo "OpenID login for " .$myopenid ."<br>";

log_event($userid, "OpenID", "OpenID authentication started for $openid");
//session_start();
if(isset($_POST['targeturl'])){
	$_SESSION['targeturl'] = $_POST['targeturl'];
}

// begin sign-in process
try {
    $openid = new LightOpenID("$baseurl");
    if(!$openid->mode) {
        if(isset($myopenid)) {
            $openid->identity = $myopenid;
            header('Location: ' . $openid->authUrl());
        }
    } elseif($openid->mode == 'cancel') {
        echo 'User has canceled authentication!';
    } else {
        echo 'User ' . ($openid->validate() ? $openid->identity . ' has ' : 'has not ') . 'logged in.';
		$uopenid = $openid->identity;
        echo "given identity is: " .$uopenid;
        //Build the session and redirect us
        $myuser = get_user_by_openid($uopenid);
        if(empty($myuser['id'])){
				if(preg_match("/^http:/",$uopenid)){
					$uopenid = preg_replace("/^http:/","https:", $uopenid);
					$myuser = get_user_by_openid($uopenid);
					echo "<br>Trying $uopenid...";
					log_event($s_id,"Login","Trying openid $uopenid");
				}
				if(preg_match("/^https:/",$uopenid)){
					$uopenid = preg_replace("/^https:/", "http:", $uopenid);
	                $myuser = get_user_by_openid($uopenid);
	                echo "<br>Trying $uopenid...";
	                log_event($s_id,"Login","Trying openid $uopenid");
				}
		}
        if(!empty($myuser['id'])){
			echo "<br>Starting session for " .$myuser['fullname'] ."...";
			startsession($myuser);
			if(!empty($_SESSION['targeturl'])){
				header('Location: ' . $_SESSION['targeturl']);
			} else {
				header('Location: ' . "http://$baseurl/index.php");
			}
		} else {
			echo "<br><font color='red'><b>Cannot log in, $uopenid is not linked to an existing user</b></font>";
			log_event($s_id,"LogFail","OpenID $uopenid is not linked to a user");
		}
	}
} catch(ErrorException $e) {
    echo $e->getMessage();
}

?>
