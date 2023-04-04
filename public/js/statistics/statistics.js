console.log('statistics.js load');

var dataInfoArray = [];
var idBlockParent = 'chart-sales';
var idBlockCurent = 'chart-sales';
var textId = 'chart-sales';

var idBlockParentRefunds = 'chart-refunds';
var idBlockCurentRefunds = 'chart-refunds';
var textIdRefunds = 'chart-refunds';

var idBlockParentProducts = 'chart-products';
var idBlockCurentProducts = 'chart-products';
var textIdProducts = 'chart-products';

var count = 1;
var countRefunds = 1;
var countProducts = 1;

var activeLink = 'sales';

var spiner = '<div class="container"><div class="row text-center justify-content-center" style="margin-top:20px;"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div></div>';


var tableSale = new DataTable('#myTableSales', {
    responsive: true,
});

function getFormatDate(dateObject) {
    let d = new Date(dateObject);
    let day = d.getDate();
    let month = d.getMonth() + 1;
    let year = d.getFullYear();

    if (day < 10) {
        day = "0" + day;
    }

    if (month < 10) {
        month = "0" + month;
    }

    let date = year + "-" + month + "-" + day;

    return date;
};

function getInfoByCircle(data) {
    console.log('click')

    let timeStampDate = data.date;
    let myDate = getFormatDate(data.date)

    data = {
        step: $('#step-interval').val(),
        shopId: $('#shop').val(),
        unit: $('#count').val(),
        dateStartSales: $('#date-start-sales').val(),
        dateEndSales: $('#date-end-sales').val(),
        union: false,
        type: 'sales',
        checkedSp: $('#sales-CheckedSp').is(':checked'),
        checkedSelfPurchase: $('#sales-CheckedSelfPurchase').is(':checked'),
        checkedStatusCancel: $('#sales-CheckedStatusCancel').is(':checked'),
        article: $('#sales-article-product').val(),
    };

    if ($('#step-interval').val() == 'day') {
        data.dateStartSales = myDate;
        data.dateEndSales = myDate;
    }

    if ($('#step-interval').val() == 'month') {
        let date = new Date(timeStampDate);
        let firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
        let lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);
        data.dateStartSales = getFormatDate(firstDay);
        data.dateEndSales = getFormatDate(lastDay);
    }

    if ($('#step-interval').val() == 'year') {
      let date = new Date(timeStampDate);
      let firstDay = date.getFullYear() + "-" + 01 + "-" + 01;
      let lastDay = date.getFullYear() + "-" + 12 + "-" + 31;
      data.dateStartSales = firstDay;
      data.dateEndSales = lastDay;
    }

    console.log(data)

    $.ajax({
      url: '/get-info-statics-sales-by-date-for-table',
      method: 'post',
      dataType: "json",
      data: data,
      async: true,
      success: function(data) {
          console.log(data)

          tableSale.clear().draw();
          tableSale.rows.add(data.data).draw();
      },
      error: function (jqXHR, exception) {
         /* if (jqXHR.status === 0) {
              alert('Not connect. Verify Network.');
          } else if (jqXHR.status == 404) {
              alert('Requested page not found (404).');
          } else if (jqXHR.status == 500) {
              alert('Internal Server Error (500).');
          } else if (exception === 'parsererror') {
              alert('Requested JSON parse failed.');
          } else if (exception === 'timeout') {
              alert('Time out error.');
          } else if (exception === 'abort') {
              alert('Ajax request aborted.');
          } else {
              alert('Uncaught Error. ' + jqXHR.responseText);
          }*/
      }
  });
}

/**
 * Получение информации с сервера (дата и значение)
 */
