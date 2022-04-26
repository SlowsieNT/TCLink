<?php
require_once "inc/lib/all-libs.php";
// Edit these 3 lines below
define("DB_HOST", "localhost");
define("DB_NAME", "c");
define("DB_USER", "root");
define("DB_PASS", "");
// Line below only if file got renamed or such
define("MAIN_TABLE", "conv");
define("LINKER_FILE", "inc/linker.php");

DB::NewConnect(DB_NAME, DB_USER, DB_PASS, DB_HOST);
$vQ = DB::Query(0, "SHOW tables like '".MAIN_TABLE."'");
if (false !== $vQ && $vQ->rowCount()) {
	echo "Already installed.";
} else {
	WriteLink(DB_NAME, DB_USER, DB_PASS);
	DB::Query(0, GetSQL());
	echo "Installed.";
	rename("install.php", "install-".time().rand(11111, 99999).".php");
}

function WriteLink($aDBName, $aUser="root", $aPassword="") {
	if (!trim($aUser)) $aUser="root";
	$vContent = <<<e
<?php
\tDB::NewConnect("$aDBName", "$aUser", "$aPassword");	
?>
e;
	file_put_contents(LINKER_FILE, $vContent);
}
function GetSQL() {
	return <<<e
	--
	-- Table structure for table `conv`
	--
	CREATE TABLE `conv` (
	  `id` bigint(20) UNSIGNED NOT NULL,
	  `oid` bigint(20) NOT NULL,
	  `name` varchar(32) NOT NULL,
	  `type` int(11) NOT NULL,
	  `mexpire` int(11) NOT NULL,
	  `createdat` bigint(20) NOT NULL,
	  `flags` int(11) NOT NULL,
	  `password` varchar(24) NOT NULL,
	  `avatar` varchar(64) NOT NULL DEFAULT 'img/gr.png'
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	
	--
	-- Table structure for table `conv_bin`
	--
	CREATE TABLE `conv_bin` (
	  `id` bigint(20) UNSIGNED NOT NULL,
	  `uid` bigint(20) NOT NULL,
	  `cid` bigint(20) NOT NULL,
	  `contenttype` varchar(64) NOT NULL,
	  `extension` varchar(32) NOT NULL,
	  `content` longblob NOT NULL,
	  `protected` int(11) NOT NULL,
	  `createdat` bigint(20) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	
	--
	-- Table structure for table `conv_lmsg`
	--
	CREATE TABLE `conv_lmsg` (
	  `cid` bigint(20) NOT NULL,
	  `mid` bigint(20) NOT NULL,
	  `uid` bigint(20) NOT NULL,
	  `createdat` bigint(20) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	
	--
	-- Table structure for table `conv_priv`
	--
	CREATE TABLE `conv_priv` (
	  `cid` bigint(20) NOT NULL,
	  `uid` bigint(20) NOT NULL,
	  `sid` bigint(20) NOT NULL,
	  `createdat` bigint(20) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	
	--
	-- Table structure for table `conv_pub`
	--
	CREATE TABLE `conv_pub` (
	  `cid` bigint(20) NOT NULL,
	  `mid` bigint(20) NOT NULL,
	  `createdat` bigint(20) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	
	--
	-- Table structure for table `conv_texts`
	--
	CREATE TABLE `conv_texts` (
	  `id` bigint(20) NOT NULL,
	  `cid` bigint(20) NOT NULL,
	  `uid` bigint(20) NOT NULL,
	  `content` varchar(8192) NOT NULL,
	  `createdat` bigint(20) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	
	--
	-- Table structure for table `conv_whitelist`
	--
	CREATE TABLE `conv_whitelist` (
	  `uid` bigint(20) NOT NULL,
	  `sid` bigint(20) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	
	--
	-- Table structure for table `invite`
	--
	CREATE TABLE `invite` (
	  `code` varchar(32) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	INSERT INTO `invite` (`code`) VALUES ('1c0a53bd2a6');
	
	--
	-- Table structure for table `login`
	--
	CREATE TABLE `login` (
	  `id` bigint(20) NOT NULL,
	  `login` varchar(32) NOT NULL,
	  `nick` varchar(32) NOT NULL,
	  `password` varchar(64) NOT NULL,
	  `flags` int(11) NOT NULL,
	  `avatar` text NOT NULL DEFAULT 'img/avatar.png',
	  `mood` varchar(32) NOT NULL,
	  `lastdate` bigint(20) NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
	
	ALTER TABLE `conv` ADD UNIQUE KEY `id` (`id`);
	ALTER TABLE `conv_bin` ADD UNIQUE KEY `id` (`id`);
	ALTER TABLE `conv_lmsg` ADD UNIQUE KEY `cid` (`cid`);
	ALTER TABLE `conv_texts` ADD PRIMARY KEY (`id`);
	ALTER TABLE `invite` ADD UNIQUE KEY `code` (`code`);
	ALTER TABLE `login` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `login` (`login`), ADD UNIQUE KEY `nick` (`nick`);
	
	ALTER TABLE `conv` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
	ALTER TABLE `conv_bin` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
	ALTER TABLE `conv_texts` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
	ALTER TABLE `login` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
e;
}
?>