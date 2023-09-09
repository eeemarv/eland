jQuery(function(){
	var $data_inputs = $('input[type="text"][data-tags-typeahead]');

	$data_inputs.each(function(){
		var $input = $(this);
		var store_val = $input.val();
		console.log(store_val);
		$input.val('');

		var data = $(this).data('tags-typeahead');
		if (!data.hasOwnProperty('fetch')){
			console.log('no typeahead fetch ...');
			return;
		}
		var typeahead_args = [];
		typeahead_args.push({
			highlight: true,
			classNames: {
				wrapper: 'tw-typeahead'
			}
		});
		data.fetch.forEach(function(rec){
			var engine = new Bloodhound({
				prefetch: {
					url: rec.path,
					cache: true,
					cacheKey: rec.key,
					ttl: rec.ttl_client * 1000,
					thumbprint: rec.thumbprint,
					filter: false
				},
				datumTokenizer: Bloodhound.tokenizers.obj.whitespace('txt'),
				queryTokenizer: Bloodhound.tokenizers.whitespace
			});
			typeahead_args.push({
				displayKey: function(tag){
					return tag.id;
				},
				source: engine.ttAdapter(),
				templates: {
					suggestion: function(tag){
						var tpl = '<span';
						if (tag.description !== null){
							tpl += ' title="';
							tpl += tag.description;
							tpl += '"';
						}
						tpl += '>';
						tpl += '<span class="label tag-eland tag-';
						tpl += tag.id;
						tpl += '">';
						tpl += tag.txt;
						tpl += '</span>';
						tpl += '</span>';
						return tpl;
					}
				}
			});
		});

		$input.tagsinput({
			typeaheadjs: typeahead_args,
			itemValue: 'id',
			itemText: 'txt',
			maxTags: 5,
			maxChars: 10,
			itemTitle: function(tag){
				return tag.description ?? null;
			},
			tagClass: function(tag){
				return 'label tag-eland tag-' + tag.id;
			}
		});
	});

	setTimeout($('div.bootstrap-tagsinput').addClass('form-control'), 100);
});