function getInfoStaticsOrders(union=false, type) {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    if (type == 'sales') {
        $( "#" + idBlockCurent).html(spiner);
        var url = '/get-info-statics-order';
        data = {
            step: $('#step-interval').val(),
            shopId: $('#shop').val(),
            unit: $('#count').val(),
            dateStartSales: $('#date-start-sales').val(),
            dateEndSales: $('#date-end-sales').val(),
            union: union,
            type: 'sales',
            checkedSp: $('#sales-CheckedSp').is(':checked'),
            checkedSelfPurchase: $('#sales-CheckedSelfPurchase').is(':checked'),
            checkedStatusCancel: $('#sales-CheckedStatusCancel').is(':checked'),
            article: $('#sales-article-product').val(),
        };
    };

    if (type == 'refunds')
    {
        $( "#" + idBlockCurentRefunds).html(spiner);
        var url = '/get-info-statics-order';
        data = {
            step: $('#step-interval-refunds').val(),
            shopId: $('#shop-refunds').val(),
            unit: $('#count-refunds').val(),
            dateStartSales: $('#date-start-refunds').val(),
            dateEndSales: $('#date-end-refunds').val(),
            union: union,
            type: 'refunds',
            checkedSp: $('#refunds-CheckedSp').is(':checked'),
            checkedSelfPurchase: $('#refunds-CheckedSelfPurchase').is(':checked'),
            checkedStatusCancel: $('#refunds-CheckedStatusCancel').is(':checked'),
            article: $('#refunds-article-product').val(),
        };
    }

    if (type == 'products')
    {
        $( "#" + idBlockCurentProducts).html(spiner)
        var url = '/get-info-statics-product';
        data = {
            step: $('#step-interval-products').val(),
            warehousesId: $('#warehouses-products').val(),
            unit: $('#count-products').val(),
            dateStart: $('#date-start-products').val(),
            dateEnd: $('#date-end-products').val(),
            union: union,
            article: $('#products-article-product').val(),
        };
    }
    
    $.ajax({
        url: url,
        method: 'post',
        dataType: "json",
        data: data,
        async:true,
        success: function(data) {
            setDataInfoArray(data.data)
            if (type == 'sales') {
                makeid('sales');
                $( "#" + idBlockParent).remove();
                if ($('#flexCheckCheckedDiagramma').is(':checked')) {
                  diagramaLine(idBlockCurent);
                  return;
                }
                diagramaColumn(idBlockCurent);
            }
            if (type == 'refunds'){
                makeid('refunds');
                $( "#" + idBlockParentRefunds).remove();
                if ($('#flexCheckCheckedDiagramma-refunds').is(':checked')) {
                  diagramaLine(idBlockCurentRefunds);
                  return;
                }
                diagramaColumn(idBlockCurentRefunds);
            }
            if (type == 'products') {
                makeid('products');
                $( "#" + idBlockParentProducts).remove();
            
                if ($('#flexCheckCheckedDiagramma-products').is(':checked')) {
                  diagramaLine(idBlockCurentProducts);
                  return;
                }
            
                diagramaColumn(idBlockCurentProducts);
            }
        },
        error: function (jqXHR, exception) {
            if (jqXHR.status === 0) {
                alert('Not connect. Verify Network.');
            } else if (jqXHR.status == 404) {
                alert('Requested page not found (404).');
            } else if (jqXHR.status == 500) {
                alert('Internal Server Error (500).');
            } else if (exception === 'parsererror') {
                alert('Requested JSON parse failed.');
            } else if (exception === 'timeout') {
                alert('Time out error.');
            } else if (exception === 'abort') {
                alert('Ajax request aborted.');
            } else {
                alert('Uncaught Error. ' + jqXHR.responseText);
            }
        }
    });
}

/**
 * назначение пришедшего массива в глобальнцю переменную
 * 
 * @param array data
 * 
 */
function setDataInfoArray(data) {
    dataInfoArray = data;
}

/**
 * Генерация диаграммы (однополосной)
 * 
 * @param string id
 * 
 */
