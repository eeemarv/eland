var map_names = new Bloodhound({
	prefetch: {
		url : './ajax/doc_map_names.php',
		cache : false
	},
	datumTokenizer: Bloodhound.tokenizers.whitespace,
	queryTokenizer: Bloodhound.tokenizers.whitespace
});

map_names.initialize();

$('#map_name').typeahead({
	highLight: true
},
{
//	displayKey: 'map_name',
	source: map_names.ttAdapter(),
	name: 'map_names'
});
