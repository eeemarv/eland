jQuery(function(){

	$('table input[type="number"]').on('keyup', function(){
		recalc_sum();
	});

	function recalc_sum(){
		var sum = 0;
		var transaction_count = 0;

		$('table input[type="number"]:visible').each(function() {
			var am = Number($(this).val());
			sum += am;
			if (am){
				transaction_count++;
			}
		});

		$('input#total').val(sum);
		$('span#transaction_count').text(transaction_count);
	}

	$('table').footable().bind({
		'footable_filtered' : function(e) {
			recalc_sum();
		}
	});

	$('form[method="post"]').on('submit', function(event) {
		var selected_users = '';
		$('table > tbody > tr:visible').each(function(){
			selected_users += '.' + $(this).attr('data-user-id');
		});
		$('#selected_users').val(selected_users);
	});

	var $fill_in_aid = $('#fill_in_aid');

	$fill_in_aid.on('submit', function(e){
		fill_in();
		e.preventDefault();
	});

	function fill_in(w_bal, t_in, t_out){
		var fixed = Number($('#fixed').val());
		var omit_new = $('#omit_new').prop('checked');
		var omit_leaving = $('#omit_leaving').prop('checked');

		$('table input[type="number"]:visible').each(function() {
			if (fixed === 0){
				$(this).val('');
				return;
			}

			if (omit_new && $(this).is('[data-new-account]')){
				$(this).val('');
				return true;
			}

			if (omit_leaving && $(this).is('[data-leaving-account]')){
				$(this).val('');
				return true;
			}

			$(this).val(fixed);
		});

		recalc_sum();
	}

	recalc_sum();
});
