<?php
namespace bb;

use bb\classes\Category;
use bb\classes\DohRash;
use bb\classes\DohRashesAnalisys;
use bb\classes\Model;
use bb\classes\Razdel;
use bb\classes\SubRazdel;
use bb\classes\WorkShift;
use bb\classes\WorkShiftMonthReport;
use Classes\Deal;
use bb\classes\tovar;

session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);
set_time_limit(120);

require ($_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');

Base::loginCheck();

$dateFilter = isset($_GET['date']) ? $_GET['date'] : null; // Format: YYYY-MM-DD

if($dateFilter) {
// Get the date filter from the GET request
$dateF = new \DateTime($dateFilter);

$from = clone $dateF;
  $from->setTime(0,0,0);
$to = clone $from;
  $to->setTime(23,59,59);
  $mysqli = Db::getInstance()->getConnection();

//Base::varDamp($from);
//Base::varDamp($to);
// Build the SQL query with optional date filtering
  $query = "SELECT * FROM t WHERE `time` BETWEEN '".$from->getTimestamp()."' AND '".$to->getTimestamp()."'";


  $result = $mysqli->query($query);
  if (!$result) {
    die('Database error: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
  }

  $data = [];
  while ($row = $result->fetch_assoc()) {
    $data[] = [
      't' => (double)$row['t'],        // Temperature as a number
      'h' => (double)$row['h'],        // Humidity as a number
      'time' => $row['time']*1000  // Convert time to milliseconds for JS
    ];
  }
  $result->free();

// Send the data as JSON
  header('Content-Type: application/json');
  echo json_encode($data);

  exit; // Important: Stop further execution to prevent HTML output
}

echo Base::pageStartB5('Температура');
//echo Base::PostCheck();

$mysqli = Db::getInstance()->getConnection();

//$query = "SELECT * FROM t";
//$result = $mysqli->query($query);
//if (!$result) die( 'Сбой при доступе к базе данных: ' . $query . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);

?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    function fetchDataAndCreateChart(dateFilter = null) { //datefilter should be yyyy-mm-dd string. null for all
      const url = `tt.php${dateFilter ? `?date=${dateFilter}` : ''}`;


      fetch(url)
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }

          return response.json();
        })
        .then(data => {
          createChart(data);
        })
        .catch(error => {
          console.error('Error fetching data:', error);
        });
    }

    let myChart; // Declare myChart outside createChart() so it's in scope

    function createChart(data) {

      // Destroy the previous chart instance if it exists:
      if (myChart) {
        myChart.destroy();
      }

      const timestamps = data.map(item => new Date(item.time));
      const temperatures = data.map(item => item.t);
      const humidities = data.map(item => item.h);


      const ctx = document.getElementById('chart').getContext('2d');
      myChart = new Chart(ctx, {
        type: 'line', // or 'bar', 'scatter', etc.
        data: {
          labels: timestamps,  // x-axis labels
          datasets: [{
            label: 'Temperature',
            data: temperatures,
            borderColor: 'red',
            tension: 0.4
          },
            {
              label: 'Humidity',
              data: humidities,
              borderColor: 'blue',
              tension: 0.4
            }]
        },
        options: {
          tooltips: { // New or modified tooltips config
            callbacks: {
              label: function(tooltipItem, data) {
                let label = data.datasets[tooltipItem.datasetIndex].label || '';
                if (label) {
                  label += ': ';
                }

                const datasetLabel = data.datasets[tooltipItem.datasetIndex].label;
                const timeValue = tooltipItem.label;
                const value = tooltipItem.yLabel;
                const date = new Date(parseInt(timeValue,10));
                const formattedTime = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                // Customize the tooltip content:
                label += value; // Add the t/h value
                if (datasetLabel === "Temperature"){
                  return `${formattedTime}: T: ${value}°C`;
                }
                if (datasetLabel === "Humidity"){
                  return `${formattedTime}: H: ${value}%`;
                }

              }

            }
          },
          scales: {
            x: {
              type: 'linear',  // For numeric time values
              ticks: {
                callback: function(value, index, ticks) {
                  const date = new Date(value);
                  // Format the time part as needed (e.g., HH:mm:ss)
                  return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}); // Options for time formatting
                }
              }
            }
          }
        }
      });
    }




    document.addEventListener('DOMContentLoaded', () => {
      //initial chart data load
      fetchDataAndCreateChart();


      //date filter event
      const datePicker = document.getElementById('dateFilter');

      datePicker.addEventListener('change', (event) => {
        const selectedDate = event.target.value; // "YYYY-MM-DD"
        fetchDataAndCreateChart(selectedDate);

      });


    })


  </script>
  </head>
  <body>
  <input type="date" id="dateFilter">
  <canvas id="chart"></canvas>
  </body>
<?php

echo Base::pageEndHtmlB5();

