$(document).ready(function(){

	var group_minlimit = $('table').data('minlimit');
	var group_maxlimit = $('table').data('maxlimit');

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

	$('#to_letscode').bind('typeahead:selected', function(ev, data) {
		$('#from_letscode').val('');
		$('table input[data-letscode="' + data.letscode + '"]').val('');
		recalc_sum();
	});

	$('#from_letscode').bind('typeahead:selected', function(ev, data) {
		$('#to_letscode').val('');
		$('table input[data-letscode="' + data.letscode + '"]').val('');
		recalc_sum();
	});

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

	$('#fill_in_aid').submit(function(e){

		var days = $('#var_days').val();

		if (days > 1){

			var var_ex_code_in = $('#var_ex_code_in').val().split(',');
			var var_ex_code_out = $('#var_ex_code_out').val().split(',');

			$.map(var_ex_code_in, function(s){
				return $.trim(s);
			});

			$.map(var_ex_code_out, function(s){
				return $.trim(s);
			});

			console.log(var_ex_code_in);
			console.log(var_ex_code_out);

			var session_params = $('body').data('session-params');
			var params = {"days": days};
			var params_out = {"days": days, "ex": var_ex_code_out};
			var params_in = {"days": days, "in": 1, "ex": var_ex_code_in};

			$.extend(params, session_params);
			$.extend(params_out, session_params);
			$.extend(params_in, session_params);

			$.when(
				$.get('./ajax/weighted_balances.php', params),
				$.get('./ajax/transactions_sum.php', params_out),
				$.get('./ajax/transactions_sum.php', params_in)
			).done(function(weighted_balances, trans_sum_out, trans_sum_in){
				fill_in(weighted_balances, trans_sum_out, trans_sum_in);
			})
			.fail(function(){
				alert('Data ophalen mislukt.');
			});
		} else {
			fill_in();
		}

		e.preventDefault();
	});

	function fill_in(weighted_balances, trans_sum_in, trans_sum_out){

		var ignore_letscode = $('#to_letscode').val().split(' ');
		ignore_letscode = ignore_letscode[0];

		if (ignore_letscode === ''){
			ignore_letscode = $('#from_letscode').val().split(' ');
			ignore_letscode = ignore_letscode[0];
		}

		var fixed = Number($('#fixed').val());

		var var_balance = Number($('#var_balance').val() / 1000);
		var var_base = Number($('#var_base').val());

		var var_trans_in = Number($('#var_trans_in').val() / 1000);
		var var_trans_out = Number($('#var_trans_out').val() / 1000);

		var var_min = Number($('#var_min').val());
		var var_max = Number($('#var_max').val());

		var respect_minlimit = $('#respect_minlimit').prop('checked');

		$('table input[type="number"]:visible').each(function() {

			if ($(this).attr('data-letscode') == ignore_letscode){
				$(this).val('');
				return true;
			};

			var balance = Number($(this).data('balance'));

			var minlimit = $(this).data('minlimit');

			var has_minlimit = minlimit !== '' && group_minlimit !== '' ? true : false;

			minlimit = has_minlimit && minlimit === '' ? group_minlimit : minlimit;

			if (has_minlimit){
				var room = balance - minlimit;

				if (respect_minlimit && room <= 0){
					return true;
				} 
			}

			var user_id = $(this).data('user-id');

			var bal = 0;

			if (var_balance !== 0){

				if (typeof weighted_balances == 'object' && typeof weighted_balances[user_id] !== 'undefined'){
					bal = Number(weighted_balances[user_id]);
				} else {
					bal = balance;
				}

				bal = +bal - var_base;
				bal = Math.round(bal * var_balance);
				bal = bal < 0 ? 0 : bal;
			}

			var trans_in = 0;

			if (var_trans_in !== 0){

				if (typeof trans_sum_in == 'object' && typeof trans_sum_in[user_id] !== 'undefined'){
					trans_in = Number(trans_in[user_id]);

					trans_in = Math.round(trans_in * var_trans_in);
					trans_in = trans_in < 0 ? 0 : trans_in;
				}
			}

			var trans_out = 0;

			if (var_trans_out !== 0){

				if (typeof trans_sum_out == 'object' && typeof trans_sum_out[user_id] !== 'undefined'){
					trans_out = Number(trans_out[user_id]);

					trans_out = Math.round(trans_out * var_trans_out);
					trans_out = trans_out < 0 ? 0 : trans_out;
				}
			}

			var amount = +bal + trans_in + trans_out;

			amount = amount < var_min ? var_min : amount;
			amount = amount > var_max ? var_max : amount;

			amount = amount + fixed;
			amount = amount < 0 ? 0 : amount;

			if (has_minlimit && amount !== 0){
				if (amount > room){
					$(this).val('');
					return true;
				}
			}

			$(this).val(amount);

		});

		recalc_sum();
	}
});
