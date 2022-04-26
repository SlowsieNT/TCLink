<?php
session_start();
require_once "inc/lib/all-libs.php";
require_once "inc/db/tables/all.php";
require_once "inc/config.php";
require_once "inc/linker.php";

function RevokeSingularity($aA) {
	$aA = preg_replace("/[_\-\?=!\.,;:]+/", "", urldecode($aA));
	$aA = array_reverse(explode(":", base64_decode(StrRev($aA))));
	if (2 == count($aA))
		return array(StrRev(base64_decode($aA[0])), StrRev(base64_decode($aA[1])));
	return array("", "");
}

function StripPrivateUserInfo($aUserInfo) {
	unset($aUserInfo->login);
	unset($aUserInfo->password);
	unset($aUserInfo->flags);
}

function IsValidAvatarURL(string $aURL) {
	$aURL = trim($aURL);
	$vNotAllowed = array("//", "\\\\", "http://", "https://");
	foreach ($vNotAllowed as $vItem)
		if (0 === strpos(strtolower($aURL), strtolower($vItem)))
			return false;
	return true;
}

// Validate login attempt
if (isset($_GET["_"], $_POST["_"], $_POST["c"])) {
	header("content-type: application/javascript");
	// Deobfuscate
	$vParts = RevokeSingularity($_POST["_"]);
	// Authorize, and protect data
	$vInfo = DBTbl_Login::GetUserByAuth($vParts[0], $vParts[1]);
	if (false !== $vInfo && $_SESSION[SES_CAPTCHA]==$_POST["c"]) {
		// Set login info anyway
		$_SESSION[SES_LOGIN]=$vParts[0];
		$_SESSION[SES_PASS]=$vParts[1];
		$_SESSION[SES_CSRFTOKEN]=DBTbl_Login::Hash(microtime(true));
		StripPrivateUserInfo($vInfo);
		$vInfo->CSRF_TOKEN = $_SESSION[SES_CSRFTOKEN];
		echo json_encode($vInfo, 128);
	}
	else {
		$_SESSION[SES_CAPTCHA] = microtime(true).rand(100000000, 999999999);
		echo "null";
	}
	exit;
}

// Retrieve CSRF Token: Request
$vCSRFToken = "";
if (isset($_SERVER["HTTP_X_CSRF_TOKEN"]))
	$vCSRFToken = $_SERVER["HTTP_X_CSRF_TOKEN"];

// Delete expired messages
$vCExpiry = DBTbl_Conv::FetchAllObjects("*", "mexpire>0");
foreach ($vCExpiry as $vConv) {
	DBTbl_ConvBin::Delete2("cid='$vConv->id' and UNIX_TIMESTAMP()-createdat>" . $vConv->mexpire);
	DBTbl_ConvTexts::Delete2("cid='$vConv->id' and UNIX_TIMESTAMP()-createdat>" . $vConv->mexpire);
}

// Get current user
$vCurrentUser = DBTbl_Login::GetUserByAuth(
	isset($_SESSION[SES_LOGIN])?$_SESSION[SES_LOGIN]:"",
	isset($_SESSION[SES_PASS])?$_SESSION[SES_PASS]:""
);

