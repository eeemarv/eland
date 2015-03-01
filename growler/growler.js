/*
---

name: Growler

description: a simple, event-based, Growl-style notification system for mooTools

authors: Stephane P. Pericat (@sppericat)

license: MIT-style license.

requires: [Core]

provides : Growler

...
*/

/* Version */
var Growler = {
	author: "Stéphane P. Péricat",
	license: "MIT",
	version: '1.0'
}

/*
 * Growler.init() is used to instantiate a new Growler Object and set the contaienr div in the window
 */

Growler.init = function (options) {
	if(Browser.ie) {
		return new Growler.Classic(options);
	} else {
		return new Growler.Modern(options);
	}
};

/*
 * Growler.createWindow() returns a new growl window with the appropriate css style
 */

Growler.createWindow = function(css) {
	return new Element('div', {
		'class': css,
		'styles': {
			'opacity': 0
		}
	});
}

/* 
 * Growler.Base, the mother of all growling
 */

Growler.Base = new Class({
	Implements: [Options, Events],
	
	options: {
		timeOut: 5000,
		containerCss: 'GrowlContainer'
	},
	container: null,
	
	initialize: function(options) {
		if(options)
			this.setOptions(options);
		
		this.container = new Element('div', {
			'class': this.options.containerCss
		});
		$(document.body).grab(this.container);
	},
	
	listen: function(style, msg, el, evt) {
		if($(el)) {
			$(el).addEvent(evt, function() {
				this.spawn(msg, style);
			}.bind(this));
		} else {
			throw 'invalid element id';
		}
	},
	
	spawn: function(msg, style) {
		var win = Growler.createWindow(style);
		win.set('text', msg);
		this.container.grab(win);
		win.morph({opacity: 1});
		(function() {
			win.morph({opacity: 0});
			(function() {
				win.dispose();
			}).delay(500);
		}).delay(this.options.timeOut);
	}
});

/* 
 * Growler.Classic uses a png/gif file as background image, thus making size + style quite static
 * This is a fallback Growler that will only be instantiated if the client is using IE
 */

Growler.Classic = new Class({
	Extends: Growler.Base,
	
	options: {
		cssStyle: 'GrowlerClassic'
	},
	
	listen: function(el, evt, msg) {
		this.parent(this.options.cssStyle, msg, el, evt);
	},
	
	notify: function(msg) {
		this.spawn(msg, this.options.cssStyle);
	}
});

/* 
 * Growler.Modern is a fully customizable html5 / css3 implementation of Growl.
 * Compatibility: Safari 5, Firefox 3.*, Chrome 
 */

Growler.Modern = new Class({
	Extends: Growler.Base,
	
	options: {
		cssStyle: 'GrowlerModern'
	},
	
	listen: function(el, evt, msg) {
		this.parent(this.options.cssStyle, msg, el, evt);
	},
	
	notify: function(msg) {
		this.spawn(msg, this.options.cssStyle);
	}
});
