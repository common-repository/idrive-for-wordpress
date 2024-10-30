/**
 * Author : Diganta Dutta
 * Company: Pro-Softnet Corp.
 */

IDriveWpPluginMisc = {};

IDriveWpPluginMisc.getWinSize = function() {
	var myWidth = 0, myHeight = 0;

	if (typeof (window.innerWidth) == 'number') {
		// Non-IE
		myWidth = window.innerWidth;
		myHeight = window.innerHeight;
	} else if (document.documentElement
			&& (document.documentElement.clientWidth || document.documentElement.clientHeight)) {
		// IE 6+ in 'standards compliant mode'
		myWidth = document.documentElement.clientWidth;
		myHeight = document.documentElement.clientHeight;
	} else if (document.body
			&& (document.body.clientWidth || document.body.clientHeight)) {
		// IE 4 compatible
		myWidth = document.body.clientWidth;
		myHeight = document.body.clientHeight;
	}

	return {
		x : myWidth,
		y : myHeight
	};
};

IDriveWpPluginMisc.getScrollOffset = function() {
	var scrOfX = 0, scrOfY = 0;
	if (typeof (window.pageYOffset) == 'number') {
		// Netscape compliant
		scrOfY = window.pageYOffset;
		scrOfX = window.pageXOffset;
	} else if (document.body
			&& (document.body.scrollLeft || document.body.scrollTop)) {
		// DOM compliant
		scrOfY = document.body.scrollTop;
		scrOfX = document.body.scrollLeft;
	} else if (document.documentElement
			&& (document.documentElement.scrollLeft || document.documentElement.scrollTop)) {
		// IE6 standards compliant mode
		scrOfY = document.documentElement.scrollTop;
		scrOfX = document.documentElement.scrollLeft;
	}
	return {
		x : scrOfX,
		y : scrOfY
	};
};

IDriveWpPluginMisc.openshadow = function () {

	document.body.style.overflow = 'hidden';
	
	var winSize = IDriveWpPluginMisc.getWinSize();
	var scrollOffset = IDriveWpPluginMisc.getScrollOffset();

	var shadow = document.getElementById("shadow");

	shadow.style.height = winSize.y + "px";
	shadow.style.width = winSize.x + "px";
	shadow.style.left = scrollOffset.x + "px";
	shadow.style.top = scrollOffset.y + "px";

	shadow.style.display = "block";
};

IDriveWpPluginMisc.closeshadow = function() {
	document.getElementById('shadow').style.display = "none";
	document.body.style.overflow = '';
};