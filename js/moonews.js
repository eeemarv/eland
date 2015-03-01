window.addEvent('domready', function() {
	$('log_res').empty().addClass('ajax-loading');

	$('output').fade('out');

	var req = new Request.HTML({url:'rendernews.php',  update: 'output',
		onSuccess: function(html) {
			$('log_res').removeClass('ajax-loading');
			$('output').fade('in');
		},
		//Our request will most likely succeed, but just in case, we'll add an
		//onFailure method which will let the user know what happened.
		onFailure: function() {
			$('log_res').removeClass('ajax-loading');
			$('output').set('text', 'The request failed.');
			$('output').fade('in');
		}
	});
	req.send();
});
