$("#letsgroup_id").find('option').each(function(){
	$(this).data('users', new Bloodhound({
		prefetch: {
			url: 'get_active_users.php?letsgroup_id=' + $(this).val(),
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
	}));
});

var this_letsgroup_users = $('#letsgroup_id option[data-this-letsgroup="1"]').data('users');
this_letsgroup_users.initialize();

$('#letscode_from').typeahead({
	highLight: true
},
{
	displayKey: function(user){ 
		return user.value;
	},
	source: this_letsgroup_users.ttAdapter(),
	templates: {
		suggestion: function(data) {
			return '<p class="' + data.class + '"><strong>' + data.letscode +
				'</strong> ' + data.name + '</p>';
		}
	}
});

var selected_to_users = $('#letsgroup_id option:selected').data('users');
selected_to_users.initialize();

$('#letscode_to').typeahead({
	highLight: true
},
{
	displayKey: function(user){ 
		return user.value;
	},
	source: selected_to_users.ttAdapter(),
	templates: {
		suggestion: function(data) {
			return '<p class="' + data.class + '"><strong>' + data.letscode +
				'</strong> ' + data.name + '</p>';
		}
	}
}); 

$('#letsgroup_id').change(function(){
	$('#letscode_to').typeahead('val', '');
	selected_to_users = $('#letsgroup_id option:selected').data('users');
	selected_to_users.initialize();
	$('#letscode_to').data('ttTypeahead').dropdown.datasets[0].source = selected_to_users.ttAdapter(); 
});
