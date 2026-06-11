<?php
/**
 * Dashboard administrador — 4 KPIs horizontales + rejilla de 6 gráficos (estructura clásica).
 * Requiere: $stats_admin (aprobados, pacientes_activos, personal, total_citas)
 */
?>
<div class="admin-dash">
    <div class="admin-dash-stats">
        <button type="button" class="admin-dash-stat-link" data-admin-kpi="usuarios" data-admin-kpi-count="<?= (int)$stats_admin['aprobados'] ?>" aria-label="Ver usuarios totales">
            <div class="admin-dash-stat-card">
                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= number_format($stats_admin['aprobados']) ?></div>
                    <div class="stat-label">Usuarios totales</div>
                </div>
            </div>
        </button>
        <button type="button" class="admin-dash-stat-link" data-admin-kpi="pacientes" data-admin-kpi-count="<?= (int)$stats_admin['pacientes_activos'] ?>" aria-label="Ver pacientes activos">
            <div class="admin-dash-stat-card">
                <div class="stat-icon stat-icon--green"><i class="fa-solid fa-hospital-user"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= number_format($stats_admin['pacientes_activos']) ?></div>
                    <div class="stat-label">Pacientes activos</div>
                </div>
            </div>
        </button>
        <button type="button" class="admin-dash-stat-link" data-admin-kpi="personal" data-admin-kpi-count="<?= (int)$stats_admin['personal'] ?>" aria-label="Ver personal activo">
            <div class="admin-dash-stat-card">
                <div class="stat-icon stat-icon--amber"><i class="fa-solid fa-user-tie"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= number_format($stats_admin['personal']) ?></div>
                    <div class="stat-label">Personal activo</div>
                </div>
            </div>
        </button>
        <button type="button" class="admin-dash-stat-link" data-admin-kpi="citas" data-admin-kpi-count="<?= (int)$stats_admin['total_citas'] ?>" aria-label="Ver citas registradas">
            <div class="admin-dash-stat-card">
                <div class="stat-icon stat-icon--violet"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= number_format($stats_admin['total_citas']) ?></div>
                    <div class="stat-label">Citas registradas</div>
                </div>
            </div>
        </button>
    </div>

    <div class="admin-dash-charts">
        <div class="card admin-dash-chart-card">
            <div class="card-header"><h3>Distribución de pacientes</h3></div>
            <div class="chart-wrap"><canvas id="adminPatientAgeChart"></canvas></div>
        </div>
        <div class="card admin-dash-chart-card">
            <div class="card-header"><h3>Carga de trabajo por profesional</h3></div>
            <div class="chart-wrap"><canvas id="adminWorkloadChart"></canvas></div>
        </div>
        <div class="card admin-dash-chart-card">
            <div class="card-header"><h3>Crecimiento de usuarios</h3></div>
            <div class="chart-wrap"><canvas id="adminUserGrowthChart"></canvas></div>
        </div>
        <div class="card admin-dash-chart-card">
            <div class="card-header"><h3>Primeras consultas vs. seguimiento</h3></div>
            <div class="chart-wrap"><canvas id="adminAppointmentTypesChart"></canvas></div>
        </div>
        <div class="card admin-dash-chart-card">
            <div class="card-header"><h3>Citas completadas — últimos 7 días</h3></div>
            <div class="chart-wrap"><canvas id="adminDailyAppointmentsChart"></canvas></div>
        </div>
        <div class="card admin-dash-chart-card">
            <div class="card-header"><h3>Citas confirmadas vs. reprogramadas</h3></div>
            <div class="chart-wrap"><canvas id="adminConfirmedReprogrammedChart"></canvas></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const chartColors = ['#02b1f4', '#17a2b8', '#5bc0de', '#94a3b8'];
    const doughnut = (id, url) => {
        const el = document.getElementById(id);
        if (!el) return;
        fetch(url).then(r => r.json()).then(d => {
            new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: d.labels,
                    datasets: [{
                        data: d.data,
                        backgroundColor: chartColors,
                        borderColor: '#fff',
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: { legend: { position: 'right' } }
                }
            });
        }).catch(() => {});
    };
    const barH = (id, url) => {
        const el = document.getElementById(id);
        if (!el) return;
        fetch(url).then(r => r.json()).then(d => {
            new Chart(el, {
                type: 'bar',
                data: { labels: d.labels, datasets: [{ label: 'Citas', data: d.data, backgroundColor: '#02b1f4', borderRadius: 6 }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }).catch(() => {});
    };
    const lineChart = (id, url) => {
        const el = document.getElementById(id);
        if (!el) return;
        fetch(url).then(r => r.json()).then(d => {
            new Chart(el, {
                type: 'line',
                data: { labels: d.labels, datasets: [{ label: 'Usuarios', data: d.data, fill: true, borderColor: '#02b1f4', backgroundColor: 'rgba(2,177,244,0.18)', tension: 0.3 }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } }
            });
        }).catch(() => {});
    };
    const stackedBar = (id, url) => {
        const el = document.getElementById(id);
        if (!el) return;
        fetch(url).then(r => r.json()).then(d => {
            new Chart(el, {
                type: 'bar',
                data: { labels: d.labels, datasets: d.datasets },
                options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { position: 'top' } } }
            });
        }).catch(() => {});
    };
    const barV = (id, url, label) => {
        const el = document.getElementById(id);
        if (!el) return;
        fetch(url).then(r => r.json()).then(d => {
            new Chart(el, {
                type: 'bar',
                data: { labels: d.labels, datasets: [{ label: label || '', data: d.data, backgroundColor: '#02b1f4', borderRadius: 4 }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } }
            });
        }).catch(() => {});
    };
    const pieChart = (id, url) => {
        const el = document.getElementById(id);
        if (!el) return;
        fetch(url).then(r => r.json()).then(d => {
            new Chart(el, {
                type: 'pie',
                data: { labels: d.labels, datasets: [{ data: d.data, backgroundColor: ['#17a2b8', '#ffc107'], borderColor: '#fff', borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } } }
            });
        }).catch(() => {});
    };

    doughnut('adminPatientAgeChart', (window.ECO_BASE || '') + 'api/get_patient_age_distribution.php');
    barH('adminWorkloadChart', (window.ECO_BASE || '') + 'api/get_workload_data.php');
    lineChart('adminUserGrowthChart', (window.ECO_BASE || '') + 'api/get_user_growth_data.php');
    stackedBar('adminAppointmentTypesChart', (window.ECO_BASE || '') + 'api/get_appointment_types_data.php');
    barV('adminDailyAppointmentsChart', (window.ECO_BASE || '') + 'api/get_daily_appointments_data.php', 'Citas');
    pieChart('adminConfirmedReprogrammedChart', (window.ECO_BASE || '') + 'api/get_confirmed_reprogrammed_data.php');
})();
</script>
