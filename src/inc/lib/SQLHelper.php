<?php
class SQL {
	public static function _($aStr) {
		$aStr = addslashes($aStr);
		$aStr = str_replace("\r", "\\r", $aStr);
		$aStr = str_replace("\n", "\\n", $aStr);
		$aStr = str_replace("\t", "\\t", $aStr);
		return $aStr;
	}
	// binary(two) escape
	public static function BEsc(&$vVal1, &$vVal2) {
		return array($vVal1 = self::_($vVal1),
		$vVal2 = self::_($vVal2));
	}
	// trinary(three) escape
	public static function TEsc(&$vVal1, &$vVal2, &$vVal3) {
		return array($vVal1 = self::_($vVal1),
		$vVal2 = self::_($vVal2),
		$vVal3 = self::_($vVal3));
	}
	// singular escape
	public static function Esc(&$vVal1, $aNoRef=0) {
		if (!$aNoRef)
			return self::_($vVal1);
		return $vVal1 = self::_($vVal1);
	}
	public static function SelectA(string $aTable, string $aWhere="") {
		return self::Select($aTable, $aWhere, "*", false);
	}
	public static function Select(string $aTable, string $aWhere="", $aSelector = "*", bool $aUseCustomWhere = false) {
		if (!is_string($aSelector) || !trim($aSelector))
			$aSelector = "*";
		if ($aUseCustomWhere)
			return "SELECT $aSelector from $aTable $aWhere";
		if (trim($aWhere))
			return "SELECT $aSelector from $aTable where $aWhere";
		return "SELECT $aSelector from $aTable";
	}
	public static function Delete(string $aTable, string $aWhere="", bool $aCustomWhere=false) {
		$vQ = "DELETE from $aTable";
		// if user doesn't want "where", allow custom
		if ($aCustomWhere)
			return "$vQ $aWhere";
		// now concat with "where" then $aWhere
		if (trim($aWhere))
			return "$vQ where $aWhere";
		return $vQ;
	}
	public static function IUpdate(bool $aInsert, string $aTable, array $aData, string $aWhere = "", bool $aUseCustomWhere=false) {
		// detect whether user wanted INSERT or UPDATE
		$vQ = ($aInsert ? "INSERT into " : "UPDATE ");
		// concat with table name, and using "set" they both share
		// making it look like: update users set
		$vQ .= "$aTable set ";
		// retrieve all keys of data array
		$vKeys = array_keys($aData);
		// browse through keys
		// we allocate array length to avoid CPU burning
		for ($vI=0, $vL = count($vKeys); $vI < $vL; $vI++) {
			// concat with each key=value
			// stripping SQL injections
			$vQ .= "$vKeys[$vI] = '".self::_($aData[$vKeys[$vI]])."'";
			// if it's not last key-value, concat with comma
			if ($vI + 1 != $vL)
				$vQ .= ",";
		}
		// if user inputs where
		if (!$aInsert && $aWhere) {
			if ($aUseCustomWhere)
				return "$vQ $aWhere";
			return "$vQ where $aWhere";
		}
		return $vQ;
	}
}
?>