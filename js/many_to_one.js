var users = new Bloodhound({
	prefetch: {
		url: 'get_active_users.php?letsgroup_id=' + $('#to_letscode').attr('data-letsgroup-id'),
		ttl: 4320000,	// 50 days
		thumbprint: $(this).attr('data-thumbprint'),
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

$('table input[type="number"]').keyup(function(){
	recalc_sum();
});

function recalc_sum(){
    var sum = 0;
    $('table input[type="number"]').each(function() {
		sum += Number($(this).val());
    });
	$('input#total').val(sum);
}

$('#to_letscode').bind('typeahead:selected', function(ev, data) {
	$('table input[data-letscode="' + data.letscode + '"]').val(0);
	recalc_sum();
});

$('#fill_in_aid').submit(function(e){
	var ignore_letscode = $('#to_letscode').val().split(' ');
	ignore_letscode = ignore_letscode[0];
	var fixed = $('#fixed').val();

    $('table input[type="number"]').each(function() {

		var amount = fixed;

		if ($(this).attr('data-letscode') != ignore_letscode)
		{
			$(this).val(amount);
		}
    });
    recalc_sum();
	e.preventDefault();
});


/*
function recalc_table_sum(el_input){
	var tbody = el_input.parentNode.parentNode.parentNode;
	var inputs = tbody.getElementsByTagName('input');
	var sum = 0;
	for (var i = 0; i < inputs.length; i++){
		sum += (inputs[i].value) ? parseInt(inputs[i].value) : 0;
	}
	document.getElementById('table_total').innerHTML = '<strong>' + sum + '</strong>';
}
*/
