$(document).ready(function () {

	function exportTableToCSV($table, filename) {

		var $rows = $table.find('tr:visible'),

			tmpColDelim = String.fromCharCode(11),
			tmpRowDelim = String.fromCharCode(0),

			colDelim = '","',
			rowDelim = '"\r\n"',

			csv = '"' + $rows.map(function (i, row) {
				var $row = $(row),
					$cols = $row.find('th, td');

				return $cols.map(function (j, col) {
					var $col = $(col),
						text = $col.text();

					return text.replace(/"/g, '""').trim();

				}).get().join(tmpColDelim);

			}).get().join(tmpRowDelim)
				.split(tmpRowDelim).join(rowDelim)
				.split(tmpColDelim).join(colDelim) + '"',

			csvData = 'data:application/csv;charset=utf-8,' + encodeURIComponent(csv);

		$(this)
			.attr({
				'download': filename,
				'href': csvData
		});
	}

	$('a.csv').click(function (event) {
		exportTableToCSV.apply(this, [$('table.csv'), 'export.csv']);
	});
});
