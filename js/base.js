$(document).ready(function() {

	$('[data-toggle=offcanvas]').click(function() {
		$('.row-offcanvas').toggleClass('active');
	});
/*
	var toggleMenu = function(){
		if (swiper.previousIndex == 0)
			swiper.slidePrev();
	},

	menuButton = $('.menu-button').eq(0),

	swiper = new Swiper('.swiper-container', {
		slidesPerView: 'auto',
		initialSlide: 1,
		resistanceRatio: .00000000000001,
		onSlideChangeStart: function(slider) {
			if (slider.activeIndex == 0) {
				menuButton.addClass('cross');
				menuButton.unbind('click', toggleMenu);
			} else {
				menuButton.removeClass('cross');
			}
		},
		onSlideChangeEnd: function(slider) {
			if (slider.activeIndex == 0)
				menuButton.unbind('click', toggleMenu);
			else
				menuButton.bind('click', toggleMenu);
		},
		slideToClickedSlide: true
	});
*/

	$('.footable').footable();

	$('a[data-elas-group-id]').click(function() {

		var ajax_loader = $('img.ajax-loader');
		ajax_loader.css('display', 'inherit');

		var group_id = $(this).data('elas-group-id');
		var elas_group_login = $('body').data('elas-group-login');

		$.get(elas_group_login + '&group_id=' + group_id, function(data){

			ajax_loader.css('display', 'none');

			if (data.error) {
				alert(data.error);
			} else if (data.login_url) {
				window.open(data.login_url);
			} else {
				alert('De pagina kon niet geopend worden.');
			}
		});

	});
});
