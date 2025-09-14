<?php
session_start();
include 'conexion.php';

// Seguridad: Solo los administradores pueden acceder
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: login.php');
    exit();
}

// Determinar el título de la página según el filtro de la URL
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'aprobados';
$titulo = 'Lista de Usuarios';

switch ($filtro) {
    case 'pendientes': $titulo = 'Usuarios Pendientes de Aprobación'; break;
    case 'personal': $titulo = 'Personal Activo'; break;
    case 'doctores': $titulo = 'Doctores Activos'; break;
    case 'aprobados': $titulo = 'Todos los Usuarios Aprobados'; break;
    case 'pacientes': $titulo = 'Pacientes Activos'; break;
    default:
        $titulo = 'Todos los Usuarios Aprobados';
        break;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($titulo); ?> - WebPSY</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; margin: 0; padding: 30px; }
    .main-container { max-width: 1200px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    h1 { margin-top: 0; }
    .back-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #555; font-weight: 500; }
    .search-container { position: relative; margin: 25px 0; }
    .search-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
    .search-container input { width: 100%; padding: 12px 20px 12px 45px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
    .users-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .users-table th, .users-table td { padding: 15px; text-align: left; border-bottom: 1px solid #e9e9e9; }
    .users-table th { background-color: #fafafa; font-weight: 600; color: #555; text-transform: uppercase; font-size: 12px; }
    .users-table tr:hover { background-color: #f7f7f7; }
    .status-badge { padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 500; color: white; }
    .status-aprobado { background-color: #28a745; }
    .status-inhabilitado { background-color: #6c757d; }
    .status-pendiente { background-color: #ffc107; color: #333; }
    .action-links a, .action-links button { display: inline-block; padding: 6px 14px; font-size: 13px; font-weight: 500; text-align: center; border-radius: 6px; cursor: pointer; text-decoration: none !important; min-width: 0px; border: 2px solid; background-color: transparent; transition: all 0.2s ease-in-out; margin-right: 8px; font-family: "Poppins", sans-serif; }
    .action-links a:last-child, .action-links button:last-child { margin-right: 0; }
    .action-links a:hover, .action-links button:hover { color: white !important; transform: translateY(-2px); }
    .action-links .approve { border-color: #02b1f4; color: #02b1f4 !important; }
    .action-links .approve:hover { background-color: #02b1f4; }
    .action-links .reject { border-color: #dc3545; color: #dc3545 !important; }
    .action-links .reject:hover { background-color: #dc3545; }

    /* --- ESTILOS PARA LA TABLA ORDENABLE --- */
    .sortable-header {
        cursor: pointer;
        position: relative;
        user-select: none;
    }
    .sortable-header::after {
        content: ' \2195'; /* Flecha arriba y abajo */
        font-size: 14px;
        color: #ccc;
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
    }
    .sortable-header.sort-asc::after {
        content: ' \25B2'; /* Flecha hacia arriba */
        color: #02b1f4;
    }
    .sortable-header.sort-desc::after {
        content: ' \25BC'; /* Flecha hacia abajo */
        color: #02b1f4;
    }

    /* --- AJUSTE DE ANCHO PARA LA COLUMNA DE ACCIONES --- */

/* Le damos un ancho fijo a la columna de Acciones */
.users-table th:last-child,
.users-table td:last-child {
    width: 200px; /* <-- Puedes ajustar este valor si es necesario */
    text-align: left;
}

/* Evita que los botones se apilen */
.action-links {
    white-space: nowrap;
}

/* --- ESTILO PARA BOTÓN SECUNDARIO (REESTABLECER) - GRIS --- */
.action-links a.btn-secondary {
    border-color: #6c757d;
    color: #6c757d !important;
}
.action-links a.btn-secondary:hover {
    background-color: #6c757d;
    color: white !important;
}

/* --- ESTILOS PARA LA VENTANA MODAL DE ÉXITO --- */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: flex; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background-color: white; padding: 30px 40px; border-radius: 12px; width: 90%; max-width: 450px; text-align: center; }
        .modal-icon { font-size: 50px; color: #28a745; margin-bottom: 15px; }
        .modal-content h3 { margin: 0 0 10px 0; color: #333; }
        .modal-content p { color: #555; margin-bottom: 25px; }
        .temp-password-box { background-color: #e9ecef; border: 1px dashed #ced4da; border-radius: 8px; padding: 15px; margin: 10px 0; }
        .temp-password-box span { font-family: 'Courier New', Courier, monospace; font-size: 20px; font-weight: 600; color: #333; letter-spacing: 2px; }
        .modal-btn { display: inline-block; padding: 10px 30px; border-radius: 6px; border: none; background-color: #02b1f4; color: white; font-weight: 500; cursor: pointer; }
</style>
</head>
<body>
    <div class="main-container">
        <a href="panel.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Volver al Panel</a>
        <h1><?php echo htmlspecialchars($titulo); ?></h1>
        <div class="search-container">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="buscador-usuarios" placeholder="Buscar por nombre o cédula...">
        </div>
        <div id="tabla-usuarios-container">
            <!-- La tabla se cargará aquí por JavaScript -->
        </div>
    </div>

    <!-- HTML DE LA VENTANA MODAL DE ÉXITO -->
    <div id="success-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-icon"><i class="fa-solid fa-check-circle"></i></div>
            <h3>¡Contraseña Restablecida!</h3>
            <p>La nueva contraseña temporal para el usuario es:</p>
            <div class="temp-password-box">
                <span id="temp-password-display"></span>
            </div>
            <p style="font-size: 14px; color: #777; margin-top: 15px;">Por favor, anota esta contraseña y entrégasela al usuario.</p>
            <button class="modal-btn" onclick="document.getElementById('success-modal').style.display='none'">Entendido</button>
        </div>
    </div>

    <script>
    // --- NUEVA LÓGICA PARA MOSTRAR LA MODAL ---
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tempPass = urlParams.get('temp_pass');

        if (tempPass) {
            const modal = document.getElementById('success-modal');
            const passwordDisplay = document.getElementById('temp-password-display');
            
            passwordDisplay.textContent = tempPass;
            modal.style.display = 'flex';

            // Limpiar la URL para que la modal no reaparezca al recargar
            const newUrl = window.location.pathname + '?filtro=' + urlParams.get('filtro');
            history.replaceState(null, '', newUrl);
        }

        const buscador = document.getElementById('buscador-usuarios');
        const contenedorTabla = document.getElementById('tabla-usuarios-container');
        const filtroActual = '<?php echo htmlspecialchars($filtro, ENT_QUOTES, 'UTF-8'); ?>';

        // Función para buscar y refrescar la tabla
        window.buscarUsuarios = function(query) {
            const formData = new FormData();
            formData.append('query', query);
            formData.append('filtro', filtroActual);

            fetch('buscar_usuarios_filtro.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                contenedorTabla.innerHTML = data;
            })
            .catch(error => console.error('Error en la búsqueda:', error));
        }

        // Carga inicial de la tabla
        buscarUsuarios('');

        // Búsqueda en tiempo real al escribir
        buscador.addEventListener('keyup', function() {
            buscarUsuarios(this.value);
        });

        // --- LÓGICA PARA ORDENAR LA TABLA (DELEGACIÓN DE EVENTOS) ---
        contenedorTabla.addEventListener('click', function(event) {
            const th = event.target.closest('.sortable-header');
            if (!th) return;

            const table = th.closest('table');
            const tbody = table.querySelector('tbody');
            const columnIndex = Array.from(th.parentNode.children).indexOf(th);
            const isAsc = !th.classList.contains('sort-asc');

            table.querySelectorAll('.sortable-header').forEach(header => {
                if (header !== th) {
                    header.classList.remove('sort-asc', 'sort-desc');
                }
            });

            th.classList.toggle('sort-asc', isAsc);
            th.classList.toggle('sort-desc', !isAsc);

            const getCellValue = (tr, idx) => tr.children[idx].innerText || tr.children[idx].textContent;
            const comparer = (idx, asc) => (a, b) => {
                const vA = getCellValue(asc ? a : b, idx);
                const vB = getCellValue(asc ? b : a, idx);
                return vA.toString().localeCompare(vB, 'es', {numeric: true});
            };

            Array.from(tbody.querySelectorAll('tr'))
                .sort(comparer(columnIndex, isAsc))
                .forEach(tr => tbody.appendChild(tr));
        });
    });

    // --- FUNCIÓN PARA HABILITAR/INHABILITAR USUARIOS (ADMIN) ---
    function toggleUserState(userId, newState) {
        const confirmationMessage = newState === 'inhabilitado' 
            ? '¿Seguro que quieres inhabilitar a este usuario?' 
            : '¿Seguro que quieres habilitar a este usuario?';

        if (confirm(confirmationMessage)) {
            const formData = new FormData();
            formData.append('id', userId);
            formData.append('nuevo_estado', newState);

            fetch('cambiar_estado_usuario.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Si la operación fue exitosa, refrescamos la tabla para ver el cambio
                    const currentSearch = document.getElementById('buscador-usuarios').value;
                    window.buscarUsuarios(currentSearch);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => console.error('Error al cambiar estado:', error));
        }
    }

    

</script>
</body>
</html>