window.addEvent('domready', function() {
	var req = new Request({method: 'get', url: '/news/approvenews.php',
			onRequest: function() { 
				var log = $('log_res').empty().addClass('ajax-loading');
			},
			onComplete: function(response) { 
				$('log_res').empty().removeClass('ajax-loading');
				//document.getElementById("log_res").innerHTML = response;
				responseRegExp = /OK/;
				if(response.match(responseRegExp)){
					jsnotify(response,0);
				} else {
					jsnotify(response,1);
				}

			}
		});

	$('approver').addEvent('click', function(e) {
		e.stop();
		req.send('id='+nid);
	});
});