if ($vCurrentUser && isset($_GET["viewFID"])) {
	$vFileId = $_GET["viewFID"];
	if (!is_numeric($vFileId)) exit;
	// Fails if vFileId not numeric
	$vCBinInfo = DBTbl_ConvBin::GetByKValue($vFileId);
	$vAllowFileFetch = false;
	if (false !== $vCBinInfo) {
		$vConvInfo = DBTbl_Conv::GetByKValue($vCBinInfo->cid);
		if (false !== $vConvInfo) {
			$vUsers = DBTbl_Conv::GetUsers($vConvInfo->id);
			foreach ($vUsers as $vUser)
				if ($vUser->id == $vCurrentUser->id) {
					$vAllowFileFetch = true;
					break;
				}
		}
		// Fetch File if user is a member of conversation
		if ($vAllowFileFetch) {
			$vCType = $vCBinInfo->contenttype;
			$vExt = $vCBinInfo->extension;
			// Allow images, and few other media to be raw
			$vShowExt = array("mp4", "mp3", "ogg", "txt", "pdf");
			if (in_array(strtolower($vExt), $vShowExt) || false !== strpos($vCType, "image/"))
				header("content-type: $vCBinInfo->contenttype");
			else {
				header("Content-Description: File Transfer");
				header("Content-Type: application/octet-stream");
				header('Content-Disposition: attachment; filename="download.'.$vExt.'"');
			}
			echo $vCBinInfo->content;
		}
	}
	exit;
}
if ($vCurrentUser && isset($_GET["act"])) {
	header("content-type: application/javascript");
	$vAct = $_GET["act"];
	$vCUID = $vCurrentUser->id;
	// vCSRFToken must match Session's token
	if (!isset($_SESSION[SES_CSRFTOKEN]))
		$_SESSION[SES_CSRFTOKEN] = DBTbl_Login::Hash(microtime(true));
	if ($vCSRFToken != $_SESSION[SES_CSRFTOKEN])
		exit;
	if ("0" === $vAct) {
		
	} else if ("1" === $vAct && isset($_POST["uid"], $_POST["cid"], $_POST["ff"])) {
		$vUID = $_POST["uid"];
		$vCID = $_POST["cid"];
		$vData = DBTbl_Conv::WaitForMessages($vCUID, $vCID, $vUID, "1" == $_POST["ff"]);
		echo json_encode($vData);
	} elseif ("2" === $vAct && isset($_POST["cid"], $_POST["content"])) {
		$vCID = $_POST["cid"];
		$vUID = isset($_POST["uid"]) ? $_POST["uid"] : "";
		$vContent = $_POST["content"];
		$vD = DBTbl_Conv::SendMessage($vCID, $vCUID, $vUID, $vContent);
		echo json_encode($vD);
	} elseif ("3" === $vAct && isset($_GET["uid"])) {
		$vUserInfo = DBTbl_Login::ResolveFetchUIDNick($_GET["uid"]);
		if (false !== $vUserInfo)
			StripPrivateUserInfo($vUserInfo);
		echo json_encode($vUserInfo);
	} elseif ("4" === $vAct) {
		// vCUID is safe enough
		DBTbl_Login::Update2(array("lastdate"=>time()), "id='$vCUID'");
	} elseif ("5" === $vAct && isset($_POST["a"], $_POST["b"], $_POST["c"], $_POST["d"], $_POST["e"])) {
		// nick,login,password,invite
		$vNick = trim($_POST["a"]); $vLogin = trim($_POST["b"]); $vPass = $_POST["c"]; $vCode = $_POST["d"];
		$vD = false;
		if ($_POST["e"] == $_SESSION[SES_CAPTCHA])
			$vD = DBTbl_Login::CreateUser($vLogin, $vNick, $vPass, $vCode);
		echo json_encode($vD);
	} elseif ("6" === $vAct && isset($_POST["j"])) {
		$vJO = (array)json_decode($_POST["j"]);
		$vUO = array();
		if (isset($vJO["id"])) { }
		if (isset($vJO["flags"])) { }
		if (isset($vJO["pass"])) $vUO["password"] = DBTbl_Login::Hash($vJO["pass"]);
		if (isset($vJO["nick"])) $vUO["nick"] = $vJO["nick"];
		if (isset($vJO["login"])) $vUO["login"] = $vJO["login"];
		if (isset($vJO["avatar"])) {
			if (!VALIDATE_AVATAR_URL || VALIDATE_AVATAR_URL && IsValidAvatarURL($vJO["avatar"]))
				$vUO["avatar"] = $vJO["avatar"];
		}
		if (isset($vJO["mood"])) $vUO["mood"] = $vJO["mood"];
		$vCanChange = 0 < count(array_keys($vUO));
		if ($vCanChange) {
			echo json_encode(DBTbl_Login::Update2($vUO, "id='$vCUID'"));
		} else echo "false";
	} elseif ("7" === $vAct) {
		$_SESSION[SES_CSRFTOKEN] = $_SESSION[SES_LOGIN] = $_SESSION[SES_PASS] = "";
		exit;
	} elseif ("8" === $vAct && isset($_POST["a"], $_POST["b"], $_POST["c"], $_POST["d"])) {
		$vName = $_POST["a"]; $vPass = $_POST["b"];
		$vIDs = $_POST["c"];
		$vD = DBTbl_ConvPub::Create($vCUID, $vName, $vPass, json_decode($vIDs), $_POST["d"]);
		echo json_encode($vD);
	} elseif ("9" === $vAct && isset($_POST["cid"], $_POST["uid"])) {
		$vD = DBTbl_ConvPub::AddMember($vCUID, $_POST["cid"], $_POST["uid"]);
		echo json_encode($vD);
	} elseif ("10" === $vAct && isset($_POST["cid"], $_POST["uid"])) {
		$vD = DBTbl_ConvPub::RemoveMember($vCUID, $_POST["cid"], $_POST["uid"]);
		echo json_encode($vD);
	} elseif ("11" === $vAct) {
		$vD = DBTbl_Conv::ListAllConversations($vCUID);
		echo json_encode($vD);
	} elseif ("12" === $vAct && isset($_POST["cid"])) {
		$vCID = $_POST["cid"];
		$vD = DBTbl_Conv::DeleteConversation($vCID, $vCUID);
		echo json_encode($vD);
	} elseif ("13" === $vAct && isset($_POST["cid"], $_POST["uid"])) {
		$vCID = $_POST["cid"]; $vUID = $_POST["uid"];
		$vD = DBTbl_Conv::DeleteAllMessages($vCID, $vCUID, $vUID);
		echo json_encode($vD);
	} elseif ("14" === $vAct && isset($_POST["cid"], $_POST["uid"], $_POST["t"])) {
		$vCID = $_POST["cid"]; $vUID = $_POST["uid"]; $vTime = $_POST["t"];
		$vD = DBTbl_Conv::SetExpirement($vCID, $vCUID, $vUID, $vTime);
		echo json_encode($vD);
	} elseif ("15" === $vAct && isset($_POST["cid"], $_POST["a"])) {
		$vProtected = $_POST["a"];
		$vCID = $_POST["cid"];
		if (!DBTbl_Conv::IsMember($vCID, $vCUID)) echo "false";
		elseif (isset($_FILES["ff"])) {
			$vFile = $_FILES["ff"];
			$vExt = pathinfo($vFile["name"], 4);//4=ext
			$vTyp = $vFile["type"];
			$vID = DBTbl_ConvBin::Insert(null, $vCUID, $vCID, $vTyp, $vExt, "", (int)$vProtected, time());
			DBTbl_ConvBin::UploadBlob($vFile, "", "id='$vID'");
			echo json_encode(array(
				$vID, $vTyp, $vExt, (int)$vProtected
			));
		}
	} elseif ("16" === $vAct && isset($_POST["uid"])) {
		$vInfo = DBTbl_Login::ResolveFetchUIDNick($_POST["uid"]);
		if (false !== $vInfo) {
			echo json_encode(DBTbl_ConvWhitelist::Add($vCUID, $vInfo->id));
		} else echo "false";
	} elseif ("17" === $vAct && isset($_POST["uid"])) {
		$vInfo = DBTbl_Login::ResolveFetchUIDNick($_POST["uid"]);
		if (false !== $vInfo) {
			echo json_encode(DBTbl_ConvWhitelist::Remove($vCUID, $vInfo->id));
		} else echo "false";
	} elseif ("18" === $vAct && isset($_POST["cid"], $_POST["a"])) {
		$vInfo = DBTbl_Conv::GetByKValue($_POST["cid"]);
		if (false !== $vInfo) {
			echo json_encode(DBTbl_Conv::JoinGroup($vInfo->id, $vCUID, $_POST["a"]));
		} else echo "false";
	} elseif ("19" === $vAct && isset($_POST["cid"])) {
		$vInfo = DBTbl_Conv::GetByKValue($_POST["cid"]);
		if (false !== $vInfo) {
			echo json_encode(DBTbl_Conv::LeaveGroup($vInfo->id, $vCUID));
		} else echo "false";
	} elseif ("20" === $vAct && isset($_POST["cid"])) {
		$vJO = (array)json_decode($_POST["j"]);
		$vUO = array();
		if (isset($vJO["oid"])) {
			$vUO["oid"] = $vJO["oid"];
			if ($vCUID != $vJO["oid"]) {
				$vInfo = DBTbl_Login::ResolveFetchUIDNick($vJO["oid"]);
				if (false === $vInfo) unset($vUO["oid"]);
				else $vUO["oid"] = $vInfo->id;
			}
		}
		if (isset($vJO["mexpire"])) $vUO["mexpire"] = $vJO["mexpire"];
		if (isset($vJO["name"])) $vUO["name"] = $vJO["name"];
		if (isset($vJO["password"])) $vUO["password"] = $vJO["password"];
		if (isset($vJO["avatar"])) {
			if (!VALIDATE_AVATAR_URL || VALIDATE_AVATAR_URL && IsValidAvatarURL($vJO["avatar"]))
				$vUO["avatar"] = $vJO["avatar"];
		}
		if (isset($vJO["flags"])) $vUO["flags"] = $vJO["flags"];
		// handle update
		$vConv = DBTbl_Conv::GetByKValue($_POST["cid"]);
		if (false !== $vConv && $vConv->oid == $vCUID) {
			echo json_encode(DBTbl_Conv::Update2($vUO, "id='$vConv->id'"));
		} else echo "false";
	} elseif ("21" === $vAct && isset($_POST["cid"])) {
		// handle update
		$vConv = DBTbl_Conv::GetByKValue($_POST["cid"]);
		if ($vConv) unset($vConv->password);
		echo json_encode($vConv);
	} elseif ("22" === $vAct && isset($_POST["cid"])) {
		
	} else echo "null";
	exit;
}
if (false !== $vCurrentUser)
	StripPrivateUserInfo($vCurrentUser);

