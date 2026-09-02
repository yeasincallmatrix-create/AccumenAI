/* analytics-charts.js — read-only Chart.js visuals for Education Analytics overview */
(function () {
  if (typeof Chart === 'undefined') return;

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    // Attendance trend line
    var el = document.getElementById('analyticsAttendanceChart');
    if (el) {
      try {
        var raw = el.getAttribute('data-json');
        var points = raw ? JSON.parse(raw) : [];
        var labels = points.map(function (p) { return p.label; });
        var percents = points.map(function (p) { return p.percent; });
        if (labels.length) {
          new Chart(el, {
            type: 'line',
            data: {
              labels: labels,
              datasets: [{
                label: 'Present %',
                data: percents,
                tension: 0.35,
                fill: true,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.08)',
                pointRadius: 2,
                pointBackgroundColor: '#0d6efd'
              }]
            },
            options: {
              responsive: true,
              plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c){ return c.parsed.y + '% present (' + (points[c.dataIndex].total||0) + ' rec.)'; } } } },
              scales: { y: { beginAtZero: true, max: 100, ticks: { callback: function(v){ return v+'%'; } } }, x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } } }
            }
          });
        }
      } catch (e) { /* silent */ }
    }

    // Results donut — pass vs fail
    var rEl = document.getElementById('analyticsResultsChart');
    if (rEl) {
      var passed = parseInt(rEl.getAttribute('data-passed') || '0', 10);
      var failed = parseInt(rEl.getAttribute('data-failed') || '0', 10);
      if ((passed + failed) > 0) {
        new Chart(rEl, {
          type: 'doughnut',
          data: {
            labels: ['Passed', 'Failed'],
            datasets: [{ data: [passed, failed], backgroundColor: ['#198754', '#dc3545'], borderWidth: 2 }]
          },
          options: { responsive: false, plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
        });
      }
    }

    // Finance donut
    var fEl = document.getElementById('analyticsFinanceChart');
    if (fEl) {
      var recv = parseFloat(fEl.getAttribute('data-receivable') || '0');
      var pay = parseFloat(fEl.getAttribute('data-payable') || '0');
      // net_income is informational; chart shows receivable vs payable
      if ((recv + pay) > 0) {
        new Chart(fEl, {
          type: 'doughnut',
          data: {
            labels: ['Receivable', 'Payable'],
            datasets: [{ data: [recv, pay], backgroundColor: ['#0d6efd', '#ffc107'], borderWidth: 2 }]
          },
          options: { responsive: false, plugins: { legend: { position: 'bottom' } }, cutout: '60%' }
        });
      }
    }
  });
})();
