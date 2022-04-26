<?php
class DBTBase {
	static $RegisteredClasses = array();
	// Used to register table name, and set its primary/unique key for later use
	// Register() is called at the end of file that contains the class that extends this class
	// Example: class A extends DBTBase { static $Name, $Key, $DBIndex; } A::Register(0);
	// if aDBIndex is -1, then DBIndex will be used- if DBIndex is valid range
	// NOTE: it is NOT REQUIRED to call it if you set DBIndex in the class, Register will be called automatically
	public static function Register(int $aDBIndex=-1, bool $aIgnoreColumns=true) {
		$vCCName = get_called_class();
		$vRC = new ReflectionClass($vCCName);
		$vTableName = $vRC->hasProperty("Name") ? $vRC->getProperty("Name")->getValue() : "";
		$vTableKey = $vRC->hasProperty("Key") ? $vRC->getProperty("Key")->getValue() : "";
		$vDBIndex = $vRC->hasProperty("DBIndex") ? $vRC->getProperty("DBIndex")->getValue() : null;
		// Handle $vDBIndex
		if (-1 === $aDBIndex && is_numeric($vDBIndex) && $vDBIndex > -1)
			$aDBIndex = (int)$vDBIndex;
		self::$RegisteredClasses[$vCCName] = array($aDBIndex, $vTableName, $vTableKey, array());
		// Fill missing table columns if no ignore
		if (!$aIgnoreColumns)
			self::ResolveTableColumns();
	}
	public static function ResolveTableColumns(&$aTableColumnsRef=null) {
		$vCCName = get_called_class();
		$aTableColumnsRef = self::$RegisteredClasses[$vCCName][3] = self::GetTableColumns(self::_()[0], self::_()[1]);
		return $aTableColumnsRef;
	}
	// Used to retrieve information of descending classes' table information upon Register()
	public static function _() {
		$vCCName = get_called_class();
		if (!isset(self::$RegisteredClasses[$vCCName]) && DB::$AUTO_REGISTER_CLASSES)
			DB::RegisterTables(DB::$AUTO_REGISTER_CLASSES_RESOLVE_COLUMNS);
		return self::$RegisteredClasses[$vCCName];
	}
	// Retrieve columns from database table, used for dynamic data handling
	public static function GetTableColumns(int $aDBIndex, $aTName) {
		return DB::Query($aDBIndex, "DESCRIBE $aTName")->FetchAll(PDO::FETCH_OBJ);
	}
	// Retrieve one row
	public static function FetchObject(string $aSelector="*", string $aWhere="", bool $aUseCustomWhere = false, bool $aFetchAll=false) {
		return DBTProcessor::Fetch(self::_()[0], self::_()[1], PDO::FETCH_OBJ, $aWhere, $aSelector, $aUseCustomWhere, $aFetchAll);
	}
	// Retrieve one row
	public static function Fetch($aMode, string $aSelector="*", string $aWhere="", bool $aUseCustomWhere = false, bool $aFetchAll=false) {
		return DBTProcessor::Fetch(self::_()[0], self::_()[1], $aMode, $aWhere, $aSelector, $aUseCustomWhere, $aFetchAll);
	}
	// Retrieve all rows
	public static function FetchAll($aMode, string $aSelector="*", string $aWhere="", bool $aUseCustomWhere = false) {
		return self::Fetch($aMode, $aSelector, $aWhere, $aUseCustomWhere, true);
	}
	// Retrieve all rows
	public static function FetchAllObjects(string $aSelector="*", string $aWhere="", bool $aUseCustomWhere = false) {
		return self::FetchObject($aSelector, $aWhere, $aUseCustomWhere, true);
	}
	// Retrieve Row count
	public static function RowCount(string $aSelector="*", string $aWhere="", bool $aUseCustomWhere = false) {
		return DBTProcessor::RowCount(self::_()[0], self::_()[1], $aWhere, $aSelector, $aUseCustomWhere);
	}
	// Check whether there are columns
	public static function HasRows(string $aSelector="*", string $aWhere="", bool $aUseCustomWhere = false) {
		return DBTProcessor::HasRows(self::_()[0], self::_()[1], $aWhere, $aSelector, $aUseCustomWhere);
	}
	// Retrieve data by default key, and its value from aKValue argument
	public static function GetByKValue($aKValue, string $aSelector="*") {
		SQL::Esc($aKValue);
		return self::Fetch(PDO::FETCH_OBJ, $aSelector, self::_()[2]."='$aKValue'");
	}
	// Returns array
	static function GetIData($aArgs, $aOffset=0, &$aTI=null) {
		$aTI = self::_();
		if (0 != $aOffset) $aArgs = array_slice($aArgs, $aOffset);
		$vCols = $aTI[3];
		$vIData = array(); $vIdx = 0; $vArgc = count($aArgs);
		foreach ($vCols as $vItem)
			if ($vIdx < $vArgc) {
				$vValue = $aArgs[$vIdx++];
				// SQL helper will strip SQLi
				if (!is_null($vValue))
					$vIData[$vItem->Field] = $vValue;
			} else break;
		return $vIData;
	}
	public static function GetFirstBlobFieldName() {
		foreach (self::_()[3] as $vField)
			if (false !== strpos(strtolower($vField->Type), "blob")) {
				return $vField->Field;
				break;
			}
		return "";
	}
	// Inserts, or appends to blob
	public static function IAppendBlob($aData, $aBlobFieldName="", $aWhere=null) {
		$vTI = self::_();
		if (!trim($aBlobFieldName))
			$aBlobFieldName = self::GetFirstBlobFieldName();
		return DBTProcessor::IAppendBlob($vTI[0], $vTI[1], $aData, $aBlobFieldName, $aWhere);
	}
	// Uploads Blob field in database table
	// It is best to insert metadata first, then the actual content
	// Returns health of file (eg: 1 if entirely uploaded), or false
	public static function UploadBlobString($aData, $aBlobFieldName="", $aWhere=null) {
		$vTI = self::_();
		if (!trim($aBlobFieldName))
			$aBlobFieldName = self::GetFirstBlobFieldName();
		$vLength = 65535; $vOffset=0;
		$vAffected = 0; $vIterations = 0;
		while (true) {
			$vPiece = substr($aData, $vOffset, $vLength);
			$vFlag = DBTProcessor::IAppendBlob($vTI[0], $vTI[1], $vPiece, $aBlobFieldName, $aWhere);
			if (false === $vFlag)
				return $vFlag;
			if (strlen($vPiece) < $vLength)
				break;
			else $vOffset += $vLength;
			$vAffected += $vFlag;
			$vIterations++;
		}
		return $vIterations > 0 ? $vAffected/$vIterations : false;
	}
	// Uploads Blob field in database table
	// It is best to insert metadata first, then the actual content
	// $aFile is based on $_FILES
	// Returns health of file (eg: 1 if entirely uploaded), or false
	public static function UploadBlob($aFile, $aBlobFieldName="", $aWhere=null) {
		$vTI = self::_();
		if (!trim($aBlobFieldName))
			$aBlobFieldName = self::GetFirstBlobFieldName();
		$vFileHandle = fopen($aFile["tmp_name"], "rb");
		if (false !== $vFileHandle) {
			DBTProcessor::Update($vTI[0], $vTI[1], array($aBlobFieldName=>""), $aWhere);
			$vAffected = 0; $vIterations = 0;
			while (!feof($vFileHandle)) {
				// Retrieve data
				$vData = fread($vFileHandle, 200000); // Not KiB
				$vFlag = DBTProcessor::IAppendBlob($vTI[0], $vTI[1], $vData, $aBlobFieldName, $aWhere);
				if (false === $vFlag)
					return $vFlag;
				$vAffected += $vFlag;
				$vIterations++;
			}
			fclose($vFileHandle);
			return $vIterations > 0 ? $vAffected/$vIterations : false;
		}
		return false;
	}
	// Insert Data, all according to ORDER of columns in database!
	// Returns id, or false
	public static function Insert() { return self::Insert3(func_get_args()); }
	// Insert with Array
	// Example $aData = array ("field" => "value", "field2" => "value2");
	public static function Insert2(array $aData) {
		$vTI = self::_();
		return DBTProcessor::Insert($vTI[0], $vTI[1], $aData);
	}
	// Insert Data, $aArray according to ORDER of columns in database!
	// Returns id, or false
	public static function Insert3(array $aArray, $aOffset=0) {
		$vIData = self::GetIData($aArray, $aOffset, $vTI);
		return DBTProcessor::Insert($vTI[0], $vTI[1], $vIData);
	}
	// Update Where, all according to ORDER of columns in database!
	// Returns number of affected rows, or false
	public static function Update($aWhere) { return self::Update3($aWhere, func_get_args(), 1); }
	// Update with Array
	// Example $aData = array ("field" => "value", "field2" => "value2");
	public static function Update2(array $aData, $aWhere="", $aUseCustomWhere=false) {
		$vTI = self::_();
		return DBTProcessor::Update($vTI[0], $vTI[1], $aData, $aWhere, $aUseCustomWhere);
	}
	// Update Where, $aArray according to ORDER of columns in database!
	// Returns number of affected rows, or false
	public static function Update3($aWhere, array $aArray, $aOffset=0) {
		$vIData = self::GetIData($aArray, $aOffset, $vTI);
		return DBTProcessor::Update($vTI[0], $vTI[1], $vIData, $aWhere);
	}
	// Update Custom Where, all according to ORDER of columns in database!
	// Returns number of affected rows, or false
	public static function UpdateCW($aWhere) {
		$vIData = self::GetIData(func_get_args(), 1, $vTI);
		return DBTProcessor::Update($vTI[0], $vTI[1], $vIData, $aWhere, 1);
	}
	// Delete By default key
	// Returns number of affected rows
	public static function Delete($aKeyField) {
		SQL::Esc($aKeyField); // protect key field
		return DBTProcessor::Delete(self::_()[0], self::_()[1], self::_()[2]."='$aKeyField'");
	}
	// Delete Where, or Custom Where
	// Returns number of affected rows
	public static function Delete2($aWhere="", $aUseCustomWhere=false) {
		return DBTProcessor::Delete(self::_()[0], self::_()[1], $aWhere, $aUseCustomWhere);
	}
	public static function DeleteAll() {
		return self::Delete2("", 1);
	}
}
?>
