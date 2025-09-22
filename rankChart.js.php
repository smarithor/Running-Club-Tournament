<?php
// rankChart.js.php
header("Content-Type: application/javascript");

// Expect $rankHistory already prepared in the including page
?>

const ctx = document.getElementById('rankChart').getContext('2d');

const datasets = <?=
    json_encode(array_values(array_map(function($f) {
        return [
            'label' => $f['name'],
            'data' => $f['data'], // [{x:date, y:int, comment:string}]
            'fill' => false,
            'borderColor' => sprintf('hsl(%d, 70%%, 50%%)', rand(0,360)),
            'tension' => 0.1
        ];
    }, $rankHistory)), JSON_UNESCAPED_SLASHES)
?>;

new Chart(ctx, {
  type: 'line',
  data: { datasets },
  options: {
    responsive: true,
    interaction: { mode: 'nearest', axis: 'x', intersect: false },
    plugins: {
      tooltip: {
        callbacks: {
          label: (ctx) => {
            const p = ctx.raw;
            let label = `${ctx.dataset.label}: Rank ${p.y}`;
            if (p.comment) label += ` (${p.comment})`;
            return label;
          }
        }
      }
    },
    scales: {
      x: { type: 'time', time: { unit: 'day' }, title: { display: true, text: 'Date' } },
      y: { reverse: true, title: { display: true, text: 'Rank' }, ticks: { precision: 0 } }
    }
  }
});
