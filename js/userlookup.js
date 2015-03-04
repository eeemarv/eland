function lookupuser(){
	var name = document.getElementById('name').value;
	var letsgroup = document.getElementById('letsgroup').value;

	var url = "/transactions/getuserbyname.php?name="+name+"&letsgroup=" +letsgroup;

	try {
	userobj = window.XMLHttpRequest?new XMLHttpRequest(): new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {
                // browser doesn't support ajax. handle however you want
                alert("Browser ondersteunt geen AJAX");
        }

	userobj.onreadystatechange = function(evt) {
		if ((userobj.readyState == 4) && (userobj.status == 200)) {
			document.getElementById('letscode').value = userobj.responseText;
			load();
		}
	}
        userobj.open("GET", url);
	userobj.send(null);
}

function load(){
	var letscode = document.getElementById('letscode').value
	var letsgroup = document.getElementById('letsgroup').value

	var url = "/transactions/getuserinfo.php?letscode="+letscode +"&letsgroup=" +letsgroup;

	try {
	usernameobj = window.XMLHttpRequest?new XMLHttpRequest(): new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {
                // browser doesn't support ajax. handle however you want
                alert("Browser ondersteunt geen AJAX");
        }

	usernameobj.onreadystatechange = function(evt) {
		if ((usernameobj.readyState == 4) && (usernameobj.status == 200)) {
			document.getElementById('fullname').value = usernameobj.responseText;
		}
	}
        usernameobj.open("GET", url);
	usernameobj.send(null);
}
