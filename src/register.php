<?php
session_start();
require_once "inc/lib/all-libs.php";
require_once "inc/db/tables/all.php";
require_once "inc/config.php";
require_once "inc/linker.php";

$vError = -1;
if (isset($_GET["act"], $_POST["a"], $_POST["b"], $_POST["c"], $_POST["d"], $_POST["e"])) {
	$vCaptcha = $_POST["a"]; $vInvite = $_POST["b"];
	$vNick = trim($_POST["c"]); $vLogin = trim($_POST["d"]); $vPass = $_POST["e"];
	if ($vCaptcha != $_SESSION["tclink_captcha"]) {
		$_SESSION[SES_CAPTCHA] = microtime(true).rand(100000000, 999999999);
		$vError = 0;
	}
	$vD = DBTbl_Login::CreateUser($vLogin, $vNick, $vPass, $vInvite);
	if (false === $vD)
		header("location: ?error=$vError");
	else {
		$_SESSION[SES_LOGIN] = $vLogin;
		$_SESSION[SES_PASS] = $vPass;
		header("location: .");
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Registration</title>
	<style>
*{
	padding:0;margin:0;min-width:0;min-height:0;
	border:none;outline:none;resize:none;text-decoration:none;
	list-style:none;
	box-sizing:border-box;
	background-color:transparent;color:gray;
	font-family:Helvetica, Arial, sans-serif;
}
html,body{width:100%;height:100%;}
body{background-color:#111;padding:32px;}
input {
	padding:4px;
	background-color:#242424;
	color:gray;
	margin:2px;
}
button {
	padding:4px 8px;margin:2px;
	background-color:#323232;
}
	</style>
</head>
<body>
<?php
	if (isset($_GET["error"])) {
		$vEC = (int)$_GET["error"];
		echo "<h1>Error code: $vEC</h1>";
	}
?>
	<form action="?act=0" method="post">
		<img src="captcha.php" alt="Captcha">
		<div><input type="text" name="a" placeholder="Captcha"></div>
		<div><input type="text" name="b" placeholder="Invite"></div>
		<div><input type="text" name="c" placeholder="Nick"> *2 chars min; cannot be all numbers</div>
		<div><input type="text" name="d" placeholder="Login"> *2 chars min</div>
		<div><input type="password" name="e" placeholder="Pass"></div>
		<button>Register</button>
	</form>
</body>
</html>