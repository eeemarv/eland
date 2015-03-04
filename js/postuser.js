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
                 "&name=" + encodeURIComponent( document.getElementById("name").value ) +
		 "&fullname=" + encodeURIComponent( document.getElementById("fullname").value ) +
		 "&letscode=" + encodeURIComponent( document.getElementById("letscode").value ) +
 		 "&postcode=" + encodeURIComponent( document.getElementById("postcode").value ) +
		 "&birthday=" + encodeURIComponent( document.getElementById("birthday").value ) +
                 "&hobbies=" + encodeURIComponent( document.getElementById("hobbies").value ) +
		 "&comments=" + encodeURIComponent( document.getElementById("comments").value ) +
		 "&login=" + encodeURIComponent( document.getElementById("login").value ) +
		 "&email=" + encodeURIComponent( document.getElementById("email").value ) +
		 "&address=" + encodeURIComponent( document.getElementById("address").value ) +
		 "&telephone=" + encodeURIComponent( document.getElementById("telephone").value ) +
		 "&gsm=" + encodeURIComponent( document.getElementById("gsm").value ) +
		 "&activate=" + encodeURIComponent( document.getElementById("activate").checked ) +
                 "&accountrole=" + encodeURIComponent( document.getElementById("accountrole").value ) +
		 "&status=" + encodeURIComponent( document.getElementById("status").value ) +
		 "&admincomment=" + encodeURIComponent( document.getElementById("admincomment").value ) +
		 "&presharedkey=" + encodeURIComponent( document.getElementById("presharedkey").value ) +
		 "&minlimit=" + encodeURIComponent( document.getElementById("minlimit").value ) +
		 "&maxlimit=" + encodeURIComponent( document.getElementById("maxlimit").value );
   makePOSTRequest('/users/postuser.php', poststr);
}
