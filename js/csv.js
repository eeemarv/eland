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

					return text.replace(/"/g, '""');

				}).get().join(tmpColDelim);

			}).get().join(tmpRowDelim)
				.split(tmpRowDelim).join(rowDelim)
				.split(tmpColDelim).join(colDelim) + '"',

			csvData = 'data:application/csv;charset=utf-8,' + encodeURIComponent(csv);

		$(this)
			.attr({
			'download': filename,
				'href': csvData,
				'target': '_blank'
		});
	}

	function exportAddressesToCSV($table, filename) { 

		var fullnameIndex = $table.find('tr th[data-content=fullname]').index();
		var adrIndex = $table.find('tr th[data-content=adr]').index();

		var $rows = $table.find('tr:visible'),

			tmpColDelim = String.fromCharCode(11),
			tmpRowDelim = String.fromCharCode(0),

			colDelim = '","',
			rowDelim = '"\r\n"';

		var csv = '"' + $rows.map(function (i, row) {
				if (i == 0)
				{
					return null;
				}

				var $row = $(row),
					fullname = $row.find('td:eq(' + fullnameIndex + ') a').text(),
					adr = $row.find('td:eq(' + adrIndex + ')').text().split(',');
				adr.unshift(fullname);

				return adr.map(function (str, i) {
					return str.trim().replace(/"/g, '""');

				}).join(tmpColDelim);

			}).get().join(tmpRowDelim)
				.split(tmpRowDelim).join(rowDelim)
				.split(tmpColDelim).join(colDelim) + '"',

			csvData = 'data:application/csv;charset=utf-8,' + encodeURIComponent(csv);

		$(this)
			.attr({
			'download': filename,
				'href': csvData,
				'target': '_blank'
		});
	}


	$('a.csv').click(function (event) {
		exportTableToCSV.apply(this, [$('table.csv'), 'export.csv']);
	});

	$('a.csv-adr').click(function (event) {
		exportAddressesToCSV.apply(this, [$('table.csv-adr'), 'export_adr.csv']);
	});
});
