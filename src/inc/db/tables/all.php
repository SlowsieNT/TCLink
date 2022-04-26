<?php
require_once "conv.php";
class DBTbl_Invite extends DBTBase { static $DBIndex = 0, $Name = "invite", $Key = "code"; }
class DBTbl_ConvTexts extends DBTBase { static $DBIndex = 0, $Name = "conv_texts", $Key = "id"; }
class DBTbl_ConvBin extends DBTBase { static $DBIndex = 0, $Name = "conv_bin", $Key = "id"; }
class DBTbl_ConvLMsg extends DBTBase { static $DBIndex = 0, $Name = "conv_lmsg", $Key = "cid"; }
class DBTbl_Login extends DBTBase {
	static $DBIndex = 0, $Name = "login", $Key = "id";
	public static function Hash($aText) {
		return md5("ahfnbcxmnjhrehutioyu".md5("#&*:`^%$!;!@#$%^".$aText));
	}
	public static function GetUserByAuth($aUser, $aPass) {
		SQL::Esc($aUser); $aPass = self::Hash($aPass);
		return DBTbl_Login::FetchObject("*", "login='$aUser' and password='$aPass'");
	}
	public static function GetUserByNick($aNick) {
		SQL::Esc($aNick);
		return DBTbl_Login::FetchObject("*", "nick='$aNick'");
	}
	public static function CreateUser($aLogin, $aNick, $aPass, $aInvite) {
		if (is_numeric($aNick) || strlen($aLogin) < 2 || strlen($aNick) < 2)
			return false;
		if (false === DBTbl_Invite::GetByKValue($aInvite))
			return false;
		// Check if login exists
		$vRC = DBTbl_Login::RowCount("*", "login='".SQL::Esc($aLogin, 1)."'");
		if (1 > $vRC)
			return DBTbl_Login::Insert(null, trim($aLogin), trim($aNick), DBTbl_Login::Hash($aPass));
		return false;
	}
	public static function ResolveFetchUIDNick($aUID) {
		$vInfo = self::GetByKValue($aUID);
		if (false === $vInfo)
			$vInfo = self::GetUserByNick($aUID);
		return $vInfo;
	}
}

?>