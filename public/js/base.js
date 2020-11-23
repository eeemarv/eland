jQuery(function() {

	$('[data-toggle=offcanvas]').on('click', function() {
		$('.row-offcanvas').toggleClass('active');
	});

	$('.footable').footable();

	$('form[method="get"]').on('submit', function(){
		$(this).find(':input').each(function() {
			var inp = $(this);
			if (!inp.val()) {
				inp.prop('disabled', true);
			}
		});
	});
});