// Retrieve CSRF Token: Session
if (isset($_SESSION[SES_CSRFTOKEN]))
	$vCSRFToken = $_SESSION[SES_CSRFTOKEN];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width">
<?php
if ($vCSRFToken)
	echo "\t<meta name=\"csrf-token\" content=\"$vCSRFToken\">\r\n";
?>
	<title>TC Link</title>
	<link rel="stylesheet" href="css/style.css?_=<?php echo time();?>">
	<script src="js/jquery.js"></script>
	<script src="js/mod.chat.api.js?_=<?php echo time();?>"></script>
	<script src="js/lib.ParseCommandLineA.js"></script>
	<script src="js/mod.ui.textarea-maxrows.js"></script>
	<script src="js/mod.tools.js?_=<?php echo time();?>"></script>
	<script src="js/mod.chat.js?_=<?php echo time();?>"></script>
</head>
<body>
<div class="chatbox d-f w960 h480">
	<div class="body east bg-20 d-f f-1">
		<div class="d-f f-1 f-dc">
			<div class="d-f f-1 of-y">
				<div class="messages wp100"></div>
			</div>
			<div class="d-f bg-16">
				<textarea class="f-1 p8 chatinput" data-maxrows="12" rows="4" placeholder="Write data to be sent."></textarea>
			</div>
		</div>
	</div>
	<div class="west bg-28 w240 d-f f-dc of-y ">
		<div class="users"></div>
	</div>
