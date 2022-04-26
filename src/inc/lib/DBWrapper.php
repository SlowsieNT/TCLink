<?php
class DB {
	public static $m_DBs = array();
	// if not auto, need to call (once) ::RegisterTables()
	static $AUTO_REGISTER_CLASSES = true;
	static $AUTO_REGISTER_CLASSES_RESOLVE_COLUMNS = true;
	static $m_ClassTableRegistered = false;
	static $m_ClassTableContains = "DBTbl_";
	// Returns index
	public static function NewConnect($aName="", $aUser="root", $aPass="", $aHost="localhost", $aOptions=null) {
		if (null === $aUser) $aUser = "root";
		return self::NewDsnConnect("mysql:host=$aHost;dbname=$aName;charset=utf8mb4", $aUser, $aPass, $aOptions);
	}
	public static function NewDsnConnect(string $aDSN, $aUser="root", $aPass="", $aOptions=null) {
		self::$m_DBs[] = new PDO($aDSN, $aUser, $aPass, $aOptions);
		return -1 + count(self::$m_DBs);
	}
	static function HandleAutoRegister() {
		if (self::$AUTO_REGISTER_CLASSES && false === self::$m_ClassTableRegistered)
			self::RegisterTables(self::$AUTO_REGISTER_CLASSES_RESOLVE_COLUMNS);
	}
	public static function Exec(int $aIndex, string $aSQLStatement) {self::HandleAutoRegister();return self::$m_DBs[$aIndex]->exec($aSQLStatement); }
	public static function Query(int $aIndex, string $aSQLStatement) { self::HandleAutoRegister("query");
		//echo "\r\n$aSQLStatement\r\n";
		return self::$m_DBs[$aIndex]->query($aSQLStatement);
	}
	public static function GetAttribute(int $aIndex, int $aAttribute){ self::HandleAutoRegister();return self::$m_DBs[$aIndex]->getAttribute($aAttribute); }
	public static function SetAttribute(int $aIndex, int $aAttribute, $aValue){ self::HandleAutoRegister();return self::$m_DBs[$aIndex]->setAttribute($aAttribute, $aValue); }
	public static function LastInsertId(int $aIndex, $aName = null) { self::HandleAutoRegister();return self::$m_DBs[$aIndex]->lastInsertId($aName); }
	public static function Quote(int $aIndex, string $aStr) { self::HandleAutoRegister();return self::$m_DBs[$aIndex]->quote($aStr); }
	public static function Prepare(int $aIndex, string $aQuery, array $aOptions = array()) { self::HandleAutoRegister();return self::$m_DBs[$aIndex]->prepare($aQuery, $aOptions); }
	public static function InTransaction(int $aIndex=0) { self::HandleAutoRegister();return self::$m_DBs[$aIndex]->inTransaction(); }
	public static function GetAvailableDrivers(int $aIndex=0) { self::HandleAutoRegister();return self::$m_DBs[$aIndex]->getAvailableDrivers(); }
	public static function RollBack(int $aIndex=0) { self::HandleAutoRegister();return self::$m_DBs[$aIndex]->rollBack(); }
	public static function ErrorCode(int $aIndex=0) { self::HandleAutoRegister();return self::$m_DBs[$aIndex]->errorCode(); }
	public static function ErrorInfo(int $aIndex=0) { self::HandleAutoRegister();return self::$m_DBs[$aIndex]->errorInfo(); }
	public static function BeginTransaction(int $aIndex=0) { self::HandleAutoRegister();return self::$m_DBs[$aIndex]->beginTransaction(); }
	public static function Commit(int $aIndex=0) { self::HandleAutoRegister();return self::$m_DBs[$aIndex]->commit(); }
	// if not auto, need to call (once) ::RegisterTables()
	public static function RegisterTables(bool $aResolveTableColumns = false) {
		self::$m_ClassTableRegistered=true;
		foreach (get_declared_classes() as $vClass)
			if (false !== strpos($vClass, self::$m_ClassTableContains)) {
				//eval("$vClass::Register();");
				$vRC = new ReflectionClass($vClass);
				$vMethod = $vRC->getMethod("Register");
				//$vMethod->setAccessible(true);
				$vMethod->invoke(null);
			}
		if ($aResolveTableColumns)
			self::ResolveTableColumns();
	}
	// $aContains default is self::$m_ClassTableContains
	public static function ResolveTableColumns($aContains = null) {
		if (null === $aContains) $aContains = self::$m_ClassTableContains;
		foreach (get_declared_classes() as $vClass)
			if (false !== strpos($vClass, $aContains)) {
				//	eval("$vClass::ResolveTableColumns();");
				$vRC = new ReflectionClass($vClass);
				$vMethod = $vRC->getMethod("ResolveTableColumns");
				//$vMethod->setAccessible(true);
				$vMethod->invoke(null);
			}
	}
}
?>