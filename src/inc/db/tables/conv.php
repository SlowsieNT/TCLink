<?php
class DBTbl_ConvPub extends DBTBase {
	static $DBIndex = 0, $Name = "conv_pub", $Key = "cid";
	public static $UNLISTED = 1;
	public static $CANNOT_JOIN = 2;
	public static $REQUIRE_PASSWORD = 4;
	public static function Create($aCUID, $aName, $aPass, array $aUIDs, $aFlags) {
		$vCID = DBTbl_Conv::Insert(null, $aCUID, $aName, 1, 0, time(), $aFlags, $aPass);
		$vAIDs = array();
		foreach ($aUIDs as $vUID) {
			$vInfo = DBTbl_Login::ResolveFetchUIDNick($vUID);
			if (false !== $vInfo) {
				if (DBTbl_ConvWhitelist::IsWhitelisted($aCUID, $vInfo->id)) {
					$vAIDs[] = $vInfo->id;
					self::Insert($vCID, $vInfo->id, time());
				}
			}
		}
		if (!in_array($aCUID, $vAIDs))
			self::Insert($vCID, $aCUID, time());
		return $vCID;
	}
	public static function AddMember($aCUID, $aCID, $aUID) {
		$vInfo2 = DBTbl_Login::ResolveFetchUIDNick($aUID);
		if (DBTbl_ConvWhitelist::IsWhitelisted($aCUID, $vInfo2->id))
			if (!DBTbl_Conv::IsMember($aCID, $vInfo2->id, $vInfo)) {
				self::Insert($vInfo->id, $vInfo2->id, time());
				return true;
			}
		return false;
	}
	public static function RemoveMember($aCUID, $aCID, $aUID) {
		$vInfo = DBTbl_Conv::GetByKValue($aCID);
		if (false !== $vInfo && $vInfo->type && $aCUID == $vInfo->oid) {
			$vInfo2 = DBTbl_Login::ResolveFetchUIDNick($aUID);
			if (false !== $vInfo2)
				return 0 < self::Delete2("cid='$vInfo->id' and mid='$vInfo2->id'");
		}
		return false;
	}
}
class DBTbl_ConvPriv extends DBTBase {
	static $DBIndex = 0, $Name = "conv_priv", $Key = "cid";
	public static function GetCreateID($aCUID, $aUID) {
		$vPass=0;
		if (!is_numeric($aUID)) {
			$vInfo = DBTbl_Login::GetUserByNick($aUID);
			if (false !== $vInfo)
				$vPass = $aUID = $vInfo->id;
		}
		// Must be numeric
		if (!is_numeric($aUID) || !is_numeric($aCUID))
			return false;
		if (!$vPass && false === DBTbl_Login::GetByKValue($aUID))
			return false;
		$vObj = self::FetchObject("*", "uid='$aUID' and sid='$aCUID' or uid='$aCUID' and sid='$aUID'");
		if (false === $vObj) {
			$vCID = DBTbl_Conv::Insert(null, null, null, 0, null, time());
			self::Insert($vCID, $aCUID, $aUID, time());
		} else $vCID = $vObj->cid;
		return $vCID;
	}
}
class DBTbl_ConvWhitelist extends DBTBase {
	static $DBIndex = 0, $Name = "conv_whitelist", $Key = "uid";
	// if sid is current user's id, then is whitelisted
	public static function IsWhitelisted($aCUID, $aUID) {
		SQL::BEsc($aCUID, $aUID);
		return 0 < self::RowCount("*", "uid='$aCUID' and sid='$aUID'");
	}
	public static function Add($aCUID, $aUID) {
		if (!self::IsWhitelisted($aCUID, $aUID))
			return self::Insert($aCUID, $aUID);
		return false;
	}
	public static function Remove($aCUID, $aUID) {
		SQL::BEsc($aCUID, $aUID);
		return 0 < self::Delete2("uid='$aCUID' and sid='$aUID'");
	}
}
class DBTbl_Conv extends DBTBase {
	static $DBIndex = 0, $Name = "conv", $Key = "id";
	public static function IsMember($aID, $aUID, &$aRefConv=null) {
		SQL::Esc($aUID);
		$aRefConv = $vInfo = self::GetByKValue($aID);
		if (false !== $vInfo) {
			if (0 == $vInfo->type)
				return 0 < DBTbl_ConvPriv::RowCount("*", "(uid='$aUID' or sid='$aUID') and cid='$vInfo->id'");
			return 0 < DBTbl_ConvPub::RowCount("*", "mid='$aUID' and cid='$vInfo->id'");
		}
		return false;
	}
	public static function JoinGroup($aID, $aCUID, $aPassword="") {
		if (self::IsMember($aID, $aCUID, $vInfo))
			return false;
		if (false !== $vInfo && $vInfo->type) {
			$vFlags = $vInfo->flags; // vFlags is a number
			if ($vFlags & DBTbl_ConvPub::$CANNOT_JOIN)
				return false;
			if ($vFlags & DBTbl_ConvPub::$REQUIRE_PASSWORD)
				if ($aPassword != $vInfo->password)
					return false;
			DBTbl_ConvPub::Insert($vInfo->id, $aCUID, time());
			return true;
		}
		return false;
	}
	public static function LeaveGroup($aID, $aCUID) {
		if (!self::IsMember($aID, $aCUID))
			return false;
		SQL::Esc($aID);
		return 0 < DBTbl_ConvPub::Delete2("cid='$aID' and mid='$aCUID'");
	}
	public static function GetUsers($aID) {
		$vInfo = self::GetByKValue($aID);
		if (false !== $vInfo) {
			$vArr = array();
			if ($vInfo->type) {
				$vObj = DBTbl_ConvPub::FetchAllObjects("mid", "cid='$vInfo->id'");
				if (false !== $vObj)
					foreach ($vObj as $vItem)
						$vArr[] = $vItem->mid;
			} else {
				$vObj = DBTbl_ConvPriv::FetchObject("*", "cid='$vInfo->id'");
				if (false !== $vObj)
					$vArr = array($vObj->uid, $vObj->sid);
			}
			$vR = array();
			for ($vI = 0, $vC = count($vArr); $vI < $vC; $vI++) {
				$vObj = DBTbl_Login::FetchObject("id,nick,mood,avatar,lastdate", "id='$vArr[$vI]'");
				if (false !== $vObj)
					$vR[] = $vObj;
			}
			return $vR;
		}
		return array();
	}
	public static function ResolveUIDCID($aCUID, $aCID, $aUID) {
		if (is_numeric($aUID) && "0" != $aUID && $aUID)
			$aCID = DBTbl_ConvPriv::GetCreateID($aCUID, $aUID);
		elseif ("0" == $aCID)
			$aCID = DBTbl_ConvPriv::GetCreateID($aCUID, $aUID, 1);
		return $aCID;
	}
	public static function WaitForMessages($aCUID, $aCID, $aUID, $aFirstFetch) {
		if (!is_numeric($aCID)) return array();
		if (is_numeric($aCID)) {
			$aCID = self::ResolveUIDCID($aCUID, $aCID, $aUID);
			if (!self::IsMember($aCID, $aCUID))
				return false;
			session_write_close();
			$vLObj = DBTbl_ConvLMsg::GetByKValue($aCID);
			$vMaxTicks = 32;
			// Set to dummy to avoid late arrival of messages
			if (false === $vLObj)
				$vLObj = (object)array("mid" => 0);
			while ($vMaxTicks--) {
				if ($aFirstFetch)
					break;
				$vObj = DBTbl_ConvLMsg::GetByKValue($aCID);
				if (false !== $vObj)
					if ($vObj->mid != $vLObj->mid) {
						$vLObj = $vObj;
						break;
					}
				usleep(300e3);
			}
			$vMID = false !== $vLObj ? $vLObj->mid : 0;
			$vData = array();
			// Fetch messages
			if (!$aFirstFetch)
				$vData = DBTbl_ConvTexts::FetchAllObjects("*", "cid='$aCID' and id >= $vMID");
			else $vData = array_reverse(DBTbl_ConvTexts::FetchAllObjects("*", "cid='$aCID' order by createdat desc limit 256"));
			$vData = array(
				"u" => self::GetUsers($aCID),
				"c" => $vData
			);
			// Return data
			return $vData;
		}
		return false;
	}
	public static function SetExpirement($aID, $aCUID, $aUID, $aTime) {
		if (!is_numeric($aID) || !is_numeric($aTime))
			return false;
		$aID = self::ResolveUIDCID($aCUID, $aID, $aUID);
		if (self::IsMember($aID, $aCUID)) {
			self::Update2(array("mexpire"=>$aTime), "id='$aID'");
			return true;
		}
		return false;
	}
	public static function DeleteAllMessages($aID, $aCUID, $aUID) {
		$aID = self::ResolveUIDCID($aCUID, $aID, $aUID);
		if (is_numeric($aID)) {
			$vInfo = self::GetByKValue($aID);
			$vCanDelete = true;
			if (false !== $vInfo) {
				if ($vInfo->type && $vInfo->oid != $aCUID)
					$vCanDelete = false;
				if ($vCanDelete && self::IsMember($aID, $aCUID)) {
					DBTbl_ConvTexts::Delete2("cid='$aID'");
					DBTbl_ConvLMsg::Delete2("cid='$aID'");
					DBTbl_ConvBin::Delete2("cid='$aID'");
					return true;
				}
			}
		}
		return false;
	}
	public static function DeleteConversation($aID, $aCUID) {
		if (is_numeric($aID)) {
			$vInfo = self::GetByKValue($aID);
			if (false !== $vInfo && $vInfo->type && $vInfo->oid == $aCUID) {
				self::Delete2("id='$aID'");
				DBTbl_ConvTexts::Delete2("cid='$aID'");
				DBTbl_ConvLMsg::Delete2("cid='$aID'");
				DBTbl_ConvPub::Delete2("cid='$aID'");
				DBTbl_ConvBin::Delete2("cid='$aID'");
				return true;
			}
		}
		return false;
	}
	public static function SendMessage($aID, $aCUID, $aUID, $aContent) {
		$aID = self::ResolveUIDCID($aCUID, $aID, $aUID);
		if (!self::IsMember($aID, $aCUID, $vConv))
			return false;
		// Send message
		$vLMsg = DBTbl_ConvLMsg::GetByKValue($aID);
		$vID = DBTbl_ConvTexts::Insert(null, $aID, $aCUID, $aContent, time());
		if (false === $vLMsg) {
			//echo "insert\r\n\r\n";?
			DBTbl_ConvLMsg::Insert($aID, $vID, $aCUID, time());
		}
		else {
			//echo "update\r\n\r\n";
			DBTbl_ConvLMsg::Update("cid='$vConv->id'", null, $vID, $aCUID, time());
		}
		return $vID;
	}
	public static function ListAllConversations($aCUID) {
		$vDataX = array(
			DBTbl_ConvPriv::FetchAllObjects("*", "sid='$aCUID' or uid='$aCUID'"),
			DBTbl_ConvPub::FetchAllObjects("*", "mid='$aCUID'")
		);
		$vRet = array();
		// Loop (2) times
		for ($vI2 = 0, $vC2 = count($vDataX); $vI2 < $vC2; $vI2++) {
			$vData = $vDataX[$vI2];
			// Loop through vData (CONVs)
			for ($vI = 0, $vC = count($vData); $vI < $vC; $vI++) {
				$vR = array(); $vItem = $vData[$vI];
				// Revalidate conv
				$vConv = self::GetByKValue($vItem->cid);
				if (false !== $vConv) {
					if (!$vConv->type) {
						$vUID = $vItem->sid;
						if ($aCUID == $vUID) $vUID = $vItem->uid;
						$vInfo = DBTbl_Login::GetByKValue($vUID, "avatar,nick");
						if (false !== $vInfo) {
							$vConv->name = $vInfo->nick;
							$vConv->avatar = $vInfo->avatar;
						}
					}
					// Set key and value of result (vR) array to: c=conv
					$vR["c"] = $vConv;
					$vLMsg = DBTbl_ConvLMsg::FetchObject("*", "cid='$vConv->id'");
					if (false !== $vLMsg) {
						$vMsg = DBTbl_ConvTexts::FetchObject("*", "id='$vLMsg->mid'");
						if (false !== $vMsg) {
							// Set key and value of result (vR) array to: m=msg
							$vR["m"] = $vMsg;
							$vInfo = DBTbl_Login::GetByKValue($vMsg->uid, "nick,avatar");
							// Set key and value of result (vR) array to: u=member
							if (false !== $vInfo)
								$vR["u"] = $vInfo;
						}
					}
				}
				unset($vData[$vI]);
				$vRet[] = $vR;
			}
		}
		return $vRet;
	}
}
?>