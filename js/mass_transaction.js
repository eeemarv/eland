var users = new Bloodhound({
	prefetch: {
		url: $('#to_letscode').data('url'),
		cache: false,
//		ttl: 4320000,	// 50 days
//		thumbprint: $('#to_letscode').data('thumbprint'),
		filter: function(users){
			return $.map(users, function(user){
				return { 
					value: user.c + ' ' + user.n,
					tokens : [ user.c, user.n ],
					letscode: user.c,
					name: user.n,
					class: (user.s) ? ((user.s == 2) ? 'danger' : 'success') : null,
					postcode: user.p
				};
			});
		}
	},
	datumTokenizer: function(d) { 
		return Bloodhound.tokenizers.whitespace(d.value); 
	},
	queryTokenizer: Bloodhound.tokenizers.whitespace
});

users.initialize();

$('#to_letscode').typeahead({
	highLight: true
},
{
	displayKey: function(user){ 
		return user.value;
	},
	source: users.ttAdapter(),
	templates: {
		suggestion: function(data) {
			return '<p class="' + data.class + '"><strong>' + data.letscode +
				'</strong> ' + data.name + '</p>';
		}
	}
});

$('#from_letscode').typeahead({
	highLight: true
},
{
	displayKey: function(user){ 
		return user.value;
	},
	source: users.ttAdapter(),
	templates: {
		suggestion: function(data) {
			return '<p class="' + data.class + '"><strong>' + data.letscode +
				'</strong> ' + data.name + '</p>';
		}
	}
});

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

	var days = $('#percentage_balance_days').val();
	if (days > 1){
		var jqxhr = $.get('./ajax/weighted_balances.php', {'days' :days})
		.done(function(data){
			fill_in(data);
		})
		.fail(function(){
			alert('Data ophalen mislukt.');
		});
	} else {
		fill_in();
	}

	e.preventDefault();
});

function fill_in(data){
	var ignoreLetscode = $('#to_letscode').val().split(' ');
	ignoreLetscode = ignoreLetscode[0];
	var fixed = Number($('#fixed').val());
	var perc = Number($('#percentage_balance').val() / 100);
	var base = Number($('#percentage_balance_base').val());
    $('table input[type="number"]:visible').each(function() {
		var balance = $(this).data('balance');
		var am = (typeof data == 'object') ? data[$(this).data('user-id')] : balance;
		am =  +am - base;
		var amount = Math.round(am * perc);
		amount = (amount < 0) ? 0 : amount;
		amount = amount + fixed;
		var minlimit = $(this).data('minlimit');
		var blockByMinlimit = ((minlimit > (balance - amount)) && $('#respect_minlimit').val()) ? true : false; 
		var ignore = ($(this).attr('data-letscode') == ignoreLetscode) ? true : false;

		if (amount == 0 || ignore || blockByMinlimit){
			$(this).val('');
		} else {
			$(this).val(amount);
		}

    });

    recalc_sum();
}
