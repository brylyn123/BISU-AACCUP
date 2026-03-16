document.addEventListener('DOMContentLoaded', function () {

  // Initialize College Chart
  if (document.getElementById('chartCollege') && typeof chartDataColleges !== 'undefined') {
    const ctxCollege = document.getElementById('chartCollege').getContext('2d');

    const labels = chartDataColleges.map(item => item.college_code);
    const data = chartDataColleges.map(item => item.count);

    new Chart(ctxCollege, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Documents',
          data: data,
          backgroundColor: '#4f46e5',
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { display: false } },
          x: { grid: { display: false } }
        }
      }
    });
  }

  // Initialize Area Chart
  if (document.getElementById('chartArea') && typeof chartDataAreas !== 'undefined') {
    const ctxArea = document.getElementById('chartArea').getContext('2d');

    const labels = chartDataAreas.map(item => 'Area ' + item.area_no);
    const data = chartDataAreas.map(item => item.count);

    new Chart(ctxArea, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Documents',
          data: data,
          borderColor: '#0ea5e9',
          backgroundColor: 'rgba(14, 165, 233, 0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, display: false },
          x: { display: false }
        },
        elements: {
          point: { radius: 0 }
        }
      }
    });
  }
});