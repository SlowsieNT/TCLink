/* License: Unlicense, or 0-BSD
    Source: github.com/SlowsieNT
   This is free and unencumbered software released into the public domain.
*/
function ParseCommandLineA(aCommandString, aNoTrimCheck) {
	for (var vI = 0, vL = aCommandString.length, vInQuote, vStr="", vArgs=[]; vI < vL; vI++) {
		var vCC = aCommandString.charAt(vI), vNC = aCommandString.charAt(1 + vI),
			vNQ = '"' === vNC, vCQ = '"' === vCC, vNS = '\\' === vNC, vCS = '\\' === vCC;
		if (vInQuote) {
			if (vCS && vNS || vCS && vNQ) {
				vStr += vNC;
				vI++;
			} else if (vCQ) {
				vArgs.push(vStr);
				vStr = "";
				vInQuote = !1;
			} else vStr += vCC;
		} else {
			if (vCS && vNQ) { /* <- must be first */
				vStr += vNC;
				vI++;
			} else if (" " === vCC) { /* <- must be second */
				if (aNoTrimCheck)
					vArgs.push(vStr);
				else if (vStr.trim())
					vArgs.push(vStr);
				vStr = "";
			} else if (vCQ) {
				vInQuote = !0;
			} else vStr += vCC;
		}
	}
	if (aNoTrimCheck)
		vArgs.push(vStr);
	else if (vStr.trim())
		vArgs.push(vStr.trim());
	return vArgs;
}