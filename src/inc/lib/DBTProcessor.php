<?php
class DBTProcessor {
	public static function Fetch(int $aDBIndex, string $aTable, $aMode, $aWhere="", $aSelector="*", bool $aUseCustomWhere=false, bool $aFetchAll=false) {
		$vQ = DB::Query($aDBIndex, $vQStr=SQL::Select($aTable, $aWhere, $aSelector, $aUseCustomWhere));
		if ($aFetchAll)
			return $vQ->fetchAll($aMode);
		return $vQ->fetch($aMode);
	}
	public static function FetchAll(int $aDBIndex, string $aTable, $aMode, $aWhere="", $aSelector="*", bool $aUseCustomWhere=false) {
		return self::Fetch($aDBIndex, $aTable, $aMode, $aWhere, $aSelector, $aUseCustomWhere, true);
	}
	public static function RowCount(int $aDBIndex, string $aTable, $aWhere="", $aSelector="*", bool $aUseCustomWhere=false) {
		$vQ = DB::Query($aDBIndex, $vQStr=SQL::Select($aTable, $aWhere, $aSelector, $aUseCustomWhere));
		return $vQ->rowCount();
	}
	public static function HasRows(int $aDBIndex, string $aTable, $aWhere="", $aSelector="*", bool $aUseCustomWhere=false) {
		return 0 < self::RowCount($aDBIndex, $aTable, $aWhere, $aSelector, $aUseCustomWhere);
	}
	public static function Update(int $aDBIndex, string $aTable, array $aData, $aWhere="", bool $aUseCustomWhere=false) {
		$vQ = DB::Query($aDBIndex, $vQStr=SQL::IUpdate(false, $aTable, $aData, $aWhere, $aUseCustomWhere));
		if (false !== $vQ)
			return $vQ->rowCount();
		return $vQ;
	}
	public static function Insert(int $aDBIndex, string $aTable, array $aData, &$aID=null) {
		$vQ = DB::Query($aDBIndex, $vQStr=SQL::IUpdate(true, $aTable, $aData));
		if (false !== $vQ)
			return $aID = DB::LastInsertId($aDBIndex);
		return false;
	}
	public static function Delete(int $aDBIndex, string $aTable, $aWhere="", bool $aUseCustomWhere=false) {
		$vQ = DB::Query($aDBIndex, $vQStr=SQL::Delete($aTable, $aWhere, $aUseCustomWhere));
		return $vQ->rowCount();
	}
	// Returns id, number, or false
	// aWhere is string or null
	public static function IAppendBlob(int $aDBIndex, $aTable, $aData, $aBlobFieldName, $aWhere=null) {
		if (is_null($aWhere)) {
			$vQ = DB::Query($aDBIndex, "INSERT into $aTable set $aBlobFieldName='".SQL::_($aData)."'");
			if (false === $vQ) return false;
			return DB::LastInsertId($aDBIndex);
		} else {
			$vQStr = "UPDATE $aTable set $aBlobFieldName=concat($aBlobFieldName,'".SQL::_($aData)."') where $aWhere";
			$vQ = DB::Query($aDBIndex, $vQStr);
			return $vQ ? $vQ->rowCount() : false;
		}
		return false;
	} 
}
?>