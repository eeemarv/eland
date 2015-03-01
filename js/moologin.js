window.addEvent('domready', function() {
	var MyPWFormDiv = new Fx.Slide('formdiv');
	var MyPWResetFormDiv = new Fx.Slide('pwresetdiv');
	//var MyGuestLoginDiv = new Fx.Slide('guestlogindiv');

	//MyPWFormDiv.slideIn();
	//$('contactformdiv').removeClass('hidden');

	MyPWResetFormDiv.slideIn();
	$('pwresetdiv').removeClass('hidden');

	//MyGuestLoginDiv.slideIn();
	//$('guestloginform').removeClass('hidden');

	$('loginform').addEvent('submit', function(e) {
		//Prevents the default submit event from loading a new page.
		e.stop();
		//Empty the log and show the spinning indicator.
		var log = $('log_res').empty().addClass('ajax-loading');
		log.removeClass('logfail');
		//Set the options of the form's Request handler. 
		//("this" refers to the $('myForm') element).
		this.set('send', {onComplete: function(response) {
			log.removeClass('ajax-loading');
			responseRegExp = /OK/;
			if(response.match(responseRegExp)){
				//jsnotify(response,0);
				log.removeClass('logfail');
				log.addClass('logok');
				$('log_res').set('text', response);
				setTimeout("self.location=redirecturl",200);
			} else {
				//jsnotify(response,0);
				log.removeClass('logok');
				log.addClass('logfail');
				$('log_res').set('text', response);
			}
		}});
		//Send the form.
		this.send();
	});

	$('pwresetform').addEvent('submit', function(e) {
		e.stop();
		var log = $('log_res').empty().addClass('ajax-loading');
		this.set('send', {onComplete: function(response) {
			log.removeClass('ajax-loading');
			responseRegExp = /OK/;
			if(response.match(responseRegExp)){
				jsnotify(response,0);
				MyPWFormDiv.toggle();
				MyPWResetFormDiv.toggle();
			} else {
				jsnotify(response,0);
			}
		}});
		//Send the form.
		this.send();
	});

	$('openidbox').addEvent('submit', function(e) {
			jsnotify('OpenID login',0);
	});


	$('showlostpasswordform').addEvent('click', function(e) {
		e.stop();
		MyPWFormDiv.toggle();
		MyPWResetFormDiv.toggle();
	});

	//$('showguestloginform').addEvent('click', function(e) {
     //           e.stop();
		//MyPWFormDiv.toggle();
		//MyGuestLoginDiv.toggle();
        //});

});
