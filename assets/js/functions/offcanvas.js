module.exports = function() {

	var $row_offcanvas = $('.row-offcanvas');

	$('[data-toggle=offcanvas]').click(function() {
		$row_offcanvas.toggleClass('active');
	});

	$row_offcanvas.swipe({
		swipeRight: function(ev){
			$row_offcanvas.addClass('active');
		},
		swipeLeft: function(ev){
			$row_offcanvas.removeClass('active');
		},
		treshold: 30,
		preventDefaultEvents: false
	});

	$('div.content-container-overlay').click(function(ev){
		$row_offcanvas.removeClass('active');
	});
};
