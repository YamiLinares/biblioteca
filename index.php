<?php
declare(strict_types=1);

$sessionDirectory = __DIR__ . '/runtime/sessions';
if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
    throw new RuntimeException('No se pudo preparar la sesión local de la aplicación.');
}
session_save_path($sessionDirectory);
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'path' => '/',
]);
session_start();
require_once __DIR__ . '/classes/Biblioteca.php';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function idFromPost(string $key): int
{
    $id = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) throw new InvalidArgumentException('El identificador enviado no es válido.');
    return $id;
}

function icon(string $name, string $class = ''): string
{
    $paths = [
        'grid' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.4"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.4"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.4"/>',
        'book' => '<path d="M5 4.5h11.5A2.5 2.5 0 0 1 19 7v13H7a3 3 0 0 1-3-3V6a1.5 1.5 0 0 1 1-1.5Z"/><path d="M4 16.5A3 3 0 0 1 7 14h12M8 8h7m-7 3h5"/>',
        'users' => '<path d="M16 20v-1.5a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4V20"/><circle cx="10" cy="7" r="3.5"/><path d="M16 4.2a3.5 3.5 0 0 1 0 6.7M17 14.7a4 4 0 0 1 3 3.8V20"/>',
        'arrows' => '<path d="M7 7h12l-3-3m3 3-3 3M17 17H5l3 3m-3-3 3-3"/>',
        'search' => '<circle cx="10.8" cy="10.8" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'edit' => '<path d="m15 5 4 4M4 20l4.3-.8L19 8.5a2.1 2.1 0 0 0-3-3L5.3 16.2 4 20Z"/>',
        'trash' => '<path d="M4 7h16m-10 4v5m4-5v5M6.5 7l1 13h9l1-13M9 7V4h6v3"/>',
        'check' => '<path d="m5 12 4.5 4.5L19 7"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'spark' => '<path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3ZM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8L19 16Z"/>',
    ];
    return '<svg class="icon ' . h($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$section = (string) ($_GET['seccion'] ?? 'libros');
if (!in_array($section, ['libros', 'usuarios', 'prestamos'], true)) $section = 'libros';
$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);
$errorMessage = null;
$library = null;

try {
    $library = new Biblioteca();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string) ($_POST['csrf'] ?? '');
        if (!hash_equals((string) $_SESSION['csrf'], $token)) throw new RuntimeException('La sesión del formulario venció. Recarga la página e inténtalo nuevamente.');
        switch ((string) ($_POST['action'] ?? '')) {
            case 'crear_libro':
                $cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT);
                $library->agregarLibro(new Libro((string) ($_POST['titulo'] ?? ''), (string) ($_POST['autor'] ?? ''), (string) ($_POST['isbn'] ?? ''), $cantidad === false ? -1 : (int) ($cantidad ?? 1)));
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'El título ya forma parte de la colección.']; $section = 'libros'; break;
            case 'editar_libro':
                $library->editarLibro(idFromPost('id'), $_POST);
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Los cambios del libro se guardaron.']; $section = 'libros'; break;
            case 'eliminar_libro':
                if (!$library->eliminarLibro(idFromPost('id'))) throw new RuntimeException('El libro ya no existe.');
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Libro eliminado de la colección.']; $section = 'libros'; break;
            case 'crear_usuario':
                $library->agregarUsuario(new Usuario((string) ($_POST['nombre'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['telefono'] ?? '')));
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'El lector se registró correctamente.']; $section = 'usuarios'; break;
            case 'editar_usuario':
                $library->editarUsuario(idFromPost('id'), $_POST);
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Los datos del lector se actualizaron.']; $section = 'usuarios'; break;
            case 'eliminar_usuario':
                if (!$library->eliminarUsuario(idFromPost('id'))) throw new RuntimeException('El lector ya no existe.');
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Lector eliminado del directorio.']; $section = 'usuarios'; break;
            case 'prestar':
                $library->prestarLibro(idFromPost('libro_id'), idFromPost('usuario_id'));
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Préstamo registrado. La disponibilidad ya está actualizada.']; $section = 'prestamos'; break;
            case 'devolver':
                $library->devolverLibro(idFromPost('prestamo_id'));
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Devolución registrada. El ejemplar volvió a estar disponible.']; $section = 'prestamos'; break;
            default: throw new InvalidArgumentException('La acción solicitada no es válida.');
        }
        header('Location: index.php?seccion=' . urlencode($section)); exit;
    }
} catch (Throwable $exception) {
    if ($exception instanceof PDOException && $exception->getCode() === '23000') $errorMessage = 'El ISBN o el correo ya está registrado. Verifica los datos ingresados.';
    else $errorMessage = $exception instanceof PDOException ? 'No se pudo completar la operación. Revisa la conexión y la configuración local.' : $exception->getMessage();
}

