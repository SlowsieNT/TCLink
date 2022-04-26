/* License: Unlicense, or 0-BSD
    Source: github.com/SlowsieNT
   This is free and unencumbered software released into the public domain.
*/
$(function(){
	var vAttr = "data-maxrows";
	$("textarea["+vAttr+"]").each(function (aI, aV) {
		aV.MinRows = aV.rows;
		aV.DefaultMaxRows = $(aV).attr(vAttr) >> 0||1;
		$(aV).on("input", function () {
			var vMaxRows = $(aV).attr(vAttr) >> 0||aV.DefaultMaxRows,
				vRows = aV.value.split("\n").length;
			if (vRows <= vMaxRows)
				aV.rows = vRows < aV.MinRows ? aV.MinRows : vRows;
		});
	});
});