function diagramaLine(id) {
        // Create root element
        // https://www.amcharts.com/docs/v5/getting-started/#Root_element 
        var root = am5.Root.new(id);
        

        // Set themes
        // https://www.amcharts.com/docs/v5/concepts/themes/ 
        root.setThemes([
          am5themes_Animated.new(root)
        ]);
        
        
        // Create chart
        // https://www.amcharts.com/docs/v5/charts/xy-chart/
        var chart = root.container.children.push(am5xy.XYChart.new(root, {
          panX: true,
          panY: true,
          wheelX: "panX",
          wheelY: "zoomX",
          maxTooltipDistance: 1000,
          pinchZoomX: true,
          synchronizeGrid: true,
        }));
        
        
        var date = new Date();
        date.setHours(0, 0, 0, 0);
        var value = 1000000;
        
        function generateData() {
            value = Math.round((Math.random() * 1000 - 4.2) + value);
            am5.time.add(date, "day", 1);
            return {
              date: date.getTime(),
              value: value + 10000
            };
        }

        formatDateXCustom = "day";
        if ($('#step-interval').val() == 'month') {
            formatDateXCustom = "month";
        }
        if ($('#step-interval').val() == 'day') {
            formatDateXCustom = "day";
        }
        if ($('#step-interval').val() == 'year') {
            formatDateXCustom = "year";
        }
        if ($('#step-interval').val() == 'week') {
            formatDateXCustom = "week";
        }

        // Create axes
        // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/
        var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
          maxDeviation: 0.2,
          baseInterval: {
            timeUnit: formatDateXCustom,
            count: 1
          },
          renderer: am5xy.AxisRendererX.new(root, {}),
          tooltip: am5.Tooltip.new(root, {})
        }));
        
        var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
          renderer: am5xy.AxisRendererY.new(root, {})
        }));
        //yAxis.zoomToValues(100, 200);


        // Add series
        // https://www.amcharts.com/docs/v5/charts/xy-chart/series/
        function createChart(dataArray, name) {
            var series = chart.series.push(am5xy.SmoothedXLineSeries.new(root, {
                name: name,
                xAxis: xAxis,
                yAxis: yAxis,
                valueYField: "value",
                valueXField: "date",
                legendValueText: "{valueY}",
                tooltip: am5.Tooltip.new(root, {
                  pointerOrientation: "horizontal",
                  labelText: "{valueY}"
                })
              }));
            
              series.bullets.push(function() {
                  var circle = am5.Circle.new(root, {
                      radius: 6,
                      fill: series.get("fill"),
                      stroke: root.interfaceColors.get("background"),
                      strokeWidth: 2
                  });
                
                  circle.events.on("click", function(e) {
                      getInfoByCircle(e.target.dataItem.dataContext)
                      //console.log("bullet clicked", e.target.dataItem.dataContext)
                  })

                  return am5.Bullet.new(root, {
                      sprite: circle
                  });
              });

            formatDateCustom = "yyyy-MM-dd";

            if ($('#step-interval').val() == 'month') {
                formatDateCustom = "yyyy-MM";
            }
            if ($('#step-interval').val() == 'day') {
                formatDateCustom = "yyyy-MM-dd";
            }
            if ($('#step-interval').val() == 'year') {
                formatDateCustom = "yyyy";
            }
            if ($('#step-interval').val() == 'week') {
                formatDateCustom = "yyyy-w";
            }

            series.data.processor = am5.DataProcessor.new(root, {
                dateFormat: formatDateCustom,
                dateFields: ["date"],
                numericFields: ["value"]
            });

            series.data.setAll(dataArray);
            
            // Make stuff animate on load
            // https://www.amcharts.com/docs/v5/concepts/animations/
            series.appear();
        }
        
        $.each(dataInfoArray, function(key) {
            createChart(dataInfoArray[key], key)
        });


        // Add cursor
        // https://www.amcharts.com/docs/v5/charts/xy-chart/cursor/
        var cursor = chart.set("cursor", am5xy.XYCursor.new(root, {
          behavior: "zoomX"
        }));

        cursor.lineY.set("visible", true);
        
        // Add scrollbar
        // https://www.amcharts.com/docs/v5/charts/xy-chart/scrollbars/
        chart.set("scrollbarX", am5.Scrollbar.new(root, {
          orientation: "horizontal"
        }));
        
        chart.set("scrollbarY", am5.Scrollbar.new(root, {
          orientation: "vertical"
        }));
        
        
        // Add legend
        // https://www.amcharts.com/docs/v5/charts/xy-chart/legend-xy-series/
        var legend = chart.rightAxesContainer.children.push(am5.Legend.new(root, {
          width: 200,
          paddingLeft: 15,
          height: am5.percent(100)
        }));
        
        // When legend item container is hovered, dim all the series except the hovered one
        legend.itemContainers.template.events.on("pointerover", function(e) {
          var itemContainer = e.target;
        
          // As series list is data of a legend, dataContext is series
          var series = itemContainer.dataItem.dataContext;
        
          chart.series.each(function(chartSeries) {
            if (chartSeries != series) {
              chartSeries.strokes.template.setAll({
                strokeOpacity: 0.15,
                stroke: am5.color(0x000000)
              });
            } else {
              chartSeries.strokes.template.setAll({
                strokeWidth: 3
              });
            }
          })
        })
        
        // When legend item container is unhovered, make all series as they are
        legend.itemContainers.template.events.on("pointerout", function(e) {
          var itemContainer = e.target;
          var series = itemContainer.dataItem.dataContext;
        
          chart.series.each(function(chartSeries) {
            chartSeries.strokes.template.setAll({
              strokeOpacity: 1,
              strokeWidth: 1,
              stroke: chartSeries.get("fill")
            });
          });
        })
        
        legend.itemContainers.template.set("width", am5.p100);
        legend.valueLabels.template.setAll({
          width: am5.p100,
          textAlign: "right"
        });
        
        // It's is important to set legend data after all the events are set on template, otherwise events won't be copied
        legend.data.setAll(chart.series.values);
        
        
        // Make stuff animate on load
        // https://www.amcharts.com/docs/v5/concepts/animations/
        chart.appear(1000, 100);
}

