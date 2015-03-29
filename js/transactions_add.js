/*

$('#letsgroup_id').each(function(index, element){
	$(element).data('prefetch', new Bloodhound({
		prefetch: {
			url: 'get_active_users.php?letsgroup_id=' + $(element).val(),
			ttl: 86400,
			filter: function(users){
				return $.map(users, function(user){
					return { 
						value: user.c + ' ' + user.n,
						tokens : [ user.c, user.n ],
						letscode: user.c,
						name: user.n,
						class: (user.s) ? ((user.s == 2) ? 'danger' : 'success') : null,
						postcode : user.p
					};
				});
			}
		},
		datumTokenizer: function(d) { 
			return Bloodhound.tokenizers.whitespace(d.value); 
		},
		queryTokenizer: Bloodhound.tokenizers.whitespace
	}));
	$(element).data('prefetch').initialize();
	$(element).data('prefetch').clearPrefetchCache();
});

*/


var users = new Bloodhound({
	prefetch: {
		url: 'get_active_users.php?letsgroup_id=',
//		ttl: 0,
		filter: function(users){
			return $.map(users, function(user){
				
				return { 
					value: user.c + ' ' + user.n,
					tokens : [ user.c, user.n ],
					letscode: user.c,
					name: user.n,
					class: (user.s) ? ((user.s == 2) ? 'danger' : 'success') : null,
					balance : user.b,
					postcode : user.p
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


$('#letscode_from').typeahead({
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

$('#letscode_to').typeahead({
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

//.clearPrefetchCache();

$('#letsgroup_id').change(function(){
	$('#to_letscode').text('');
});
