var API = new function () {
	var _ = this, Enc = encodeURIComponent;
	_.CSRF_Token = "";
	_.Post = function (aURL, aPayload, aFunc, aErrFunc, aDataType) {
		$.ajax({
			type: "POST", url:aURL, data:aPayload,
			success:aFunc, error:aErrFunc, dataType:aDataType||"json",
			beforeSend: function (aX) { aX.setRequestHeader('X-CSRF-Token', _.CSRF_Token);}
		});
	};
	_.Get = function (aURL, aFunc, aErrFunc, aDataType) {
		$.ajax({
			type: "GET", url:aURL,
			success:aFunc, error:aErrFunc, dataType:aDataType||"json",
			beforeSend: function (aX) { aX.setRequestHeader('X-CSRF-Token', _.CSRF_Token);}
		});
	};
	_.Login = function (aUser, aPass, aCaptcha, aFunc, aErrFunc) {
		// Obfuscation is just for lulz' sake
		var vPayload = Obfuscation.GetSingularity(aUser, aPass);
		_.Post("?_=0", "_=" + vPayload + "&c=" + aCaptcha, aFunc, aErrFunc);
	};
	_.PullMessages = function (aUID, aCID, aFirstFetch, aFunc, aErrFunc) {
		if (_.PullMessages.XHR) _.PullMessages.XHR.abort();
		var vFD = new FormData();
		vFD.append("uid", aUID), vFD.append("cid", aCID), vFD.append("ff", aFirstFetch);
		var vX = new XMLHttpRequest;
		vX.open("POST", "?act=1");
		vX.setRequestHeader('X-CSRF-Token', _.CSRF_Token);
		vX.onreadystatechange = function () {
			if (4 === vX.readyState && vX.responseText)
				try {aFunc(JSON.parse(vX.responseText));} catch(e){console.log(e, vX.responseText)}
		};
		vX.onerror = aErrFunc;
		vX.send(vFD);
		_.PullMessages.XHR = vX;
	};
	_.SendMessage = function (aCID, aUID, aContent, aFunc, aErrFunc) {
		_.Post("?act=2", "cid=" + Enc(aCID.trim() || "0") + "&uid=" + Enc(aUID.trim() || "0") + "&content=" + Enc(aContent), aFunc, aErrFunc);
	};
	_.GetUserInfo = function (aUID, aFunc, aErrFunc) {
		_.Get("?act=3&uid=" + aUID, aFunc, aErrFunc);
	};
	_.ReportOnline = function (aFunc, aErrFunc) {
		_.Post("?act=4", "", aFunc, aErrFunc);
	};
	_.CreateUser = function (aNick, aLogin, aPassword, aInvite, aCaptcha, aFunc, aErrFunc) {
		_.Post("?act=5", "a=" + Enc(aNick) + "&b=" + Enc(aLogin) + "&c=" + Enc(aPassword) + "&d=" + Enc(aInvite) + "&e=" + Enc(aCaptcha), aFunc, aErrFunc);
	};
	_.SetUserData = function (aDataJson, aFunc, aErrFunc) {
		_.Post("?act=6", "j=" + Enc(JSON.stringify(aDataJson)), aFunc, aErrFunc);
	};
	_.LogOut = function (aFunc, aErrFunc) {
		_.Post("?act=7", "", aFunc, aErrFunc);
	};
	_.CreateGroup = function (aName, aPassword, aIDs, aFlags, aFunc, aErrFunc) {
		var vPayload = "a=" + Enc(aName) + "&b=" + Enc(aPassword) + "&c=" + Enc(JSON.stringify(aIDs)) + "&d=" + aFlags;
		_.Post("?act=8", vPayload, aFunc, aErrFunc);
	};
	_.AddGroupMember = function (aCID, aUID, aFunc, aErrFunc) {
		_.Post("?act=9", "cid=" + Enc(aCID) + "&uid=" + Enc(aUID), aFunc, aErrFunc);
	};
	_.RemoveGroupMember = function (aCID, aUID, aFunc, aErrFunc) {
		_.Post("?act=10", "cid=" + Enc(aCID) + "&uid=" + Enc(aUID), aFunc, aErrFunc);
	};
	_.GetConversations = function (aFunc, aErrFunc) {
		_.Post("?act=11", "", aFunc, aErrFunc);
	};
	_.DeleteGroup = function (aCID, aFunc, aErrFunc) {
		_.Post("?act=12", "cid=" + Enc(aCID), aFunc, aErrFunc);
	};
	_.DeleteMessages = function (aCID, aUID, aFunc, aErrFunc) {
		_.Post("?act=13", "cid=" + Enc(aCID) + "&uid=" + Enc(aUID), aFunc, aErrFunc);
	};
	_.SetConvMessageExpire = function (aCID, aUID, aTime, aFunc, aErrFunc) {
		_.Post("?act=14", "cid=" + Enc(aCID) + "&uid=" + Enc(aUID) + "&t=" + Enc(aTime), aFunc, aErrFunc);
	};
	_.UploadFile = function (aCID, aFile, aProtected, aFunc, aErrFunc) {
		var vFD = new FormData();
		vFD.append("cid", aCID ? aCID : "");
		vFD.append("a", aProtected>>0);
		vFD.append("ff", aFile);
		// Forge XHR:
		var vX = new XMLHttpRequest;
		vX.open("POST", "?act=15");
		vX.setRequestHeader("X-CSRF-Token", _.CSRF_Token);
		vX.onreadystatechange = function () {
			if (4 === vX.readyState)
				try {aFunc(JSON.parse(vX.responseText));} catch(e){console.log(vX.responseText)}
		};
		vX.onerror = aErrFunc;
		vX.send(vFD);
	};
	_.GroupConvWhitelistAdd = function (aUID, aFunc, aErrFunc) {
		_.Post("?act=16", "uid=" + Enc(aUID), aFunc, aErrFunc);
	};
	_.GroupConvWhitelistRemove = function (aUID, aFunc, aErrFunc) {
		_.Post("?act=17", "uid=" + Enc(aUID), aFunc, aErrFunc);
	};
	_.GroupJoin = function (aCID, aPass, aFunc, aErrFunc) {
		_.Post("?act=18", "cid=" + Enc(aCID) + "&a=" + Enc(aPass), aFunc, aErrFunc);
	};
	_.GroupLeave = function (aCID, aFunc, aErrFunc) {
		_.Post("?act=19", "cid=" + Enc(aCID), aFunc, aErrFunc);
	};
	_.GroupUpdate = function (aCID, aDataJson, aFunc, aErrFunc) {
		_.Post("?act=20", "cid=" + Enc(aCID) + "&j=" + Enc(JSON.stringify(aDataJson)), aFunc, aErrFunc);
	};
	_.GroupGetInfo = function (aCID, aFunc, aErrFunc) {
		_.Post("?act=21", "cid=" + Enc(aCID), aFunc, aErrFunc);
	};
};
