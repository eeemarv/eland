$(function() {
	$('table').footable().bind({
		'footable_filtered': function(e) {
			var total = -1;
			$('table#msgs tr:visible').each(function() {
				total++;
			});
			$('span#total').text(total);
		}
	});
});
