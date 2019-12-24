module.exports = function() {
	$('form[method="get"]').submit(function(){
		$(this).find(':input').each(function() {
			var inp = $(this);
			if (!inp.val()) {
				inp.prop('disabled', true);
			}
		});
	});
};
