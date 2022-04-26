<?php
require_once "inc/config.php";
session_start();
header("Content-Type: image/png");
$vIm = imagecreate(56, 26);
$vBg = imagecolorallocate($vIm, 24, 24, 24);
$vFg = imagecolorallocate($vIm, 48, 48, 48);
imagestring($vIm, 5, 5, 5, $_SESSION[SES_CAPTCHA] = base_convert(rand(1111111, 9999999), 10, 36), $vFg);
imagepng($vIm);
imagedestroy($vIm);
exit;
?>