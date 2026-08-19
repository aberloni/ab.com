$('body').append('<div id="year"></div>');

chart = new Highcharts.Chart({
chart: {renderTo: 'year',type: 'column'},
title: {text: 'Website activity per year'},
subtitle: {text: 'andreberlemont.com'},
xAxis: {categories:[2002,2003,2005,2006,2007,2008,2009,2010,2011,2012,2013,2014,2015,2016,2017,2018,2019,2020,2021,2022,2023,2024,2025,2026]},
yAxis: {min: 0,title: {  text: 'Quantity of articles' }},
series: [{name: 'Articles',data:[1,2,1,1,2,2,3,5,13,22,36,49,34,29,28,24,23,14,18,21,14,11,20,8]}]
});
