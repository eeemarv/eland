function soapstatus(id){
	var target;
	var url = "/interlets/getstatus.php?id="+id;

	try {
	soapstatusobj = window.XMLHttpRequest?new XMLHttpRequest(): new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {
                // browser doesn't support ajax. handle however you want
                alert("Browser ondersteunt geen AJAX");
        }

	soapstatusobj.onreadystatechange = function(evt) {
		if ((soapstatusobj.readyState == 4) && (soapstatusobj.status == 200)) {
			document.getElementById('statusdiv').innerHTML = soapstatusobj.responseText;
		}
	}
        soapstatusobj.open("GET", url);
	soapstatusobj.send(null);
}

