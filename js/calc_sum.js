$(document).ready(function() {
	$('table').footable().bind({
		'footable_filtered': function(e) {
			var sum = 0;
			var total = -1;
			$('table tr:visible').each(function() {
				sum += (Number($(this).attr('data-balance'))) ? Number($(this).attr('data-balance')) : 0;
				total++;
			});
			$('span#sum').text(sum);
			$('span#total').text(total);
		}
	});

	$('#q').keyup();
});
