window.addEvent('domready', function() {
	var MyMsgFormDiv = new Fx.Slide('msgformdiv');

	//MyMLFormDiv.slideIn();
	//$('mlformdiv').removeClass('hidden');

	$('msgform').addEvent('submit', function(e) {
		e.stop();
		var log = $('log_res').empty().addClass('ajax-loading');
		log.removeClass('logfail');
		this.set('send', {
			onComplete: function(response) {
			log.removeClass('ajax-loading');
			responseRegExp = /OK/;
			if(response.match(responseRegExp)){
				log.removeClass('logfail');
				log.addClass('logok');
				$('log_res').set('text', response);
				//jsnotify(response,0);
				//MyMsgFormDiv.slideIn();
			} else {
				//jsnotify(response,1);
				log.removeClass('logok');
				log.addClass('logfail');
				$('log_res').set('text', response);
			}
		}});
		//Send the form.
		this.send();
	});

});