/**
 * Генерация диаграммы (однополосной)
 * 
 * @param string id
 * 
 */
function diagramaColumn(id) {
  // Create root element
  // https://www.amcharts.com/docs/v5/getting-started/#Root_element 
  var root = am5.Root.new(id);
  

  // Set themes
  // https://www.amcharts.com/docs/v5/concepts/themes/ 
  root.setThemes([
    am5themes_Animated.new(root)
  ]);
  
  
  // Create chart
  // https://www.amcharts.com/docs/v5/charts/xy-chart/
  var chart = root.container.children.push(am5xy.XYChart.new(root, {
    panX: true,
    panY: true,
    wheelX: "panX",
    wheelY: "zoomX",
    maxTooltipDistance: 1000,
    pinchZoomX: true,
    synchronizeGrid: true,
    layout: root.verticalLayout
  }));
  
  formatDateXCustom = "day";
  if ($('#step-interval').val() == 'month') {
      formatDateXCustom = "month";
  }
  if ($('#step-interval').val() == 'day') {
      formatDateXCustom = "day";
  }
  if ($('#step-interval').val() == 'year') {
      formatDateXCustom = "year";
  }
  if ($('#step-interval').val() == 'week') {
      formatDateXCustom = "week";
  }

  // Create axes
  // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/
  var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
    maxDeviation: 0.2,
    baseInterval: {
      timeUnit: formatDateXCustom,
      count: 1
    },
    renderer: am5xy.AxisRendererX.new(root, {}),
    tooltip: am5.Tooltip.new(root, {})
  }));
  
  var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
    renderer: am5xy.AxisRendererY.new(root, {})
  }));
  //yAxis.zoomToValues(100, 200);


  // Add series
  // https://www.amcharts.com/docs/v5/charts/xy-chart/series/
  function createChart(dataArray, name) {
      var series = chart.series.push(am5xy.ColumnSeries.new(root, {
          name: name,
          xAxis: xAxis,
          yAxis: yAxis,
          valueYField: "value",
          valueXField: "date",
          legendValueText: "{valueY}",
          tooltip: am5.Tooltip.new(root, {
            pointerOrientation: "vertical",
            labelText: "{valueY}"
          })
        }));
      
        series.bullets.push(function() {
          var circle = am5.Circle.new(root, {
            radius: 4,
            fill: series.get("fill"),
            stroke: root.interfaceColors.get("background"),
            strokeWidth: 2
          });
        
          return am5.Bullet.new(root, {
            sprite: circle
          });
      });
  

      formatDateCustom = "yyyy-MM-dd";

      if ($('#step-interval').val() == 'month') {
          formatDateCustom = "yyyy-MM";
      }
      if ($('#step-interval').val() == 'day') {
          formatDateCustom = "yyyy-MM-dd";
      }
      if ($('#step-interval').val() == 'year') {
          formatDateCustom = "yyyy";
      }
      if ($('#step-interval').val() == 'week') {
          formatDateCustom = "yyyy-w";
      }

      series.data.processor = am5.DataProcessor.new(root, {
          dateFormat: formatDateCustom,
          dateFields: ["date"],
          numericFields: ["value"]
      });

      series.data.setAll(dataArray);
      
      // Make stuff animate on load
      // https://www.amcharts.com/docs/v5/concepts/animations/
      series.appear();
  }
  
  $.each(dataInfoArray, function(key) {
      createChart(dataInfoArray[key], key)
  });


  // Add cursor
  // https://www.amcharts.com/docs/v5/charts/xy-chart/cursor/
  var cursor = chart.set("cursor", am5xy.XYCursor.new(root, {
    behavior: "zoomX"
  }));

  cursor.lineY.set("visible", true);
  
  // Add scrollbar
  // https://www.amcharts.com/docs/v5/charts/xy-chart/scrollbars/
  chart.set("scrollbarX", am5.Scrollbar.new(root, {
    orientation: "horizontal"
  }));
  
  chart.set("scrollbarY", am5.Scrollbar.new(root, {
    orientation: "vertical"
  }));
  
  
  // Add legend
  // https://www.amcharts.com/docs/v5/charts/xy-chart/legend-xy-series/
  var legend = chart.rightAxesContainer.children.push(am5.Legend.new(root, {
    width: 200,
    paddingLeft: 15,
    height: am5.percent(100)
  }));
  
  // When legend item container is hovered, dim all the series except the hovered one
  legend.itemContainers.template.events.on("pointerover", function(e) {
    var itemContainer = e.target;
  
    // As series list is data of a legend, dataContext is series
    var series = itemContainer.dataItem.dataContext;
  
    chart.series.each(function(chartSeries) {
      if (chartSeries != series) {
        chartSeries.strokes.template.setAll({
          strokeOpacity: 0.15,
          stroke: am5.color(0x000000)
        });
      } else {
        chartSeries.strokes.template.setAll({
          strokeWidth: 3
        });
      }
    })
  })
  
  // When legend item container is unhovered, make all series as they are
  legend.itemContainers.template.events.on("pointerout", function(e) {
    var itemContainer = e.target;
    var series = itemContainer.dataItem.dataContext;
  
    chart.series.each(function(chartSeries) {
      chartSeries.strokes.template.setAll({
        strokeOpacity: 1,
        strokeWidth: 1,
        stroke: chartSeries.get("fill")
      });
    });
  })
  
  legend.itemContainers.template.set("width", am5.p100);
  legend.valueLabels.template.setAll({
    width: am5.p100,
    textAlign: "right"
  });
  
  // It's is important to set legend data after all the events are set on template, otherwise events won't be copied
  legend.data.setAll(chart.series.values);
  
  
  // Make stuff animate on load
  // https://www.amcharts.com/docs/v5/concepts/animations/
  chart.appear(1000, 100);
}

