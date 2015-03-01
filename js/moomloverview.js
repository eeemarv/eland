window.addEvent('domready', function() {
	var MyMLFormDiv = new Fx.Slide('mlformdiv');
	
	MyMLFormDiv.slideIn();
	$('mlformdiv').removeClass('hidden');
	
	$('mlform').addEvent('submit', function(e) {
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
				MyMLFormDiv.slideIn();
			} else {
				log.removeClass('logok');
				log.addClass('logfail');
				$('log_res').set('text', response);
				//jsnotify(response,1);
			}
		}});
		//Send the form.
		this.send();
	});

	$('showmlform').addEvent('click', function(e) {
		e.stop();
		MyMLFormDiv.toggle();
	});

});
