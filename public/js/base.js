$(document).ready(function() {

	$('[data-toggle=offcanvas]').click(function() {
		$('.row-offcanvas').toggleClass('active');
	});

	$('.footable').footable();

	$('form[method="get"]').submit(function(){
		$(this).find(':input').each(function() {
			var inp = $(this);
			if (!inp.val()) {
				inp.prop('disabled', true);
			}
		});
	});
});
