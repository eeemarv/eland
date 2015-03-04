function loaduser(element,target){
	var target;
	var letscode = document.getElementById(element).value
	var letsgroup = document.getElementById('letsgroup').value

	if(element == 'letscode_from') {
		var url = "/transactions/getuserinfo.php?letscode="+letscode;
	} else {
		var url = "/transactions/getuserinfo.php?letscode="+letscode +"&letsgroup=" +letsgroup;
	}

	try {
	userobj = window.XMLHttpRequest?new XMLHttpRequest(): new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {
                // browser doesn't support ajax. handle however you want
                alert("Browser ondersteunt geen AJAX");
        }

	userobj.onreadystatechange = function(evt) {
		if ((userobj.readyState == 4) && (userobj.status == 200)) {
			document.getElementById(target).innerHTML = userobj.responseText;
		}
	}
        userobj.open("GET", url);
	userobj.send(null);
}
