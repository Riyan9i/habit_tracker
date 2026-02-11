<script>
    const habitLabels = <?php echo json_encode(array_column($performances, 'name')); ?>;
    const habitCompletion = <?php echo json_encode(array_map(fn($p) => $p['completion_rate'], $performances)); ?>;

    const completionCtx = document.getElementById('completionChart').getContext('2d');
    let completionChart;

    function renderChart(type = '<?php echo $chart_type; ?>') {
        if (completionChart) completionChart.destroy();
        completionChart = new Chart(completionCtx, {
            type: type,
            data: {
                labels: habitLabels,
                datasets: [{
                    label: 'Habit Completion Rate',
                    data: habitCompletion,
                    backgroundColor: 'rgba(76, 175, 80, 0.6)',
                    borderColor: '#4CAF50',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Completion Rate (%)'
                        }
                    }
                }
            }
        });
    }

    document.getElementById('chartType').addEventListener('change', function() {
        renderChart(this.value);
    });

    // প্রথমবার chart load
    renderChart('<?php echo $chart_type; ?>');
</script>