$editingBook = null;
$editingUser = null;
if ($library && isset($_GET['editar_libro'])) {
    $editingBook = $library->buscarLibro((int) $_GET['editar_libro']);
    if (!$editingBook) $errorMessage = 'El libro solicitado ya no existe.';
    else $section = 'libros';
}
if ($library && isset($_GET['editar_usuario'])) {
    $editingUser = $library->buscarUsuario((int) $_GET['editar_usuario']);
    if (!$editingUser) $errorMessage = 'El lector solicitado ya no existe.';
    else $section = 'usuarios';
}
$books = $users = $loans = $summary = [];
if ($library) {
    try {
        $books = $library->obtenerLibros();
        $users = $library->obtenerUsuarios();
        $loans = $library->obtenerPrestamos();
        $summary = $library->obtenerResumen();
    } catch (Throwable) {
        $errorMessage = 'No se pudieron cargar los datos. Comprueba la base de datos y su configuración.';
    }
}
$activeLoans = array_filter($loans, static fn(array $loan): bool => $loan['estado'] === 'activo');
$returnedLoans = array_filter($loans, static fn(array $loan): bool => $loan['estado'] === 'devuelto');
$csrf = (string) $_SESSION['csrf'];
$bookModalOpen = $editingBook !== null;
$userModalOpen = $editingUser !== null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#124c3d">
    <meta name="description" content="Panel de gestión para la colección, lectores y circulación de la biblioteca.">
    <title><?= $section === 'libros' ? 'Colección' : ($section === 'usuarios' ? 'Lectores' : 'Circulación') ?> · Folio</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/styles.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php" aria-label="Folio, ir a la colección">
            <span class="brand-symbol"><svg viewBox="0 0 40 40" aria-hidden="true"><path d="M9 9.5c4-2 8-1.8 11 1v20c-3-2.8-7-3-11-1v-20Zm22 0c-4-2-8-1.8-11 1v20c3-2.8 7-3 11-1v-20Z"/><path d="M13 15h3m-3 4h3m8-4h3m-3 4h3"/></svg></span>
            <span class="brand-copy"><strong>folio</strong><small>gestión bibliotecaria</small></span>
        </a>
        <div class="sidebar-label">ESPACIO DE TRABAJO</div>
        <nav class="side-nav" aria-label="Navegación principal">
            <a class="nav-link <?= $section === 'libros' ? 'is-active' : '' ?>" href="index.php?seccion=libros" <?= $section === 'libros' ? 'aria-current="page"' : '' ?>><?= icon('grid') ?><span>Resumen y colección</span><?php if ($section === 'libros'): ?><i></i><?php endif; ?></a>
            <a class="nav-link <?= $section === 'usuarios' ? 'is-active' : '' ?>" href="index.php?seccion=usuarios" <?= $section === 'usuarios' ? 'aria-current="page"' : '' ?>><?= icon('users') ?><span>Lectores</span><?php if ($section === 'usuarios'): ?><i></i><?php endif; ?></a>
            <a class="nav-link <?= $section === 'prestamos' ? 'is-active' : '' ?>" href="index.php?seccion=prestamos" <?= $section === 'prestamos' ? 'aria-current="page"' : '' ?>><?= icon('arrows') ?><span>Circulación</span><?php if ($section === 'prestamos'): ?><i></i><?php endif; ?></a>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-note"><span class="note-icon"><?= icon('spark') ?></span><p><strong>Un buen libro</strong><br>siempre encuentra lector.</p></div>
            <div class="workspace"><span class="workspace-avatar">B</span><span><strong>Biblioteca</strong><small>Espacio local</small></span><span class="workspace-dots">···</span></div>
        </div>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <button class="mobile-menu" type="button" data-menu-toggle aria-label="Abrir menú" aria-expanded="false"><?= icon('grid') ?></button>
            <div class="breadcrumb"><span>Biblioteca</span><b>/</b><strong><?= $section === 'libros' ? 'Colección' : ($section === 'usuarios' ? 'Lectores' : 'Circulación') ?></strong></div>
            <div class="topbar-right"><span class="today"><span class="status-dot"></span> Sistema en línea</span><span class="top-divider"></span><span class="today-date"><?= h(date('d M Y')) ?></span><span class="top-avatar">B</span></div>
        </header>

        <div class="page-content">
            <?php if ($notice): ?><div class="toast toast-success" role="status"><span class="toast-icon"><?= icon('check') ?></span><span><?= h($notice['text']) ?></span><button type="button" class="toast-close" aria-label="Cerrar aviso" data-dismiss><?= icon('close') ?></button></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="toast toast-error" role="alert"><span class="toast-icon"><?= icon('close') ?></span><span><?= h($errorMessage) ?></span><button type="button" class="toast-close" aria-label="Cerrar aviso" data-dismiss><?= icon('close') ?></button></div><?php endif; ?>

            <?php if (!$library): ?>
                <section class="setup-panel"><p class="eyebrow">CONFIGURACIÓN INICIAL</p><h1>Prepara tu biblioteca</h1><p>Importa <code>biblioteca.sql</code> y crea <code>config.local.php</code> a partir del archivo de ejemplo para conectar la base de datos.</p></section>
            <?php else: ?>
                <section class="page-intro">
                    <div><p class="eyebrow"><?= $section === 'libros' ? 'UN LUGAR PARA CADA HISTORIA' : ($section === 'usuarios' ? 'COMUNIDAD LECTORA' : 'MOVIMIENTO DE LA COLECCIÓN') ?></p>
                        <h1><?= $section === 'libros' ? 'Tu biblioteca, en orden.' : ($section === 'usuarios' ? 'Lectores' : 'Circulación') ?></h1>
                        <p class="page-subtitle"><?= $section === 'libros' ? 'Una mirada clara a los títulos y a su disponibilidad.' : ($section === 'usuarios' ? 'Personas que dan vida a cada historia.' : 'Préstamos y devoluciones, siempre al día.') ?></p></div>
                    <?php if ($section === 'libros'): ?><button class="button button-dark" type="button" data-open-dialog="bookDialog"><?= icon('plus') ?><span>Agregar título</span></button>
                    <?php elseif ($section === 'usuarios'): ?><button class="button button-dark" type="button" data-open-dialog="userDialog"><?= icon('plus') ?><span>Nuevo lector</span></button><?php endif; ?>
                </section>

                <section class="metric-strip" aria-label="Indicadores de la biblioteca">
                    <article class="metric metric-featured"><div class="metric-icon"><?= icon('book') ?></div><div><span>Títulos en catálogo</span><strong><?= (int) ($summary['libros'] ?? 0) ?></strong><small>obras registradas</small></div><span class="metric-index">01</span></article>
                    <article class="metric"><div class="metric-icon metric-icon-sage"><?= icon('users') ?></div><div><span>Lectores</span><strong><?= (int) ($summary['usuarios'] ?? 0) ?></strong><small>en el directorio</small></div><span class="metric-index">02</span></article>
                    <article class="metric"><div class="metric-icon metric-icon-gold"><?= icon('arrows') ?></div><div><span>En circulación</span><strong><?= (int) ($summary['activos'] ?? 0) ?></strong><small>préstamos activos</small></div><span class="metric-index">03</span></article>
                    <article class="metric"><div class="metric-icon metric-icon-terra"><?= icon('check') ?></div><div><span>Devueltos</span><strong><?= count($returnedLoans) ?></strong><small>devoluciones registradas</small></div><span class="metric-index">04</span></article>
                </section>

                <?php if ($section === 'libros'): ?>
                    <section class="section-heading" id="catalogo"><div><p class="eyebrow">CATÁLOGO</p><h2>Entre las estanterías</h2><p>Consulta la colección y encuentra el siguiente libro para compartir.</p></div><div class="catalog-tools"><label class="search-field"><?= icon('search') ?><input type="search" placeholder="Buscar título o autor" aria-label="Buscar título o autor" data-table-search="bookGrid"></label><span class="result-count"><?= count($books) ?> títulos</span></div></section>
                    <?php if (!$books): ?><div class="empty-state"><span class="empty-ornament"><?= icon('book') ?></span><h3>La estantería está lista.</h3><p>Agrega el primer título para comenzar tu colección.</p><button class="button button-outline" type="button" data-open-dialog="bookDialog"><?= icon('plus') ?>Agregar título</button></div>
                    <?php else: ?><div class="book-grid" id="bookGrid">
                        <?php foreach ($books as $book): $coverVariant = ((int) $book['id'] % 5) + 1; ?>
                            <article class="book-card" data-searchable="<?= h(mb_strtolower($book['titulo'] . ' ' . $book['autor'], 'UTF-8')) ?>">
                                <div class="cover cover-<?= $coverVariant ?>" aria-label="Ilustración editorial para <?= h($book['titulo']) ?>" role="img"><span class="cover-rule"></span><span class="cover-kicker">FOLIO · COLECCIÓN</span><span class="cover-art" aria-hidden="true"><i></i><b></b><em></em></span><span class="cover-wordmark">F</span></div>
                                <div class="book-card-body"><div class="book-meta"><span class="availability <?= (int) $book['cantidad'] > 0 ? 'is-available' : 'is-unavailable' ?>"><i></i><?= (int) $book['cantidad'] > 0 ? 'Disponible' : 'Agotado' ?></span><span class="book-stock"><?= (int) $book['cantidad'] ?> ej.</span></div><h3><?= h($book['titulo']) ?></h3><p class="book-author"><?= h($book['autor']) ?></p><div class="book-card-footer"><span class="isbn-label"><?= h($book['isbn'] ?: 'Sin ISBN') ?></span><div class="icon-actions"><a class="icon-button" href="index.php?editar_libro=<?= (int) $book['id'] ?>" aria-label="Editar <?= h($book['titulo']) ?>" title="Editar"><?= icon('edit') ?></a><form method="post" action="index.php?seccion=libros" data-confirm="¿Quitar este título de la colección?" data-confirm-detail="El historial de préstamos se conserva y puede impedir que se borre."><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="eliminar_libro"><input type="hidden" name="id" value="<?= (int) $book['id'] ?>"><button class="icon-button icon-danger" type="submit" aria-label="Eliminar <?= h($book['titulo']) ?>" title="Eliminar"><?= icon('trash') ?></button></form></div></div></div>
                            </article>
                        <?php endforeach; ?>
                    </div><p class="no-results" data-empty-search="bookGrid" hidden>No encontramos títulos con esa búsqueda.</p><?php endif; ?>

                <?php elseif ($section === 'usuarios'): ?>
                    <section class="section-heading"><div><p class="eyebrow">DIRECTORIO</p><h2>La comunidad lectora</h2><p>Administra los datos de contacto de las personas registradas.</p></div><div class="catalog-tools"><label class="search-field"><?= icon('search') ?><input type="search" placeholder="Buscar lector" aria-label="Buscar lector" data-table-search="userTable"></label><span class="result-count"><?= count($users) ?> lectores</span></div></section>
                    <section class="table-panel"><div class="table-topline"><div><span class="table-kicker">DIRECTORIO DE LECTORES</span><strong><?= count($users) ?> personas</strong></div><span class="table-note">Datos del registro local</span></div>
                    <?php if (!$users): ?><div class="empty-state compact"><h3>Aún no hay lectores registrados.</h3><p>Agrega una persona para comenzar a gestionar sus préstamos.</p><button class="button button-outline" type="button" data-open-dialog="userDialog"><?= icon('plus') ?>Nuevo lector</button></div>
                    <?php else: ?><div class="table-wrap"><table id="userTable"><thead><tr><th>Lector</th><th>Correo electrónico</th><th>Teléfono</th><th>Registro</th><th class="align-right">Acciones</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr data-searchable="<?= h(mb_strtolower($user['nombre'] . ' ' . $user['email'] . ' ' . ($user['telefono'] ?? ''), 'UTF-8')) ?>"><td><div class="person-cell"><span class="person-avatar"><?= h(mb_strtoupper(mb_substr($user['nombre'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span><strong><?= h($user['nombre']) ?></strong></div></td><td><?= h($user['email']) ?></td><td><?= h($user['telefono'] ?: '—') ?></td><td><?= h(date('d/m/Y', strtotime($user['created_at']))) ?></td><td class="align-right"><div class="icon-actions"><a class="icon-button" href="index.php?editar_usuario=<?= (int) $user['id'] ?>" aria-label="Editar <?= h($user['nombre']) ?>" title="Editar"><?= icon('edit') ?></a><form method="post" action="index.php?seccion=usuarios" data-confirm="¿Eliminar este lector?" data-confirm-detail="Si tiene préstamos en su historial, se conservará su registro."><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="eliminar_usuario"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button class="icon-button icon-danger" type="submit" aria-label="Eliminar <?= h($user['nombre']) ?>" title="Eliminar"><?= icon('trash') ?></button></form></div></td></tr><?php endforeach; ?></tbody></table></div><p class="no-results" data-empty-search="userTable" hidden>No encontramos lectores con esa búsqueda.</p><?php endif; ?></section>

                <?php else: ?>
                    <section class="circulation-intro"><div class="circulation-mark"><?= icon('arrows') ?></div><div><p class="eyebrow">PRÉSTAMOS Y DEVOLUCIONES</p><h2>Historias en movimiento</h2><p>Registra un préstamo o revisa el recorrido de cada ejemplar.</p></div><span class="circulation-total"><strong><?= count($activeLoans) ?></strong><small>en circulación hoy</small></span></section>
                    <section class="circulation-layout"><article class="loan-form-panel"><div class="form-panel-heading"><span class="form-index">01 / NUEVO MOVIMIENTO</span><h3>Prestar un ejemplar</h3><p>Elige un título disponible y asígnalo a un lector.</p></div><form method="post" action="index.php?seccion=prestamos" class="form-stack"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="prestar"><label>Ejemplar<select name="libro_id" required><option value="">Selecciona un título</option><?php foreach ($books as $book): if ((int) $book['cantidad'] > 0): ?><option value="<?= (int) $book['id'] ?>"><?= h($book['titulo']) ?> · <?= (int) $book['cantidad'] ?> disponibles</option><?php endif; endforeach; ?></select></label><label>Lector<select name="usuario_id" required><option value="">Selecciona una persona</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= h($user['nombre']) ?> · <?= h($user['email']) ?></option><?php endforeach; ?></select></label><button class="button button-dark full-button" type="submit" <?= (!$books || !$users || count(array_filter($books, static fn(array $book): bool => (int) $book['cantidad'] > 0)) === 0) ? 'disabled' : '' ?>><?= icon('arrow') ?><span>Registrar préstamo</span></button></form><div class="transaction-note"><?= icon('check') ?><span>El inventario se actualiza al confirmar el movimiento.</span></div></article>
                    <article class="table-panel loan-history"><div class="table-topline"><div><span class="table-kicker">REGISTRO DE CIRCULACIÓN</span><strong>Actividad reciente</strong></div><label class="search-field small-search"><?= icon('search') ?><input type="search" placeholder="Buscar movimiento" aria-label="Buscar movimiento" data-table-search="loanTable"></label></div>
                    <?php if (!$loans): ?><div class="empty-state compact"><h3>Todo está en su sitio.</h3><p>Los préstamos aparecerán aquí cuando registres una salida.</p></div>
                    <?php else: ?><div class="table-wrap"><table id="loanTable"><thead><tr><th>Ejemplar / lector</th><th>Fecha de salida</th><th>Fecha de retorno</th><th>Estado</th><th class="align-right">Acción</th></tr></thead><tbody><?php foreach ($loans as $loan): ?><tr data-searchable="<?= h(mb_strtolower($loan['titulo'] . ' ' . $loan['nombre'] . ' ' . $loan['email'], 'UTF-8')) ?>"><td><div class="loan-book-cell"><span class="mini-book"><?= icon('book') ?></span><span><strong><?= h($loan['titulo']) ?></strong><small><?= h($loan['nombre']) ?> · <?= h($loan['email']) ?></small></span></div></td><td><?= h(date('d M Y', strtotime($loan['fecha_prestamo']))) ?></td><td><?= $loan['fecha_devolucion'] ? h(date('d M Y', strtotime($loan['fecha_devolucion']))) : '<span class="muted">Pendiente</span>' ?></td><td><span class="loan-status <?= $loan['estado'] === 'activo' ? 'status-active' : 'status-returned' ?>"><i></i><?= $loan['estado'] === 'activo' ? 'En préstamo' : 'Devuelto' ?></span></td><td class="align-right"><?php if ($loan['estado'] === 'activo'): ?><form method="post" action="index.php?seccion=prestamos" data-confirm="¿Registrar la devolución?" data-confirm-detail="El ejemplar volverá al inventario disponible."><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="devolver"><input type="hidden" name="prestamo_id" value="<?= (int) $loan['id'] ?>"><button class="return-button" type="submit"><?= icon('check') ?><span>Devolver</span></button></form><?php else: ?><span class="returned-label">Completado</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><p class="no-results" data-empty-search="loanTable" hidden>No encontramos movimientos con esa búsqueda.</p><?php endif; ?></article></section>
                <?php endif; ?>
            <?php endif; ?>
            <footer class="page-footer"><span>Folio <i>·</i> Gestión de biblioteca</span><span>Una colección para compartir.</span></footer>
        </div>
    </main>
</div>

<dialog class="modal" id="bookDialog" <?= $bookModalOpen ? 'open' : '' ?> aria-labelledby="bookModalTitle"><form method="dialog" class="modal-dismiss"><button class="modal-close" aria-label="Cerrar" value="close" formnovalidate><?= icon('close') ?></button></form><div class="modal-heading"><span class="modal-kicker">CATÁLOGO · <?= $editingBook ? 'EDITAR' : 'NUEVO REGISTRO' ?></span><h2 id="bookModalTitle"><?= $editingBook ? 'Actualizar título' : 'Agregar a la colección' ?></h2><p>Los campos con asterisco son obligatorios.</p></div><form method="post" action="index.php?seccion=libros" class="form-stack modal-form"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="<?= $editingBook ? 'editar_libro' : 'crear_libro' ?>"><?php if ($editingBook): ?><input type="hidden" name="id" value="<?= (int) $editingBook['id'] ?>"><?php endif; ?><label>Título <span>*</span><input name="titulo" maxlength="255" required autofocus value="<?= h($editingBook['titulo'] ?? '') ?>" placeholder="Ej. El jardín de los senderos que se bifurcan"></label><div class="field-row"><label>Autor <span>*</span><input name="autor" maxlength="255" required value="<?= h($editingBook['autor'] ?? '') ?>" placeholder="Nombre de quien escribe"></label><label>ISBN <small>OPCIONAL</small><input name="isbn" maxlength="20" value="<?= h($editingBook['isbn'] ?? '') ?>" placeholder="978-..."></label></div><label>Ejemplares disponibles <span>*</span><input type="number" name="cantidad" min="0" required value="<?= h($editingBook['cantidad'] ?? 1) ?>"></label><div class="modal-actions"><button class="button button-quiet" type="button" data-close-dialog>Cancelar</button><button class="button button-dark" type="submit"><?= $editingBook ? 'Guardar cambios' : 'Registrar título' ?></button></div></form></dialog>

<dialog class="modal" id="userDialog" <?= $userModalOpen ? 'open' : '' ?> aria-labelledby="userModalTitle"><form method="dialog" class="modal-dismiss"><button class="modal-close" aria-label="Cerrar" value="close" formnovalidate><?= icon('close') ?></button></form><div class="modal-heading"><span class="modal-kicker">DIRECTORIO · <?= $editingUser ? 'EDITAR' : 'NUEVA PERSONA' ?></span><h2 id="userModalTitle"><?= $editingUser ? 'Actualizar lector' : 'Agregar lector' ?></h2><p>Registra los datos para gestionar los préstamos.</p></div><form method="post" action="index.php?seccion=usuarios" class="form-stack modal-form"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="<?= $editingUser ? 'editar_usuario' : 'crear_usuario' ?>"><?php if ($editingUser): ?><input type="hidden" name="id" value="<?= (int) $editingUser['id'] ?>"><?php endif; ?><label>Nombre completo <span>*</span><input name="nombre" maxlength="255" required autofocus value="<?= h($editingUser['nombre'] ?? '') ?>" placeholder="Nombre y apellido"></label><label>Correo electrónico <span>*</span><input type="email" name="email" maxlength="255" required value="<?= h($editingUser['email'] ?? '') ?>" placeholder="nombre@correo.com"></label><label>Teléfono <small>OPCIONAL</small><input name="telefono" maxlength="20" value="<?= h($editingUser['telefono'] ?? '') ?>" placeholder="0000-0000"></label><div class="modal-actions"><button class="button button-quiet" type="button" data-close-dialog>Cancelar</button><button class="button button-dark" type="submit"><?= $editingUser ? 'Guardar cambios' : 'Registrar lector' ?></button></div></form></dialog>

<dialog class="confirm-modal" id="confirmDialog" aria-labelledby="confirmTitle"><div class="confirm-mark"><?= icon('trash') ?></div><div class="confirm-content"><span class="modal-kicker">CONFIRMACIÓN</span><h2 id="confirmTitle">¿Continuar con esta acción?</h2><p id="confirmDetail">Esta acción se aplicará al registro seleccionado.</p><div class="modal-actions"><button class="button button-quiet" type="button" data-cancel-confirm>Cancelar</button><button class="button button-danger" type="button" data-accept-confirm>Confirmar</button></div></div></dialog>
</body>
</html>
