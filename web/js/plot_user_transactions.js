$(document).ready(function(){

	var session_params = $('body').data('session-params');

	var $chart = $('#chartdiv');
	var $donut = $('#donutdiv');

	var params = {
		"user_id": $chart.data('user-id'),
		"days": $chart.data('days')
	};

	$.extend(params, session_params);

	$.get('./ajax/plot_user_transactions.php', params, 'json')
	.done( function(data){

		var transactions = data.transactions;
		var users = data.users;
		var groups = data.groups;

		var graph = new Array();
		var graphTrans = new Array();

		var donut = new Array();
		var donutData = new Array();

		users.getIndex = function(userCode){
			for (var i = 0; i < this.length; i++){
				if (userCode == this[i].c){
					return i;
				}
			}
			return null;
		}

		groups.findById = function(id){
			for (var i = 0; i < this.length; i++){
				if (id == this[i].id){
					return this[i];
				}
			}
			return null;
		}

		donut.add = function(transaction, users){

			var user = users[transaction.userIndex];

			for (i = 0; i < this.length; i++){
				if (user.c == this[i][0]){
					this[i][1]++;
					return i;
				}
			}

			this.push([user.c, 1, user.n, '']);
			return this.length - 1;
		}

		donutData.add = function(transaction, sliceIndex){
			var slice = {
				in:0,
				out:0,
				amountIn: 0,
				amountOut: 0,
				userIndex: null,
			};

			if (sliceIndex == this.length){
				this.push(slice);
			}
			this[sliceIndex].in += (transaction.out) ? 0 : 1;
			this[sliceIndex].out += (transaction.out) ? 1 : 0;
			this[sliceIndex].amountIn += (transaction.out) ? 0 : transaction.a;
			this[sliceIndex].amountOut += (transaction.out) ? transaction.a : 0;
			this[sliceIndex].userIndex = transaction.userIndex;
		}

		var balance = Number(data.beginBalance);
		var beginDate = Number(data.begin) * 1000;
		var prevDate = beginDate;
		graph.push([beginDate, balance, '']);
		graphTrans.push(0);

		for (var i2 = 0; i2 < transactions.length; i2++){
			var t = transactions[i2];
			var tDate = Number(t.date * 1000);
			var amount = Number(t.a);

			t.userIndex = users.getIndex(t.c);
			var u = users[t.userIndex];

			amount = (t.out) ? amount * -1 : amount;

			if (tDate > prevDate){
				graph.push([tDate, balance, '']);
				graphTrans.push(0);
				prevDate = tDate;
			}

			balance += Number(amount);
			tDate = prevDate + 1;
			var d = new Date(tDate);
			var plus = (amount > 0) ? '+' : '';
			var str = '<tr><td>'+d.getFullYear()+'-'+(d.getMonth()+1)+'-'+d.getDate()+'</td></tr>';
			str += '<tr><td>'+plus+amount+' '+data.currency+'</td></tr>';
			str += '<tr><td>'+t.desc+'</td></tr>';
			str += '<tr><td>'+u.c+' '+u.n+'</td></tr>';
			str += (u.g) ? '<tr><td>'+groups.findById(u.g).n+'</td></tr>' : '';
			graph.push([tDate, balance, str]);
			graphTrans.push(t.id);
			prevDate++;

			sliceIndex = donut.add(t, users);
			donutData.add(t, sliceIndex);
		}

		$.each(donut, function(index, de){
			var ddi = donutData[index];
			var ui = ddi.userIndex;
			
			var ddd = (users[ui].g) ? '<tr><td>'+ groups.findById(users[ui].g).n +'</td></td>' : '';
			ddd += (ddi.out) ? '<tr><td><strong>-</strong> '+ddi.out+' transacties, <strong>-</strong> '+ddi.amountOut+' '+data.currency+'</td></tr>' : '';
			ddd += (ddi.in) ? '<tr><td><strong>+</strong> '+ddi.in+' transacties, <strong>+</strong> '+ddi.amountIn+' '+data.currency+'</td></tr>' : '';
			de[3] = ddd;
		});

		var endDate = Number(data.end) * 1000;
		graph.push([endDate, balance, '']);
		graphTrans.push(0);
		graph = [[[beginDate, 0], [endDate, 0]], graph];

		var chart_c = $.jqplot('chartdiv', graph, {
			grid: {shadow: false},
			cursor: {
				show: true,
				zoom: true
			},
			axes: {
				xaxis: {
					renderer:$.jqplot.DateAxisRenderer,
					numberTicks: data.ticks,
					tickOptions:{
						formatString: '%m'
					}
				},
				yaxis: {
					tickOptions:{
						formatString: '%.0f',
						fontFamily: 'Georgia',
						fontSize: '10pt'
					},
				},
			},
			axesDefaults: {
				pad: 1
			},
			fillBetween: {
				series1: 0,
				series2: 1,
				color: 'rgba(0, 0, 255, 0.1)',
				baseSeries: 0,
				fill: true
			},
			seriesDefaults: {
				showMarker: false,
				color: 'rgb(225, 225, 255)',
				shadow: false
			},
			series: [
				{
				},
				{
					color: 'rgb(0, 0, 0)',
					highlighter: {
						show: true,
						tooltipAxes: 'y',
						tooltipLocation: 'sw',
						useAxesFormatters: false,
						yvalues: 3,
						formatString:'<table class="jqplot-highlighter"><tr><td>%2$s '+data.currency+'</td></tr>%3$s</table>',
					}
				},
			],
			highlighter: {
				show: true
			}
		});

		$chart.on('jqplotDataClick',
			function (ev, seriesIndex, pointIndex, data) {                

			if (seriesIndex != 1){
				return;
			}

			var tid = graphTrans[pointIndex];

			var params = {"id" : tid};

			$.extend(params, session_params);

			if (tid){
				window.location.href = './transactions.php?' + $.param(params);
			}
		});

		$chart.on('jqplotDataMouseOver', function (ev, seriesIndex, pointIndex, ev) {

			if (!graphTrans[pointIndex] || seriesIndex != 1){
				$('.jqplot-event-canvas').css('cursor', 'crosshair');
				return;
			}
			$('.jqplot-event-canvas').css('cursor', 'pointer');
		});

		$chart.on('jqplotDataUnhighlight', function (ev, seriesIndex, pointIndex, evData) {
			$('.jqplot-event-canvas').css('cursor', 'crosshair');			
		});

		var donut_c = $.jqplot('donutdiv', [donut] , {
			grid: {borderWidth: 0, shadow: false},
			seriesDefaults: {
				renderer:$.jqplot.DonutRenderer,
				rendererOptions:{
					padding: 0,
					sliceMargin: 3,
					startAngle: -90,
					showDataLabels: true,
					dataLabels: 'label',
					shadow:false,
				},
			},
			highlighter : {
				showTooltip: true,
				tooltipFade: true,
				show: true,
				yvalues: 4,
				formatString: '<table class="jqplot-highlighter"><tr><td>%1$s %3$s</td></tr>%4$s</table>',
				tooltipLocation: 'sw', 
				useAxesFormatters: false 
			}
		});

		$donut.on('jqplotDataMouseOver', function (ev, seriesIndex, pointIndex, ev) {
			var dd = donutData[pointIndex];
			var user = users[dd.userIndex];

			if (user.l){
				$('.jqplot-event-canvas').css('cursor', 'pointer');
			} else {
				$('.jqplot-event-canvas').css('cursor', 'default');
			}
		});

		$donut.on('jqplotDataHighlight', function(ev, seriesIndex, pointIndex, evdata){
			var dd = donutData[pointIndex];
			var user = users[dd.userIndex];

			if (user.l){
				$('.jqplot-event-canvas').css('cursor', 'pointer');
			} else {
				$('.jqplot-event-canvas').css('cursor', 'default');
			}
		}); 

		$donut.on('jqplotDataUnhighlight', function(ev, seriesIndex, pointIndex, evdata){
			$('.jqplot-event-canvas').css('cursor', 'default');
		});

		$donut.on('jqplotDataClick', function(ev, seriesIndex, pointIndex, evdata){
			var user = users[donutData[pointIndex].userIndex];
			if (user.l){

				var params = {"id": user.id};

				$.extend(params, session_params);

				window.location.href = './users.php?' + $.param(params);
			}
		});

		$chart.on('resize', function(event, ui) {
			chart_c.replot( { resetAxes: true } );
		});

		$donut.on('resize', function(event, ui) {
			donut_c.replot( { resetAxes: true } );
		});
	});
});
