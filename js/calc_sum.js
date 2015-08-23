$(function() {
	$('table').footable().bind({
		'footable_filtered': function(e) {
			var sum = 0;
			$('table tr:visible').each(function() {
				sum += (Number($(this).attr('data-balance'))) ? Number($(this).attr('data-balance')) : 0;
			});
			$('span#sum').text(sum);
		}
	});
});
