function makePOSTRequest(url, parameters) {
   http_request = false;
   try {
        http_request = window.XMLHttpRequest?new XMLHttpRequest(): new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {
                // browser doesn't support ajax. handle however you want
                alert("Browser ondersteunt geen AJAX");
        }

      if (http_request.overrideMimeType) {
      	// set type accordingly to anticipated content type
         //http_request.overrideMimeType('text/xml');
         http_request.overrideMimeType('text/html');
      }

   http_request.onreadystatechange = alertContents;
   http_request.open('POST', url, true);
   http_request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
   http_request.setRequestHeader("Content-length", parameters.length);
   http_request.setRequestHeader("Connection", "close");
   http_request.send(parameters);
}

function alertContents() {
   if (http_request.readyState == 4) {
      if (http_request.status == 200) {
         result = http_request.responseText;
         document.getElementById('serveroutput').innerHTML = result;
	 resultRegExp = /OK/;
	 if(result.match(resultRegExp)){
		document.getElementById('zend').disabled = true;
	 }
      } else {
         alert('Er was een fout bij het verwerken, probeer opnieuw');
      }
   }
}

function get(obj) {
   var poststr = "mode=" + encodeURIComponent( document.getElementById("mode").value ) +
		 "&id=" + encodeURIComponent( document.getElementById("id").value ) +
                 "&groupname=" + encodeURIComponent( document.getElementById("groupname").value ) +
		 "&shortname=" + encodeURIComponent( document.getElementById("shortname").value ) +
		 "&prefix=" + encodeURIComponent( document.getElementById("prefix").value ) +
		 "&apimethod=" + encodeURIComponent( document.getElementById("apimethod").value ) +
 		 "&remoteapikey=" + encodeURIComponent( document.getElementById("remoteapikey").value ) +
		 "&localletscode=" + encodeURIComponent( document.getElementById("localletscode").value ) +
                 "&myremoteletscode=" + encodeURIComponent( document.getElementById("myremoteletscode").value ) +
		 "&url=" + encodeURIComponent( document.getElementById("url").value ) +
		 "&elassoapurl=" + encodeURIComponent( document.getElementById("elassoapurl").value ) +
		 "&presharedkey=" + encodeURIComponent( document.getElementById("presharedkey").value ) ;
   makePOSTRequest('/interlets/postgroup.php', poststr);
}
