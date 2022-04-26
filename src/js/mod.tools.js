function TTL(aStr){ return aStr.trim().toLowerCase(); }
function IsN(aX) { return /^[-+]?[0-9]+$/.test(aX); }
function IsAtBottomScroll(aElement) {
	if (!aElement) return false;
	var vSH = aElement.scrollHeight, vST = aElement.scrollTop, vOH = aElement.offsetHeight;
	return !vOH || vST == vSH - vOH;
}
// Kind of Obfuscation
var Obfuscation = {
	StrRev: function (aStr) {
		return aStr.split("").reverse().join("");
	},
	GetSingularity: function (aA, aB, aNonBase64Char) {
		var _ = this;
		aA = _.StrRev(btoa(_.StrRev(aA)).split("=").join(""));
		aB = _.StrRev(btoa(_.StrRev(aB)).split("=").join(""));
		var vA = _.PCR(_.StrRev(btoa(_.StrRev(aA + (aNonBase64Char||":") + aB)).split("=").join("")), "_-?=!.,;:");
		return encodeURIComponent(vA);
	}, RevokeSingularity: function (aA) {
		var _ = this;
		aA = decodeURIComponent(aA).replace(/[_\-\?!\.,;:]+/g, "");
		// remove pcr, remove rev1, 
		aA = atob(_.StrRev(aA)).split(":").reverse();
		return [_.StrRev(atob(aA[0])), _.StrRev(atob(aA[1]))];
	}, PCR: function (aString, aChars) {
		return this.PlaceCharsRandomly(aString, aChars);
	}, PlaceCharsRandomly: function (aString, aChars) {
		if (aString.length < 4) return;
		for (var vStr = "", vPos = {}, vI = 0; vI < aChars.length; vI++)
			vPos[(aString.length - 3)*Math.random()>>0] = aChars.charAt(vI);
		for (vI = 0; vI < aString.length; vI++)
			vStr += aString.charAt(vI) + (vPos[vI] || "");
		return vStr;
	}
};
var MT = new function () {
	var _ = this;
	var vMonths = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
	_.GetArrayBufferFromURL = function (aURL, aFunc, aErrFunc) {
		var vX = new XMLHttpRequest;
		vX.open("GET", aURL);
		vX.responseType = "arraybuffer";
		vX.onreadystatechange = function () {
			if (4 === vX.readyState) {
				if (vX.response instanceof ArrayBuffer )
					aFunc && aFunc(vX.response);
				else aErrFunc && aErrFunc();
			}
		};
		vX.onerror = aErrFunc;
		vX.send();
	};
	_.LZ = function (aX) { return aX < 10 ? "0" + aX : aX; };
	_.Timestamp2String = function (aTimestamp) {
		var vDate = new Date(aTimestamp);
		return (
			_.LZ(vDate.getDate()) + "-" + vMonths[vDate.getMonth()] + "-" + vDate.getFullYear() + " " + 
			_.LZ(vDate.getHours()) + ":" + _.LZ(vDate.getMinutes()) + ":" + _.LZ(vDate.getSeconds())
		);
	};
	_.XSS = function (aString) {
		return ("" + aString).replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&apos;");
	};
	_.JQCValue = function (aSel, aParent, aValue) {
		return $(aSel, aParent)[0].checked && aValue || 0;
	};
	_.GetBText = function (aString) {
		for (var vR = [], vInB = 0, vBuf = "", vI = 0; vI < aString.length; vI++) {
			var vCurrChr = aString.charAt(vI), vNextChr = aString.charAt(1 + vI),
				vCurrSlash = '\\' === vCurrChr, vNextSlash = '\\' === vNextChr;
			if (vCurrSlash && vNextSlash)
				vBuf += vCurrChr, vI++;
			else if (vCurrSlash && "[" === vNextChr) vBuf += vNextChr, vI++;
			else if (vCurrSlash && "]" === vNextChr) vBuf += vNextChr, vI++;
			else {
				if (!vInB) {
					if ("[" === vCurrChr) {
						if (vBuf) vR.push({A: vBuf, B: vInB}), vBuf = "";
						vInB = 1;
					}
					else vBuf += vCurrChr;
				} else {
					if ("]" === vCurrChr)
						vR.push({A: vBuf, B: vInB}), vInB = 0, vBuf = "";
					else vBuf += vCurrChr;
				}
			}
		}
		if (vBuf) vR.push({A: vBuf, B: vInB});
		return vR;
	};
};