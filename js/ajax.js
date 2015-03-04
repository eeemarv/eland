function loadurl(dest) {
	var urlreq = new Request.HTML({url:dest, update: 'output',
		onSuccess: function(html) {
		},
		onFailure: function() {
			jsnotify('Request failed',0);
		}

		});
	urlreq.send();
}

function loadurlto(dest,target){
	var urltoreq = new Request.HTML({url:dest, update: target,
		onSuccess: function(html) {
		},
		onFailure: function() {
			jsnotify('Request failed',0);
		}

		});
	urltoreq.send();

}

function showsmallloader(div){
	document.getElementById(div).innerHTML = "<img src='/gfx/ajax-smallloader.gif' ALT='loading'>";
}

function showloader(div){
	document.getElementById(div).innerHTML = "<img src='/gfx/ajax-loader.gif' ALT='loading'>";
}
