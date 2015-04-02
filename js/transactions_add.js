

var users = new Bloodhound({
	prefetch: {
		url: 'get_active_users.php?letsgroup_id=',
		ttl: 4320000,	// 50 days
		thumbprint: 
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
				'</strong> ' + data.name + '<span text-align="left"><i>' + data.postcode + '</i></span></p>';
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
