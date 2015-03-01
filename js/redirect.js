function doredirect(dest) {
	try {
	xmlhttp = window.XMLHttpRequest?new XMLHttpRequest(): new ActiveXObject("Microsoft.XMLHTTP");
	} catch (e) {
		// browser doesn't support ajax. handle however you want
		alert("Browser ondersteunt geen AJAX");
	}

	// the xmlhttp object triggers an event everytime the status changes
	// triggered() function handles the events
	xmlhttp.onreadystatechange = triggerme;
	// open takes in the HTTP method and url.
	xmlhttp.open("GET", dest);
	// send the request. if this is a POST request we would have
	// sent post variables: send("name=aleem&gender=male)
	// Moz is fine with just send(); but
	// IE expects a value here, hence we do send(null);
	xmlhttp.send(null);
}

function triggerme() {
	// if the readyState code is 4 (Completed)
	// and http status is 200 (OK) we go ahead and get the responseText
	// other readyState codes:
	// 0=Uninitialised 1=Loading 2=Loaded 3=Interactive
	if ((xmlhttp.readyState == 4) && (xmlhttp.status == 200)) {
		// xmlhttp.responseText object contains the response.
		//document.getElementById("urlfield").value = xmlhttp.responseText;
	        url = xmlhttp.responseText;
         	resultRegExp = /elasv2/;
         	if(url.match(resultRegExp)){
			window.location = url;
		} else {
			alert('Er kon geen verbinding met de andere LETS groep gemaakt worden, probeer het later opnieuw');
			self.close();
		}
	}
}
