var Chat = new function () {
	var _ = this;
	_.Audio = new Audio("mp3/sms.mp3");
	_.UI = {Input:null, Messages:null, Users:null, Popup: null};
	_.AudioVolume = 0.6;
	_.User = {login:null,mood:null,lastdate:null,nick:null,id:null};
	_.Users = [];
	_.UsersPending = {}; _.Queue = {};
	_.CID = _.UID = 0, _.FirstFetch = 1;
	_.Encry = {
		Protected: 0, Key: "abc",
		Encrypt: function (aX) { return aX; },
		Decrypt: function (aX) { return aX; },
		EncryptBuffer: function (aBuffer, aFunc) {
			var vUint8Arr = new Uint8Array(aBuffer);
			// Do as needed...
			aFunc(vUint8Arr);
		}, DecryptBuffer: function (aBuffer, aFunc) {
			var vUint8Arr = new Uint8Array(aBuffer);
			// Do as needed...
			aFunc(vUint8Arr);
		}
	};
	_.PlayAudio = function () {
		_.Audio.currentTime=0;
		_.Audio.volume = _.AudioVolume;
		try{_.Audio.play();}catch(e){}
	};
	_.Register = function (aConfig) {
		_.UI = aConfig.UI;
		_.User = aConfig.User;
		if (!API.CSRF_Token)
			API.CSRF_Token = $("meta[name=csrf-token]").attr("content");
		$(_.UI.Input).on("paste", function (aE) {
			var vD = aE.originalEvent.clipboardData || window.clipboardData,
				vFile = vD.files[0], vV = this;
			if (vFile) {
				var vFR = new FileReader;
				vFR.onload = function () {
					_.Encry.EncryptBuffer(vFR.result, function (aArrayBuffer) {
						var vFile2 = new File([aArrayBuffer], vFile.name, {type: vFile.type});
						API.UploadFile(_.CID, vFile2, _.Encry.Protected, function (aResp) {
							if (aResp) {
								vV.value += "[file " + aResp.join("|") + "]";
							} else _.ShowMessage("Error", "Invalid, or unspecified CID/UID.");
						}, function () {
							_.ShowMessage("Failure", "Internal error.");
						});
					});
				};
				vFR.readAsArrayBuffer(vFile);
			}
		});
		$(_.UI.Input).on("keydown", function (aE) {
			if (13 === aE.keyCode && !aE.shiftKey) {
				// Backend will accept empty message
				if (_.ProcessCommand(this.value)) {
					this.value = "";
					return false;
				}
				if (this.value.trim()) _.Send(this.value);
				this.value = "";
				return false;
			}
		});
		$(window).click(function (aE) {
			var vT = aE.target, vDLDI = $(vT).attr("data-dl-decrypt-nfo");
			if ("string" === typeof vDLDI) {
				vDLDI = vDLDI.split(";");
				_.ShowMessage("Information", "File is being retrieved...");
				_.ResolveBuffer(vDLDI[0], function (aObj) {
					_.ShowMessage("Downloaded in Memory", '<a class="fg-o" href="'+aObj.URL+'" download="download.' + vDLDI[1]+'">Click to Save</a>');
				});
			}
		});
		$(window).on("keydown", function (aE) {
			if (27 === aE.keyCode) _.PopupClose();
		});
		$(".closebtn", _.UI.Popup).click(function () { _.PopupClose(); });
		_.UI.Popup.on("keydown", function (aE) {
			if (13 === aE.keyCode)
				$(".submit", _.UI.Popup).click();
		});
	};
	_.ProcessCommand = function (aMessage) {
		if (0 != aMessage.indexOf("/")) return false;
		var vArgs = ParseCommandLineA(aMessage.slice(1)),
			vTyp = vArgs[0];
		if (vTyp == "am") {
			var vUID = vArgs[1];
			if (_.CID) API.AddGroupMember(_.CID, vUID, function (aResp) {
				if (true === aResp) {
					var vUser = _.GetUserBy(vUID, "id");
					if (!IsN(vUID))
						vUser = _.GetUserBy(vUID, "nick");
					API.GetUserInfo(vUID, function (aInfo) {
						if (aInfo) _.UIAddUser(aInfo, "am");
					});
					_.ShowMessage("Success", "Member added.", 555);
				} else _.ShowMessage("Error", "Already a member, or is private chat.", 555);
			}, function () {
				_.ShowMessage("Failure", "Server error, try again.");
			}); else _.ShowMessage("Failure", "Specify CID (load group)");
			return true;
		} else if (vTyp == "rm") {
			var vUID = vArgs[1];
			if (_.CID) API.RemoveGroupMember(_.CID, vUID, function (aResp) {
				if (true === aResp) {
					var vUser = _.GetUserBy(vUID, "id");
					if (!IsN(vUID))
						vUser = _.GetUserBy(vUID, "nick");
					vUser && $("[data-uid='" + vUser.id + "']", _.UI.Users).remove();
					_.ShowMessage("Success", "Member removed.", 555);
				} else _.ShowMessage("Error", "Already a member, or is private chat.", 555);
			}, function () {
				_.ShowMessage("Failure", "Server error, try again.");
			}); else _.ShowMessage("Failure", "Specify CID (load group)");
			return true;
		} else if (vTyp == "rg") {
			if (_.CID) API.DeleteGroup(_.CID, function (aResp) {
				if (true === aResp) {
					_.Clear(1);
					_.ShowMessage("Success", "Group removed.", 555);
				} else _.ShowMessage("Error", "No permission, or is private chat.", 555);
			}, function () {
				_.ShowMessage("Failure", "Server error, try again.");
			}); else _.ShowMessage("Failure", "Specify CID (load group)");
			return true;
		} else if (vTyp == "del") {
			if (_.CID) API.DeleteMessages(_.CID, _.UID, function (aResp) {
				if (true === aResp) {
					_.Clear(0,1);
					_.ShowMessage("Success", "Messages removed.", 444);
				} else _.ShowMessage("Error", "No permission, or is private chat.", 444);
			}, function () {
				_.ShowMessage("Failure", "Server error, try again.");
			}); else _.ShowMessage("Failure", "Specify CID (load group)");
			return true;
		} else if (vTyp == "e") {
			var vTime = vArgs[1];
			if (_.CID) API.SetConvMessageExpire(_.CID, _.UID, vTime>>0, function (aResp) {
				if (true === aResp) {
					_.ShowMessage("Success", "Expirement is set.", 444);
				} else _.ShowMessage("Error", "No permission, or is private chat.", 444);
			}, function () {
				_.ShowMessage("Failure", "Server error, try again.");
			}); else _.ShowMessage("Failure", "Specify CID (load group)");
			return true;
		} else if (vTyp == "wa") {
			var vVal = vArgs[1];
			API.GroupConvWhitelistAdd(vVal, function (aResp) {
				if (aResp) _.ShowMessage("Success", "Whitelisted.", 555); else _.ShowMessage("Error", "May already exist.", 555);
			}, function () {
				_.ShowMessage("Failure", "Server error, try again.");
			});
			return true;
		} else if (vTyp == "wr") {
			var vVal = vArgs[1];
			API.GroupConvWhitelistRemove(vVal, function (aResp) {
				if (aResp) _.ShowMessage("Success", "Unwhitelisted.", 555); else _.ShowMessage("Error", "May already exist.", 555);
			}, function () {
				_.ShowMessage("Failure", "Server error, try again.");
			});
			return true;
		} else if (vTyp == "join") {
			var vVal = vArgs[1], vPass = vArgs[2];
			_.CancelPullMessages(1);
			API.GroupJoin(vVal, vPass, function (aResp) {
				if (aResp) {
					_.CID = vVal; _.Clear(1); _.ReceiveLoop(1);
					_.ShowMessage("Success", "<span style=color:lime>ACCESS GRANTED</a>", 555);
				}
				else _.ShowMessage("Error", "Access denied.");
			}, function () {
				_.ShowMessage("Failure", "Server error, try again.");
			});
			return true;
		} else if (vTyp == "leave") {
			var vVal = vArgs[1]||_.CID;
			API.GroupLeave(vVal, function (aResp) {
				if (aResp) {
					_.CancelPullMessages(1);
					_.Clear(1);
					_.ShowMessage("Success", "Left the group.", 555);
				}
				else _.ShowMessage("Error", "Might have already left.", 555);
			}, function () {
				_.ShowMessage("Failure", "Server error, try again.");
			});
			return true;
		} else if (vTyp == "mef") {
			var vFI = $("<input type=file>");
			vFI.change(function () {
				var vFile = this.files[0], vFR = new FileReader;
				vFR.onload = function () {
					var vDefEnB = _.Encry.EncryptUint8Array, vDefDeB = _.Encry.DecryptUint8Array;
					_.Encry = eval(vFR.result);
					if (!_.Encry.EncryptUint8Array) _.Encry.EncryptUint8Array = vDefEnB;
					if (!_.Encry.DecryptUint8Array) _.Encry.DecryptUint8Array = vDefDeB;
				};
				vFR.readAsText(vFile);
			}).click();
		} else if (vTyp == "sid") {
			_.ShowMessage("Information",
			"CID: " + MT.XSS(_.CID) +
			"<br>UID: " + MT.XSS(_.UID) +
			"<br>(<b>Note</b>: UID can also be a Nick!)"
			);
			return true;
		} else if (vTyp == "ss") {
			_.ShowSetGroupInfo();
			return true;
		} else if (vTyp == "cc") {
			_.CancelPullMessages(1);
			_.Clear(1);
			return true;
		} else if (vTyp == "cls") {
			_.Clear(1);
		}
	};
	_.CancelPullMessages = function (aReset) {
		if (API.PullMessages.XHR)
			API.PullMessages.XHR.abort();
		if (aReset) _.CID = _.UID = 0;
	}
	_.ShowMessage = function (aTitle, aHtml, aTime) {
		$(".body", _.UI.Popup).html(aHtml);
		$(".title", _.UI.Popup).text(aTitle);
		_.UI.Popup.show();
		if (aTime > 0 && aTime < 7001)
			setTimeout(function () { _.PopupClose(); }, aTime);
	};
	_.ShowSetUserInfo = function () {
		var vBody = $(".body", _.UI.Popup);
		vBody.html('\
		<div class="message"></div>\
		<div>(Leave a field blank if needs no change)</div>\
		<div><input type="text" data-field="id" class="basic-tb" placeholder="UID"></div>\
		<div><input type="text" data-field="avatar" class="basic-tb" placeholder="Avatar"></div>\
		<div><input type="text" data-field="nick" class="basic-tb" placeholder="Nick"></div>\
		<div><input type="text" data-field="mood" class="basic-tb" placeholder="Mood"></div>\
		<div><input type="text" data-field="login" class="basic-tb" placeholder="Login"></div>\
		<div><input type="text" data-field="password" class="basic-tb" placeholder="Pass"></div>\
		<div><input type="text" data-field="flags" class="basic-tb" placeholder="Flags"></div>\
		<div><button class="submit basic-btn bg-48">Save</button></div>\
		');
		$(".title", _.UI.Popup).text("Set user information");
		$(".submit", _.UI.Popup).click(function () {
			var vJO = {}, vMsgH = $(".message", _.UI.Popup);
			$(".basic-tb[data-field]", _.UI.Popup).each(function () {
				if (this.value.trim())
					vJO[$(this).attr("data-field")] = this.value;
			});
			API.SetUserData(vJO, function (aResp) {
				if (!aResp)
					vMsgH.text("Access denied.");
				else _.PopupClose();
			}, function () {
				vMsgH.text("Internal error.");
			});
		});
		API.GetUserInfo(_.User.id, function (aResp) {
			if (aResp && aResp.id) for (var vN in aResp)
				$("[data-field='" + vN + "']", _.UI.Popup).val(aResp[vN]);
			// Lastly show popup
			_.UI.Popup.show();
		}, function () {
			vBody.html("Failed to fetch user data.");
			// Lastly show popup
			_.UI.Popup.show();
		});
	};
	_.ShowLoadChat = function () {
		var vBody = $(".body", _.UI.Popup);
		vBody.html('\
		<div class="message"></div>\
		<div><input type="text" class="uid basic-tb" placeholder="Nick, or UID"></div>\
		<div><input type="text" class="cid basic-tb" placeholder="Chat ID"></div>\
		<div><button class="submit basic-btn bg-48">Load</button></div>\
		');
		$(".title", _.UI.Popup).text("Load Chat");
		$(".submit", _.UI.Popup).click(function () {
			var vUID = $(".uid", _.UI.Popup).val(),
				vCID = $(".cid", _.UI.Popup).val();
			if (vUID.trim()) {
				_.CID = 0; _.UID = vUID;
			}
			else {
				_.CID = IsN(vCID) ? vCID : 0; _.UID = 0;
			}
			if (_.CID || _.UID) {
				_.Clear(1);
				_.ReceiveLoop(1);
			}
			_.PopupClose();
		});
		// Lastly show popup
		_.UI.Popup.show();
	};
	_.ShowAddWhitelist = function () {
		var vBody = $(".body", _.UI.Popup);
		vBody.html('\
		<div class="message"></div>\
		<div><input type="text" class="uid basic-tb" placeholder="Nick, or UID"></div>\
		<div><button class="submit basic-btn bg-48">Done</button></div>\
		');
		$(".title", _.UI.Popup).text("Whitelist: Add");
		$(".submit", _.UI.Popup).click(function () {
			
			_.PopupClose();
		});
		// Lastly show popup
		_.UI.Popup.show();
	};
	_.UICreateChatHandle = function (aUser, aJObj) {
		var vHnd = $('<div class="convo mn4 bg-24">\
		<div class="d-f p8 f-vc">\
			<img class="avatar" width=48 height=48 alt="Avatar">\
			<div class="f-1 d-f f-dc mw4">\
				<b class="name"></b>\
				<div class="nof">\
					<b class="fs-14 who"></b>:<span class="fs-12 msg"></span>\
				</div>\
			</div>\
		</div>\
	</div>'), vNick = aJObj.u ? aJObj.u.nick : "?", vAvatar = "img/avatar.png", vConvName = aJObj.c.name||"<No group name>";
		if (aJObj.c.avatar)
			vAvatar = aJObj.c.avatar;
		if (!vAvatar && aJObj.u)
			vAvatar = aJObj.u.avatar;
		$(".avatar", vHnd).attr("src", vAvatar);
		$(".name", vHnd).text(vConvName);
		$(".who", vHnd).text(aJObj.c.type?vNick:(aUser.nick==vNick?"me":"them"));
		$(".msg", vHnd).text(aJObj.m?aJObj.m.content.slice(0, 32):"").attr("title", aJObj.m?new Date(aJObj.m.createdat*1e3):"");
		return vHnd.attr("data-cid", aJObj.c.id).attr("title", vConvName);
	}
	_.PopupClose = function () {
		_.UI.Popup.hide();
		$(".body", _.UI.Popup).html("");
	};
	_.ShowChats = function () {
		var vBody = $(".body", _.UI.Popup), vChats = [];
		vBody.html('\
		<input type="text" class="nick basic-tb" placeholder="Search Private (nick)">\
		<input type="text" class="name basic-tb" placeholder="Search Groups (name)">\
		<div class="chats maxh360 of-y"></div>\
		');
		function RenderChats(aArray) {
			var vChats = $(".chats", _.UI.Popup);
			vChats.html("");
			for (var vI = 0; vI < aArray.length; vI++) {
				var vChatH = _.UICreateChatHandle(_.User, aArray[vI]);
				vChatH.click(function () {
					_.Clear(1);
					_.CID = $(this).attr("data-cid"); _.UID = 0;
					_.ReceiveLoop(1);
					_.PopupClose();
				});
				vChats.append(vChatH);
			}
		}
		$(".title", _.UI.Popup).text("All Chats");
		$(".nick", _.UI.Popup).on("input", function () {
			var vSearch = TTL(this.value), vR = [];
			for (var vI = 0; vI < vChats.length; vI++) {
				var vChat = vChats[vI];
				if (!vChat.c.type)
					if (vChat.u && -1 < TTL(vChat.c.name).indexOf(vSearch))
						vR.push(vChat);
			}
			RenderChats(vSearch ? vR : vChats);
		});
		$(".name", _.UI.Popup).on("input", function () {
			var vSearch = TTL(this.value), vR = [];
			for (var vI = 0; vI < vChats.length; vI++) {
				var vChat = vChats[vI];
				if (vChat.c.type)
					if (-1 < TTL(vChat.c.name).indexOf(vSearch))
						vR.push(vChat);
			}
			RenderChats(vSearch ? vR : vChats);
		});
		API.GetConversations(function (aResp) {
			if (aResp) {
				if (aResp.length) {
					vChats = aResp;
					RenderChats(aResp);
				}
				// Lastly show popup
				_.UI.Popup.show();
			} else _.ShowMessage("Error", "Internal error.");
		}, function () {
			_.ShowMessage("Error", "Internal error.");
		});
	};
	_.Clear = function (aUsersReset, aNoClearUsers) {
		if (aUsersReset) _.Users=[];
		if (!aNoClearUsers) _.UI.Users.html("");
		_.UI.Messages.html("");
	};
	_.ShowNewGroup = function () {
		var vBody = $(".body", _.UI.Popup);
		vBody.html('\
		<div class="message"></div>\
		<div><input type="text" class="name basic-tb" placeholder="Name (or blank)"><input type="text" class="pass basic-tb mw4" placeholder="Join password (or blank)"></div>\
		<div class="members maxh360 of-y">\
			<div><input type="text" class="member basic-tb" placeholder="Nick, or UID"><input type="text" class="member basic-tb mw4" placeholder="Nick, or UID"></div>\
		</div>\
		<div class="mn4 mw4"><label><input class="unlisted" type="checkbox"> Unlisted</label></div>\
		<div class="mn4 mw4"><label><input class="nojoin" type="checkbox"> No joins</label></div>\
		<div class="mn4 mw4"><label><input class="rpass" type="checkbox" checked> Require Password</label></div>\
		<div class="mn4"><button class="new basic-btn bg-48">Add</button><button class="submit basic-btn bg-48">Create</button></div>\
		');
		$(".title", _.UI.Popup).text("New Group");
		$(".new", _.UI.Popup).click(function () {
			$(".members", _.UI.Popup).append('<div><input type="text" class="member basic-tb" placeholder="Nick, or UID"><input type="text" class="member basic-tb mw4" placeholder="Nick, or UID"></div>');
		});
		$(".submit", _.UI.Popup).click(function () {
			var vIDs = Array.apply(0, $(".members .member", _.UI.Popup).map(function(){
				return $(this).val();
			})), vMsgH = $(".message", _.UI.Popup);
			var vBtn = $(this),
			 	vP = _.UI.Popup,
				vName = $(".name", vP).val(), vPass = $(".pass", vP).val(),
				vRPass = MT.JQCValue(".rpass", vP, 1),
				vNJoin = MT.JQCValue(".nojoin", vP, 2),
				vUnlisted = MT.JQCValue(".unlisted", vP, 4);
			API.CreateGroup(vName, vPass, vIDs, vUnlisted|vNJoin|vRPass, function (aResp) {
				vBtn.show();
				if (!aResp) {
					vMsgH.text("Access denied.");
				} else if (IsN(aResp)) {
					_.PopupClose();
					_.Clear(1);
					_.CID = aResp; _.UID = 0;
					_.ReceiveLoop(1);
				} else vMsgH.text("Unknown error.");
			}, function () {
				vBtn.show();
				vMsgH.text("Internal error.");
			});
			vBtn.hide();
		});
		// Lastly show popup
		_.UI.Popup.show();
	};
	_.StartReportingOnline = function () {
		if (_.StartReportingOnlineTmr) clearInterval(_.StartReportingOnlineTmr);
		if (0 > document.cookie.indexOf("privacy=1")) _.StartReportingOnlineTmr = setInterval(API.ReportOnline, 8e3);
	};
	_.ShowLogin = function (aFunc) {
		var vBody = $(".body", _.UI.Popup);
		vBody.html('\
		<div class="message"></div>\
		<div><img src="captcha.php" alt="Captcha"></div>\
		<div><input type="text" class="captcha basic-tb" placeholder="Captcha"></div>\
		<div><input type="text" class="login basic-tb" placeholder="Login"></div>\
		<div><input type="password" class="pass basic-tb" placeholder="Password"></div>\
		<div><button class="submit basic-btn bg-48">Log in</button></div>\
		');
		$(".title", _.UI.Popup).text("Login");
		$(".submit", _.UI.Popup).click(function () {
			var vBtn = $(this);
			vBtn.hide();
			API.Login($(".login", _.UI.Popup).val(), $(".pass", _.UI.Popup).val(), $(".captcha", _.UI.Popup).val(), function (aResp) {
				if (aResp) {
					_.PopupClose();
					_.StartReportingOnline();
					_.User = aResp;
					API.CSRF_Token = aResp.CSRF_TOKEN;
					if (aFunc) aFunc();
				}
				else {
					$("img", _.UI.Popup).attr("src", "captcha.php");
					$(".message", _.UI.Popup).text("Invalid login.");
					vBtn.show();
				}
				vBtn.show();
			}, function () {
				$(".message", _.UI.Popup).text("Internal error.");
				vBtn.show();
			});
		});
		// Lastly show popup
		_.UI.Popup.show();
	};
	_.ShowSetGroupInfo = function () {
		var vBody = $(".body", _.UI.Popup);
		vBody.html('\
		<div class="message"></div>\
		<div>(Leave a field blank if needs no change)</div>\
		<input type="hidden" data-field="flags">\
		<div><input type="text" data-field="id" class="basic-tb" placeholder="ID" readonly></div>\
		<div><input type="text" data-field="oid" class="basic-tb" placeholder="Owner UID/Nick"></div>\
		<div><input type="text" data-field="mexpire" class="basic-tb" placeholder="Expirement"></div>\
		<div><input type="text" data-field="name" class="basic-tb" placeholder="Name"></div>\
		<div><input type="text" data-field="password" class="basic-tb" placeholder="Password"></div>\
		<div><input type="text" data-field="avatar" class="basic-tb" placeholder="Avatar"></div>\
		<div class="mn4 mw4"><label><input class="unlisted" type="checkbox"> Unlisted</label></div>\
		<div class="mn4 mw4"><label><input class="nojoin" type="checkbox"> No joins</label></div>\
		<div class="mn4 mw4"><label><input class="rpass" type="checkbox"> Require Password</label></div>\
		<div><button class="submit basic-btn bg-48">Save</button></div>\
		');
		$(".title", _.UI.Popup).text("Set group information");
		var vFlags = 0;
		$(".submit", _.UI.Popup).click(function () {
			var vJO = {}, vMsgH = $(".message", _.UI.Popup), vP = _.UI.Popup;
			vFlags = MT.JQCValue(".unlisted", vP, 1) | MT.JQCValue(".nojoin", vP, 2) | MT.JQCValue(".rpass", vP, 4);
			$(".basic-tb[data-field]", _.UI.Popup).each(function () {
				if (this.value.trim())
					vJO[$(this).attr("data-field")] = this.value;
			});
			vJO.flags = vFlags;
			API.GroupUpdate(_.CID, vJO, function (aResp) {
				if (!aResp)
					vMsgH.text("Access denied.");
				else _.PopupClose();
			}, function () {
				vMsgH.text("Internal error.");
			});
		});
		API.GroupGetInfo(_.CID, function (aResp) {
			if (aResp && aResp.id) {
				vFlags = aResp.flags;
				for (var vN in aResp) $("[data-field='" + vN + "']", _.UI.Popup).val(aResp[vN]);
				//unlisted=1,nojoin=2,rpass=4;
				$(".unlisted", _.UI.Popup)[0].checked = vFlags & 1;
				$(".nojoin", _.UI.Popup)[0].checked = vFlags & 2;
				$(".rpass", _.UI.Popup)[0].checked = vFlags & 4;
			}
			// Lastly show popup
			_.UI.Popup.show();
		}, function () {
			vBody.html("Failed to fetch group data.");
			// Lastly show popup
			_.UI.Popup.show();
		});
	};
	_.GetUserBy = function (aVal, aField) {
		if (!_.Users.length) return;
		for (var vI = 0; vI < _.Users.length; vI++)
			if (aVal == _.Users[vI][aField])
				return _.Users[vI];
	};
	_.TryScroll = function (aElement, aCanScroll) {
		if (aCanScroll) {
			aElement.scrollTop = aElement.scrollHeight;
			var vTmr = setInterval(function () {
				if (!IsAtBottomScroll(aElement))
					aElement.scrollTop = aElement.scrollHeight;
				else clearInterval(vTmr);
			}, 555);
		}
	};
	_.UIAddUser = function (aInfo) {
		var vUsr = $("[data-uid='" + aInfo.id + "']", _.UI.Users);
		if (!vUsr.length) {
			if (!_.GetUserBy(aInfo.id, "id")) _.Users.push(aInfo);
			vUsr = Chat.CreateUserOnline(aInfo.id, aInfo.avatar, aInfo.nick, aInfo.mood||"N/A", aInfo.lastdate);
			vUsr[0].CData = aInfo;
			_.UI.Users.append(vUsr);
		}
	};
	_.UISetUsers = function (aUsers) {
		for (var vI = 0; vI < aUsers.length; vI++) {
			var vInfo = aUsers[vI], vUsr = $("[data-uid='" + vInfo.id + "']", _.UI.Users);
			if (vUsr.length) {
				// Update user information
				var CData = vUsr[0].CData;
				if (!CData) continue;
				var vIsOff = Date.now() / 1e3 - vInfo.lastdate > 16;
				if (CData.nick != vInfo.nick) $(".nick", vUsr).text(vInfo.nick);
				if (CData.avatar != vInfo.avatar) $(".avatar", vUsr).attr("src", vInfo.avatar);
				if (CData.mood != vInfo.mood) $(".mood", vUsr).text(vInfo.mood || "N/A");
				if (CData.lastdate != vInfo.lastdate) $(".status", vUsr).text(vIsOff ? "OFF" : "ON").css("color", vIsOff ? "red" : "lime");
			} else _.UIAddUser(vInfo);
		}
	};
	_.ReceiveLoop = function (aFirstFetch) {
		if (aFirstFetch) _.FirstFetch = 1;
		_.Queue = {};
		var vQID = Date.now();
		_.Queue[vQID] = 1;
		if (_.CID||_.UID) API.PullMessages(_.UID, _.CID, _.FirstFetch, function (aResp) {
			if (!_.Queue[vQID]) return;
			if (false === aResp) {
				_.CID = _.UID = 0;
				return;
			}
			if (!aResp) return _.ReceiveLoop();
			if (aResp.u) {
				_.Users = aResp.u;
				aResp = aResp.c;
				_.UISetUsers(_.Users);
			}
			var vMSE = _.UI.Messages[0].parentNode, vWSAB = IsAtBottomScroll(vMSE);
			for (var vI = 0; vI < aResp.length; vI++) {
				var vItem = aResp[vI],
					vUserInfo = _.GetUserBy(vItem.uid, "id"),
					vAvatar = vUserInfo ? vUserInfo.avatar : "",
					vNick = vUserInfo ? vUserInfo.nick : "[no name]",
					vMsg = _.CreateUIMessage(vItem.id, vItem.uid, vAvatar, vNick, vItem.createdat*1e3, _.Encry.Decrypt(vItem.content));
				if (!vUserInfo && !_.UsersPending[vItem.uid]) {
					_.UsersPending[vItem.uid] = 1;
					(function Retry(aTries, aMsg) {
						if (aTries > 0) API.GetUserInfo(vItem.uid, function (aResp) {
							_.Users.push(aResp);
							$(".msg-nick", aMsg).text(aResp.nick);
							$(".avatar-pic", aMsg).attr("src", aResp.avatar);
						}, function () { Retry(--aTries, aMsg); });
						else delete _.UsersPending[vItem.uid];
					})(3, vMsg);
				}
				if (_.User.id == vItem.uid)
					vMsg.addClass("myself");
				vMsg.attr("data-mid", vItem.id);
				if (!$("[data-mid='"+vItem.id+"']", _.UI.Messages).length) {
					_.UI.Messages.append(vMsg);
					if (_.User.id != vItem.uid && !_.FirstFetch)
						_.PlayAudio();
				}
			}
			if (_.FirstFetch) _.FirstFetch = 0;
			if (aResp.length)
				_.TryScroll(vMSE, vWSAB);
			_.ReceiveLoop();
		});
	};
	_.Send = function (aText) {
		if (_.CID||_.UID) API.SendMessage(_.CID?_.CID:"", _.UID?_.UID:"", _.Encry.Encrypt(aText), function (aResp) {
			if (!aResp) console.log(aResp);
		});
	};
	_.ResolveBuffer = function (aID, aFunc) {
		MT.GetArrayBufferFromURL("?viewFID=" + aID, function (aBufOrig) {
			function SetBlob(aBuffer) {
				var vOURL = URL.createObjectURL(new Blob([aBuffer]));
				aFunc({URL: vOURL});
			}
			_.Encry.DecryptBuffer(aBufOrig, SetBlob);
		}, function () { aFunc({Args: arguments}); });
	};
	_.ProcessMessage = function (aMessage, aHMsgRef) {
		// Escape html tags
		var vIter = 0, vResolveAA = [];
		// vResolveAA is for callbacks; are called if file is protected
		aMessage = MT.XSS(aMessage);
		aMessage = MT.GetBText(aMessage).map(function (aX) {
			var vArgs = ParseCommandLineA(aX.A, 1), vBuf="";
			if (vArgs && aX.B) {
				var vType = vArgs[0].toLowerCase();
				if ("file" == vType) {
					var vParts = vArgs[1].split("|"),
						vID  = MT.XSS(vParts[0]),
						vTyp = MT.XSS(vParts[1]||""),
						vExt = MT.XSS(vParts[2]||""), vPro = 1==vParts[3], vEID = ++vIter + "_" + Date.now();
					if ("image" == vTyp.slice(0, 5)) {
						if (vPro) vResolveAA.push(function () {
							_.ResolveBuffer(vID, function (aObj) {
								$("#" + vEID).attr("src", aObj.URL);
							});
						});
						return "<br><a href=\"?viewFID=" + vID + "\" target=\"_blank\">\
							<img class=\"maxw320 maxh240\" id=\"" + vEID + "\" src=\"" + (!vPro ? ("?viewFID=" + vID) : "") + "\">\
						</a>";
					} else {
						return '<div class="bg-28 p4">' + vExt + '; ' + vTyp + '\
						<div><a class="fg-s fwb" data-dl-decrypt-nfo="'+vID+';'+vExt+';'+vTyp+'" href="#">Download File</a></div>\
						<div><a class="fg-s fwb" href="?viewFID='+vID+'">Raw File</a>\
						</div>';
					}
				} else if ("vf" == vType) {
					var vParts = vArgs[1].split("|"), vID = vParts[0], vEID = ++vIter + "_" + Date.now();
					vResolveAA.push(function () {
						_.ResolveBuffer(vID, function (aObj) {
							$("#" + vEID).attr("src", aObj.URL);
						});
					});
					return "<video id=\""+vEID+"\" class=\"maxw480 maxh320\" controls></video>";
				} else if ("af" == vType) {
					var vParts = vArgs[1].split("|"), vID = vParts[0], vEID = ++vIter + "_" + Date.now();
					vResolveAA.push(function () {
						_.ResolveBuffer(vID, function (aObj) {
							$("#" + vEID).attr("src", aObj.URL);
						});
					});
					return "<audio id=\""+vEID+"\" class=\"maxw480 maxh320\" controls></audio>";
				}
				else if (/ts:?/.test(vType)) {
					var vColor = vType.split(":")[1]>>0, vColors = [
						"", "fg-w", "fg-0", "fg-a", "fg-l", "fg-r", "fg-g", "fg-o", "fg-b"
					];
					vType = vArgs[1];
					for (var vI = 0, vS = {}; vI < vType.length; vI++)
						if ("u" == vType[vI]) vS.tdu=1;
						else if ("s" == vType[vI]) vS.tdlt=1;
						else if ("b" == vType[vI]) vS.fwb=1;
						else if ("i" == vType[vI]) vS.fsi=1;
						else if ("1" == vType[vI]) vS["fs-28"]=1;
						else if ("2" == vType[vI]) vS["fs-24"]=1;
						else if ("3" == vType[vI]) vS["fs-20"]=1;
						else if ("4" == vType[vI]) vS["fs-16"]=1;
					if (vColor in vColors)
						vS[vColors[vColor]] = 1;
					vS = Object.keys(vS);
					if (2 > vArgs.length) vBuf = vType;
					else vBuf = MT.XSS(vArgs.slice(2).join(" "));
					if (vBuf)
						return "<span class=\"" + vS.join(" ") + "\">" + vBuf.replace(/ /g, "&nbsp;") + "</span>";
				}
				return "[invalid embed]";
			}
			return vArgs.join(" ");
		}).join("");
		aHMsgRef.Call = vResolveAA;
		// etc
		return aMessage.replace(/\n/g, "<br>");
	};
	_.CreateUserOnline = function (aUID, aAvatar, aNick, aMood, aDate) {
		var vHnd = $('<div class="user">\
		<div class="d-f">\
			<div class="d-f p4 f-fc">\
				<img class="avatar" src="img/avatar.png" width=40 height=40 alt="Avatar">\
				<div class="f-1 mw4 fs-14">\
					<div class="nof"><b class="nick"></b></div>\
					<div class="mood nof fs-12">N/A</div>\
					<div class="status nof fs-12">status</div>\
				</div>\
			</div>\
		</div>\
	</div>');
		if (!aDate) aDate=0;
		if (aAvatar) $(".avatar", vHnd).attr("src", aAvatar);
		var vIsOff = Date.now() / 1e3 - aDate > 16;
		$(".avatar", vHnd).attr("src", aAvatar);
		$(".nick", vHnd).text(aNick);
		$(".mood", vHnd).text(aMood || "N/A");
		$(".status", vHnd).text(vIsOff ? "OFF" : "ON").css("color", vIsOff ? "red" : "lime");
		return vHnd.attr("data-uid", aUID);
	};
	_.CreateUIMessage = function (aMID, aUID, aAvatar, aNick, aDate, aMessage) {
		var vHnd = $('<div class="d-f p4 m4 f-1">\
		<div class="d-f">\
			<img src="img/avatar.png" class="f-1 avatar-pic">\
		</div>\
		<div class="d-f f-1 f-dc mw4">\
			<div class="d-f f-vc">\
				<div class="d-f f-1">\
					<div class="msg-fill"></div>\
					<b class="msg-nick">this 20 len nickname</b>\
					<span class="msg-date mw8 fg-si fs-11">01-Jan-1970</span>\
				</div>\
			</div>\
			<div class="d-f mn2 msg-holder">\
				<div class="d-f f-1">\
					<div class="msg-fill"></div>\
					<div class="msg me32 p4-8 bw bg-48"></div>\
				</div>\
			</div>\
		</div>\
	</div>'), vMaxNL = 24, vNL = aNick.length > vMaxNL;
		if (aAvatar)
			$(".avatar-pic", vHnd).attr("src", aAvatar);
		$(".msg-nick", vHnd).text(aNick.slice(0, vMaxNL) + (vNL ? "..." : ""));
		$(".msg-date", vHnd).text(MT.Timestamp2String(aDate));
		var vHMsgRef = {Call:null};
		$(".msg", vHnd).html(_.ProcessMessage(aMessage, vHMsgRef));
		if (vHMsgRef = vHMsgRef.Call)
			for (var vI in vHMsgRef) vHMsgRef[vI]();
		vHnd[0].Info = { MID: aMID, UID: aUID };
		return vHnd;
	};
};