</div>
<div class="mn8">
	<a href="register.php" class="bg-28 m4 p4" target="_blank" rel="noopener noreferrer">Register</a>
	<a style="display:none" href="#" class="bg-28 m4 p4" id="logout">Log out</a>
	<a style="display:none" href="#" class="bg-28 m4 p4" id="lchat">Load Chat...</a>
	<a style="display:none" href="#" class="bg-28 m4 p4" id="ngroup">New Group</a>
	<a style="display:none" href="#" class="bg-28 m4 p4" id="suinfo">Set profile info...</a>
	<a style="display:none" href="#" class="bg-28 m4 p4" id="showac">Show all chats</a>
</div>
<div>
	<div class="popup shadow" style="display:none">
		<div class="window pa maxzi maxw480 minw240 nw50p tnw-50p d-f f-dc">
			<div class="bg-24 p8 bw">
				<div class="d-f">
					<div class="head f-1"><b class="title"></b></div>
					<div><a href="#" class="closebtn bg-32 p4-8">x</a></div>
				</div>
			</div>
			<div class="body f-1 bg-32 p8 bw"></div>
			<div class="p4 bg-24 fs-12 bw">
				<div><b>Tip</b>: Press [Escape] to exit the popup, <b>or</b> [Enter] to submit.</div>
			</div>
		</div>
	</div>
</div>
<script>
var vLICShown=0, vCurrentUser = <?php echo json_encode($vCurrentUser);?>;
function ShowLoggedInComponents() {
	if (vLICShown) return;
	vLICShown = 1;
	// Component event registration
	var vBtnLO = $("#logout"),
		vBtnLoadChat = $("#lchat"),
		vBtnSetUserInfo = $("#suinfo"),
		vBtnShowChats = $("#showac"),
		vBtnNewGroup = $("#ngroup");
	vBtnLO.click(function(){API.LogOut();setTimeout("location.reload()", 555);}).show();
	// All other things
	vBtnLoadChat.click(Chat.ShowLoadChat).show();
	vBtnNewGroup.click(Chat.ShowNewGroup).show();
	vBtnShowChats.click(Chat.ShowChats).show();
	vBtnSetUserInfo.click(Chat.ShowSetUserInfo).show();
}
(function () {
	Chat.Register({
		User: vCurrentUser,
		UI: {
			Input: $(".chatinput"),
			Messages: $(".messages"),
			Users: $(".users"),
			Popup: $(".popup")
		}
	});
	if (!Chat.User)
		Chat.ShowLogin(ShowLoggedInComponents);
	else Chat.StartReportingOnline(ShowLoggedInComponents());
})();
</script>
</body>
</html>