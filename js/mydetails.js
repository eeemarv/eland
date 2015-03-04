function loadcontact(url){
        try {
        contactobj = window.XMLHttpRequest?new XMLHttpRequest(): new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {
                // browser doesn't support ajax. handle however you want
                alert("Browser ondersteunt geen AJAX");
        }

        contactobj.onreadystatechange = function() {
                if ((contactobj.readyState == 4) && (contactobj.status == 200)) {
                        document.getElementById('contactdiv').innerHTML = contactobj.responseText;
                }
        }
        contactobj.open("GET", url);
        contactobj.send(null);
}

function loaduser(url){
        try {
	userobj = window.XMLHttpRequest?new XMLHttpRequest(): new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {
                // browser doesn't support ajax. handle however you want
                alert("Browser ondersteunt geen AJAX");
        }

	userobj.onreadystatechange = function() {
                if ((userobj.readyState == 4) && (userobj.status == 200)) {
                        document.getElementById('userdiv').innerHTML = userobj.responseText;
                }
        }
        userobj.open("GET", url);
        userobj.send(null);
}

function loadoid(url){
        try {
        oidobj = window.XMLHttpRequest?new XMLHttpRequest(): new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {
                // browser doesn't support ajax. handle however you want
                alert("Browser ondersteunt geen AJAX");
        }

        oidobj.onreadystatechange = function() {
                if ((oidobj.readyState == 4) && (oidobj.status == 200)) {
                        document.getElementById('oiddiv').innerHTML = oidobj.responseText;
                }
        }
        oidobj.open("GET", url);
        oidobj.send(null);
}

function loadsubs(url){
        try {
        subsobj = window.XMLHttpRequest?new XMLHttpRequest(): new ActiveXObject("Microsoft.XMLHTTP");
        } catch (e) {
                // browser doesn't support ajax. handle however you want
                alert("Browser ondersteunt geen AJAX");
        }

        subsobj.onreadystatechange = function() {
                if ((subsobj.readyState == 4) && (subsobj.status == 200)) {
                        document.getElementById('subsdiv').innerHTML = subsobj.responseText;
                }
        }
        subsobj.open("GET", url);
        subsobj.send(null);
}
