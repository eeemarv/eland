$(document).ready(function(){

	$('table input[type="number"]').keyup(function(){
		recalc_sum();
	});

	function recalc_sum(){
		var sum = 0;
		$('table input[type="number"]:visible').each(function() {
			sum += Number($(this).val());
		});
		$('input#total').val(sum);
	}

	$('table').footable().bind({
		'footable_filtered' : function(e) {
			recalc_sum();
		}
	});

	$('form[method="post"]').submit(function(event) {
		var selected_users = '';
		$('table > tbody > tr:visible').each(function(){
			selected_users += '.' + $(this).attr('data-user-id');
		});
		$('#selected_users').val(selected_users);
	});

	var $fill_in_aid = $('#fill_in_aid');

	$fill_in_aid.submit(function(e){
		fill_in();
		e.preventDefault();
	});

	function fill_in(w_bal, t_in, t_out){

		var fixed = Number($('#fixed').val());

		$('table input[type="number"]:visible').each(function() {

			if (fixed === 0){
				$(this).val('');
				return;
			}

			$(this).val(fixed);
		});

		recalc_sum();
	}
});
