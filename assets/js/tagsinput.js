jQuery(function(){
	var $tag_inputs = $('input[type="text"][data-tags-typeahead]');

	const wait_until = async (fu) => {
		return new Promise((resolve, reject) => {
			var count_down = 100;
			const wait = setInterval(function() {
				if (fu()){
					clearInterval(wait);
					resolve();
				} else if (count_down < 1){ // timeout
					clearInterval(wait);
					reject();
				}
				count_down--;
			}, 20);
		});
	};

	$tag_inputs.each(function(){
		var $input = $(this);
		var $form = $tag_inputs.closest('form');
		var $submit = $form.find('button[type="submit"][data-tags-edit-submit]');
		var $reset = $form.find('button[data-tags-edit-reset]');
		var $checkbox_block = $form.find('input[type="checkbox"][data-tags-edit-block]');

		var stored_tag_ids = $input.val();
		console.log('tag ids : ' + stored_tag_ids);
		$input.val('');

		var tag_key_ids = [];
		stored_tag_ids.split(',').forEach((strid) => {
			tag_key_ids[parseInt(strid)] = true;
		});

		var load_tags = [];
		var load_ready = false;

		var data = $input.data('tags-typeahead');

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

		data.fetch.forEach((rec, rec_idx) => {
			var engine = new Bloodhound({
				initialize: false,
				prefetch: {
					url: rec.path,
					cache: true,
					cacheKey: rec.key,
					ttl: rec.ttl_client * 1000,
					thumbprint: rec.thumbprint,
					filter: false
				},
				identify: (tag) => {
					return tag.id;
				},
				sorter: (a, b) => {
					return b.pos > a.pos;
				},
				datumTokenizer: Bloodhound.tokenizers.obj.whitespace('txt'),
				queryTokenizer: Bloodhound.tokenizers.whitespace
			});

			var prom = engine.initialize();

			prom.done(() => {
				var all_tags = engine.index.all();

				all_tags.forEach((tag) => {
					if (!tag_key_ids[tag.id]){
						return;
					}
					load_tags.push(tag);
				});
				if (rec_idx === data.fetch.length - 1){
					load_ready = true;
				}
			});

			typeahead_args.push({
				displayKey: function(tag){
					return tag.id;
				},
				source: engine.ttAdapter(),
				templates: {
					suggestion: (tag) => {
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
			itemTitle: (tag) => {
				return tag.description ?? null;
			},
			tagClass: (tag) => {
				return 'label tag-eland tag-' + tag.id;
			}
		});

		wait_until(() => load_ready).then(() => {
			refresh_tags();
		});

		$checkbox_block.on('click', function(){
			if ($(this).is(':checked')){
				$input.prop('disabled', true);
			} else {
				$input.prop('disabled', false);
				$input.tagsinput('focus');
			}
		});

		$input.on('itemRemoved itemAdded', function(ev) {
			$submit.prop('disabled', false);
			$reset.prop('disabled', false);
			console.log('tags changed.');
		});

		$reset.on('click', function(event){
			refresh_tags();
		});

		function refresh_tags(){
			$input.tagsinput('removeAll');
			load_tags.forEach(function(tag){
				$input.tagsinput('add', tag);
			});
			$submit.prop('disabled', true);
			$reset.prop('disabled', true);

			if (!$input.prop('disabled')){
				$input.tagsinput('focus');
			}
		}
	});

	setTimeout($('div.bootstrap-tagsinput').addClass('form-control'), 100);
});
