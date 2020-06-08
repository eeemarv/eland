export default function() {
	$('form').submit(function(){
		$(this).find(':input[data-provide="datepicker"]').each(function() {
			var $inp = $(this);
			var date = $inp.datepicker('getDate');
			var month = date.getMonth() + 1;
			var day = date.getDate();
			var year = date.getFullYear();
			var formatted = year + '-' + month + '-' + day + ' 12:00:00';
			$inp.val(formatted);
		});
	});
};
