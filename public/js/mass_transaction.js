$(document).ready(function(){

	var system_minlimit = $('table').data('minlimit');

	var has_system_minlimit = system_minlimit === '' ? false : true;

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

	var $fill_in_aid = $('#fill_in_aid');

	$fill_in_aid.submit(function(e){

		var days = Number($('#var_days').val());

		if (days > 1){

			var var_ex_code_in = $('#var_ex_code_in').val().split(',');
			var var_ex_code_out = $('#var_ex_code_out').val().split(',');

			$.map(var_ex_code_in, function(s){
				return $.trim(s);
			});

			$.map(var_ex_code_out, function(s){
				return $.trim(s);
			});

			var path_transactions_sum_in = $fill_in_aid.data('transactions-sum-in');
			var path_transactions_sum_out = $fill_in_aid.data('transactions-sum-out');
			var path_weighted_balances = $fill_in_aid.data('weighted-balances');

			path_transactions_sum_in = path_transactions_sum_in.replace('/365', '/' + days);
			path_transactions_sum_out = path_transactions_sum_out.replace('/365', '/' + days);
			path_weighted_balances = path_weighted_balances.replace('/365', '/' + days);

			$.when(
				$.get(path_weighted_balances),
				$.get(path_transactions_sum_out, params_out),
				$.get(path_transactions_sum_in, params_in)
			).done(function(w_bal, t_out, t_in){

				fill_in(w_bal[0], JSON.parse(t_out[0]), JSON.parse(t_in[0]));
			}).fail(function(){
				alert('Data ophalen mislukt.');
			});
		} else {
			fill_in();
		}

		e.preventDefault();
	});

	function fill_in(w_bal, t_in, t_out){

		var ignore_letscode = $('#to_letscode').val().split(' ');
		ignore_letscode = ignore_letscode[0];

		var fixed = Number($('#fixed').val());

		var var_balance = Number($('#var_balance').val() / 1000);
		var var_base = Number($('#var_base').val());

		var var_trans_in = Number($('#var_trans_in').val() / 1000);
		var var_trans_out = Number($('#var_trans_out').val() / 1000);

		var var_min = $('#var_min').val();
		var var_min_en = var_min === '' ? false : true;
		var_min = Number(var_min);

		var var_max = $('#var_max').val();
		var var_max_en = var_max === '' ? false : true;
		var var_max = Number(var_max);

		var respect_minlimit = $('#respect_minlimit').prop('checked');
		var omit_new = $('#omit_new').prop('checked');
		var omit_leaving = $('#omit_leaving').prop('checked');

		$('table input[type="number"]:visible').each(function() {

			var letscode = $(this).data('letscode');

			if (letscode === ignore_letscode){
				$(this).val('');
				return true;
			};

			if (omit_new && $(this).is('[data-new-account]')){
				$(this).val('');
				return true;
			}

			if (omit_leaving && $(this).is('[data-leaving-account]')){
				$(this).val('');
				return true;
			}

			var balance = Number($(this).data('balance'));

			var minlimit = $(this).data('minlimit');

			var has_minlimit = minlimit === '' ? false : true;

			if (!has_minlimit){
				if (has_system_minlimit){

					minlimit = system_minlimit;
					has_minlimit = true;

					if (respect_minlimit && ((balance - minlimit) <= 0)){

						$(this).val('');
						return true;
					}
				}
			}

			var user_id = $(this).data('user-id');

			var bal = 0;

			if (var_balance !== 0){

				if (typeof w_bal == 'object' && w_bal[user_id] !== undefined){

					bal = Number(w_bal[user_id]);
				} else {
					bal = balance;
				}

				bal = +bal - var_base;
				bal = Math.round(bal * var_balance);
				bal = bal < 0 ? 0 : bal;
			}

			var trans_in = 0;

			if (var_trans_in !== 0){

				if (typeof t_in == 'object' && t_in[user_id] !== undefined){

					trans_in = Number(t_in[user_id]);
					trans_in = Math.round(trans_in * var_trans_in);
					trans_in = trans_in < 0 ? 0 : trans_in;
				}
			}

			var trans_out = 0;

			if (var_trans_out !== 0){

				if (typeof t_out == 'object' && t_out[user_id] !== undefined){

					trans_out = Number(t_out[user_id]);
					trans_out = Math.round(trans_out * var_trans_out);
					trans_out = trans_out < 0 ? 0 : trans_out;
				}
			}

			var amount = +bal + trans_in + trans_out;

			amount = var_min_en && amount < var_min ? var_min : amount;
			amount = var_max_en && amount > var_max ? var_max : amount;

			amount = +amount + fixed;
			amount = amount < 0 ? 0 : amount;

			if (amount === 0){

				$(this).val('');
				return true;
			}

			if (has_minlimit){

				if (amount > (balance - minlimit)){

					$(this).val('');
					return true;
				}
			}

			$(this).val(amount);
		});

		recalc_sum();
	}
});
