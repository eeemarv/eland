;jQuery(document).ready(function($){
	$.ajax({
		url: '/plot_user_transactions.php',
		dataType: 'json',
		data: { user_id: user_id },
		success:function(data){

			var transactions = data.transactions;
			var users = data.users;

			var graph = new Array();
			var graphTrans = new Array();

			var donut = new Array();
			var donutData = new Array();

			users.getIndex = function(userCode){
				for (var i = 0; i < this.length; i++){
					if (userCode == this[i].code){
						return i;
					}
				}
				return null;
			}

			donut.add = function(transaction, users){
				var userCode = users[transaction.userIndex].code;
				for (i = 0; i < this.length; i++){
					if (userCode == this[i][0]){
						this[i][1]++;
						return i;
					}
				}
				this.push([userCode, 1]);
				return this.length - 1;
			}

			donutData.add = function(transaction, sliceIndex){
				if (sliceIndex == this.length){
					this.push({
						in:0,
						out:0,
						amountIn: 0,
						amountOut: 0,
						transIndexes: [],
						userIndex: null,
						});
				}
				this[sliceIndex].in += (transaction.out) ? 0 : 1;
				this[sliceIndex].out += (transaction.out) ? 1 : 0;
				this[sliceIndex].amountIn += (transaction.out) ? 0 : transaction.amount;
				this[sliceIndex].amountOut += (transaction.out) ? transaction.amount : 0;
				this[sliceIndex].transIndexes.push();
				this[sliceIndex].userIndex = transaction.userIndex;
			}

			var balance = Number(data.beginBalance);
			var beginDate = Number(data.begin) * 1000;
			var prevDate = beginDate;
			graph.push([beginDate, balance]);
			graphTrans.push([0, 0]);

			for (var u = 0; u < transactions.length; u++){
				var tDate = Number(transactions[u].date * 1000);
				var amount = Number(transactions[u].amount);
				amount = (transactions[u].out) ? amount * -1 : amount;

				if (tDate > prevDate){
					graph.push([tDate, balance]);
					graphTrans.push([1, u]);
					prevDate = tDate;
				}

				balance += Number(amount);
				tDate = prevDate + 1;
				graph.push([tDate, balance]);
				graphTrans.push([1, u]);
				prevDate++;
				transactions[u].userIndex = users.getIndex(transactions[u].userCode);
				sliceIndex = donut.add(transactions[u], users);
				donutData.add(transactions[u], sliceIndex);
			}

			var endDate = Number(data.end) * 1000;
			graph.push([endDate, balance]);
			graphTrans.push([0, 0]);
			graph = [[[beginDate, 0], [endDate, 0]], graph];

			$.jqplot('chartdiv1', graph, {
				grid: {shadow: false},
				cursor: {
					show: true,
					zoom: true,
				},
				axes: {
					xaxis: {
						renderer:$.jqplot.DateAxisRenderer,
						numberTicks: data.ticks,
						tickOptions:{
							formatString: '%m',
						}
					},
					yaxis: {
						tickOptions:{
							formatString: '%.0f',
					        fontFamily: 'Georgia',
							fontSize: '10pt',
						},
					},
				},
				axesDefaults: {
					pad: 1,
				},
				fillBetween: {
					series1: 0,
					series2: 1,
					color: 'rgba(0, 0, 255, 0.1)',
					baseSeries: 0,
					fill: true,
				},
				seriesDefaults: {
					showMarker: false,
					color: 'rgb(225, 225, 255)',
					shadow: false,
				},
				series: {
					1: {color: 'rgb(0, 0, 127)'},
				},
			});

			$('#chartdiv1').bind('jqplotDataMouseOver', function (ev, seriesIndex, pointIndex, evData) {

				if (!graphTrans[pointIndex][0] || seriesIndex != 1){
					return;
				}

				var transactionData = transactions[graphTrans[pointIndex][1]];
				var transDate = new Date(transactionData.date * 1000);
				var transDateString = transDate.getDate() + '-' + (Number(transDate.getMonth()) + 1) + '-' + transDate.getFullYear();

				var transdiv = '<div class="tooltip-div"><p>';
				transdiv += transactionData.userCode + ' ' + users[transactionData.userIndex].name;
				transdiv += '<br/><strong>';
				transdiv += (transactionData.out) ? '-' : '+';
				transdiv += '</strong>'+ transactionData.amount + ' ' + data.currency + ' ';
				transdiv += transDateString;
				transdiv += '<br/>'+transactionData.desc;
				transdiv += '</p></div>';
				$(this).append(transdiv);

			});

			$('#chartdiv1').bind('jqplotDataUnhighlight', function (ev, seriesIndex, pointIndex, evData) {
				$('div.tooltip-div').remove();
			});

			$.jqplot('chartdiv2', [donut] , {
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
				  }
				},

			});

			$('#chartdiv2').bind('jqplotDataHighlight', function(ev, seriesIndex, pointIndex, evdata){
				var dd = donutData[pointIndex];
				var user = users[dd.userIndex];

				if (user.linkable){
					$(this).css('cursor', 'pointer');
				}
				var dddiv = '<div class="tooltip-div"><p>'+user.code+' '+user.name;
				dddiv += (dd.out) ? '<br/><strong>-</strong> '+dd.out+' transacties, '+dd.amountOut+' '+data.currency : '';
				dddiv += (dd.in) ? '<br/><strong>+</strong> '+dd.in+' transacties, '+dd.amountIn+' '+data.currency : '';
				dddiv += '</p></div>';
				$(this).append(dddiv);
			});

			$('#chartdiv2').bind('jqplotDataUnhighlight', function(ev, seriesIndex, pointIndex, evdata){
				$('div.tooltip-div').remove();
				$(this).css('cursor', 'default');
			});

			$('#chartdiv2').bind('jqplotDataClick', function(ev, seriesIndex, pointIndex, evdata){
				var user = users[donutData[pointIndex].userIndex];
				if (user.linkable){
					window.location.href = user_link_location + user.id;
				}
			});

			$('#chartdiv1').bind('resize', function(event, ui) {
				plot1.replot( { resetAxes: true } );
			});

			$('#chartdiv2').bind('resize', function(event, ui) {
				plot1.replot( { resetAxes: true } );
			});
		}
	});
});
