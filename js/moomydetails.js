window.addEvent('domready', function() {
	var MyContactFormDiv = new Fx.Slide('contactformdiv');
	var MyPwFormDiv = new Fx.Slide('pwformdiv');
	var MyOIDFormDiv = new Fx.Slide('oidformdiv');
	var MySubFormDiv = new Fx.Slide('subformdiv');
	var MyUnSubFormDiv = new Fx.Slide('unsubformdiv');
	var MyMLFormDiv = new Fx.Slide('mlformdiv');
	
	MyContactFormDiv.slideIn();
	$('contactformdiv').removeClass('hidden');

	MyPwFormDiv.slideIn();
	$('pwformdiv').removeClass('hidden');

	MyOIDFormDiv.slideIn();
	$('oidformdiv').removeClass('hidden');
	
	MySubFormDiv.slideIn();
	$('subformdiv').removeClass('hidden');
	
	MyUnSubFormDiv.slideIn();
	$('unsubformdiv').removeClass('hidden');
	
	MyMLFormDiv.slideIn();
	$('mlformdiv').removeClass('hidden');

	$('contactform').addEvent('submit', function(e) {
		e.stop();
		var log = $('log_res').empty().addClass('ajax-loading');
		this.set('send', {
			onComplete: function(response) {
			log.removeClass('ajax-loading');
			responseRegExp = /OK/;
			if(response.match(responseRegExp)){
				//jsnotify(response,0);
				log.removeClass('logfail');
				log.addClass('logok');
				$('log_res').set('text', response);
				MyContactFormDiv.slideIn();
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

	$('oidform').addEvent('submit', function(e) {
		e.stop();
		var log = $('log_res').empty().addClass('ajax-loading');
		this.set('send', {
			onComplete: function(response) {
			log.removeClass('ajax-loading');
			responseRegExp = /OK/;
			if(response.match(responseRegExp)){
				log.removeClass('logfail');
				log.addClass('logok');
				$('log_res').set('text', response);
				//jsnotify(response,0);
				MyOIDFormDiv.slideIn();
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

	$('subform').addEvent('submit', function(e) {
		e.stop();
		var log = $('log_res').empty().addClass('ajax-loading');
		this.set('send', {
			onComplete: function(response) {
			log.removeClass('ajax-loading');
			responseRegExp = /OK/;
			if(response.match(responseRegExp)){
				//jsnotify(response,0);
				log.removeClass('logfail');
				log.addClass('logok');
				$('log_res').set('text', response);
				MySubFormDiv.slideIn();
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
	
	$('unsubform').addEvent('submit', function(e) {
		e.stop();
		var log = $('log_res').empty().addClass('ajax-loading');
		this.set('send', {
			onComplete: function(response) {
			log.removeClass('ajax-loading');
			responseRegExp = /OK/;
			if(response.match(responseRegExp)){
				//jsnotify(response,0);
				log.removeClass('logfail');
				log.addClass('logok');
				$('log_res').set('text', response);
				MyUnSubFormDiv.slideIn();
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


	$('pwform').addEvent('submit', function(e) {
		e.stop();
		var log = $('log_res').empty().addClass('ajax-loading');
		this.set('send', {
			onComplete: function(response) {
			log.removeClass('ajax-loading');
			responseRegExp = /OK/;
			if(response.match(responseRegExp)){
				//jsnotify(response,0);
				log.removeClass('logfail');
				log.addClass('logok');
				$('log_res').set('text', response);
				MyPwFormDiv.slideIn();
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

	$('showmsgform').addEvent('click', function(e) {
		e.stop();
		MyMLFormDiv.toggle();
	});

	$('showcontactform').addEvent('click', function(e) {
		e.stop();
		MyContactFormDiv.toggle();
	});

	$('showpwform').addEvent('click', function(e) {
		e.stop();
		MyPwFormDiv.toggle();
	});
	
	$('showsubform').addEvent('click', function(e) {
		e.stop();
		MySubFormDiv.toggle();
	});
	
	$('showunsubform').addEvent('click', function(e) {
		e.stop();
		MyUnSubFormDiv.toggle();
	});


	$('showoidform').addEvent('click', function(e) {
		e.stop();
		MyOIDFormDiv.toggle();
	});

});
