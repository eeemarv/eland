var map_names = new Bloodhound({
	prefetch: {
		url : './ajax/doc_map_names.php',
		cache : false
	},
	datumTokenizer: Bloodhound.tokenizers.whitespace,
	queryTokenizer: Bloodhound.tokenizers.whitespace
});

$('#map_name').typeahead({
	highLight: true
},
{
	source: map_names,
	name: 'map_names'
});