/**
 * Смена названий блоков
 */
function makeid(type) {
    if (type == 'sales') {
        idBlockParent = idBlockCurent;
        idBlockCurent = textId + '-' + count;
        count = count + 1;
    };

    if (type == 'refunds')
    {
        idBlockParentRefunds = idBlockCurentRefunds;
        idBlockCurentRefunds = textIdRefunds + '-' + countRefunds;
        countRefunds = countRefunds + 1;
    }

    if (type == 'products')
    {
        idBlockParentProducts = idBlockCurentProducts;
        idBlockCurentProducts = textIdProducts + '-' + countProducts;
        countProducts = countProducts + 1;
    }
}

/**
 * ПРОДАЖИ
 * 
 * 
 */
function changefilterOrders(union=false) {
    getInfoStaticsOrders(union, 'sales');
}

/**
 * ВОЗВРАТЫ
 * 
 * 
 */
function changefilterOrdersRefunds(union=false) {
    getInfoStaticsOrders(union, 'refunds');
}

/**
 * ТОВАРЫ
 * 
 */
function changefilterOrdersProducts(union=false) {
    getInfoStaticsOrders(union, 'products');
}

$(document).on('click', '#get-grafics', function(){ changefilterOrders() });
$(document).on('click', '#get-union', function(){ changefilterOrders(true) });

$(document).on('click', '#get-grafics-refunds', function(){ changefilterOrdersRefunds() });
$(document).on('click', '#get-union-refunds', function(){ changefilterOrdersRefunds(true) });

$(document).on('click', '#get-grafics-products', function(){ changefilterOrdersProducts() });
$(document).on('click', '#get-union-products', function(){ changefilterOrdersProducts(true) });