$('body').append('<div id="month"></div>');

chart = new Highcharts.Chart({
chart: {renderTo: 'month',type: 'column'},
title: {text: 'Website activity per month'},
subtitle: {text: 'andreberlemont.com'},
xAxis: {categories:['Janv','Fevr','Mars','Avri','Mai','Juin','Juil','Aou','Sept','Oct','Nov','Dec']},
yAxis: {min: 0,title: {  text: 'Quantity of articles' }},
series: [{name: 'Articles',data:[1,1,1,0,3,2,0,0,0,0,0,0]}]
});
