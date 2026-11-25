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

function h(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function idFromPost(string $key): int
{
    $id = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) throw new InvalidArgumentException('El identificador enviado no es válido.');
    return $id;
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
        $action = (string) ($_POST['action'] ?? '');
        switch ($action) {
            case 'crear_libro':
                $library->agregarLibro(new Libro((string) ($_POST['titulo'] ?? ''), (string) ($_POST['autor'] ?? ''), (string) ($_POST['isbn'] ?? ''), filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT) === false ? -1 : (int) ($_POST['cantidad'] ?? 1)));
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Libro registrado correctamente.']; $section = 'libros'; break;
            case 'editar_libro':
                $library->editarLibro(idFromPost('id'), $_POST);
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Libro actualizado correctamente.']; $section = 'libros'; break;
            case 'eliminar_libro':
                if (!$library->eliminarLibro(idFromPost('id'))) throw new RuntimeException('El libro ya no existe.');
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Libro eliminado.']; $section = 'libros'; break;
            case 'crear_usuario':
                $library->agregarUsuario(new Usuario((string) ($_POST['nombre'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['telefono'] ?? '')));
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Usuario registrado correctamente.']; $section = 'usuarios'; break;
            case 'editar_usuario':
                $library->editarUsuario(idFromPost('id'), $_POST);
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Usuario actualizado correctamente.']; $section = 'usuarios'; break;
            case 'eliminar_usuario':
                if (!$library->eliminarUsuario(idFromPost('id'))) throw new RuntimeException('El usuario ya no existe.');
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Usuario eliminado.']; $section = 'usuarios'; break;
            case 'prestar':
                $library->prestarLibro(idFromPost('libro_id'), idFromPost('usuario_id'));
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Préstamo registrado y disponibilidad actualizada.']; $section = 'prestamos'; break;
            case 'devolver':
                $library->devolverLibro(idFromPost('prestamo_id'));
                $_SESSION['notice'] = ['type' => 'success', 'text' => 'Devolución registrada correctamente.']; $section = 'prestamos'; break;
            default: throw new InvalidArgumentException('La acción solicitada no es válida.');
        }
        header('Location: index.php?seccion=' . urlencode($section)); exit;
    }
} catch (Throwable $exception) {
    if ($exception instanceof PDOException && $exception->getCode() === '23000') $errorMessage = 'El ISBN o correo ya está registrado. Verifica los datos ingresados.';
    else $errorMessage = $exception instanceof PDOException ? 'No se pudo completar la operación. Revisa la configuración y la conexión a la base de datos.' : $exception->getMessage();
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
    if (!$editingUser) $errorMessage = 'El usuario solicitado ya no existe.';
    else $section = 'usuarios';
}
$books = $users = $loans = $summary = [];
if ($library) {
    try { $books = $library->obtenerLibros(); $users = $library->obtenerUsuarios(); $loans = $library->obtenerPrestamos(); $summary = $library->obtenerResumen(); }
    catch (Throwable $exception) { $errorMessage = 'No se pudieron cargar los datos. Comprueba la base de datos y su configuración.'; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f3ed">
    <title>Biblioteca | Gestión</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<header class="topbar"><a class="brand" href="index.php"><span class="brand-mark">B</span><span>Biblioteca<small>Gestión de colección</small></span></a><span class="top-note">Administración local</span></header>
<main class="shell">
    <section class="page-heading"><div><p class="eyebrow">SISTEMA DE GESTIÓN</p><h1>Biblioteca</h1><p class="intro">Control de libros, lectores y circulación en un solo lugar.</p></div><span class="date-label"><?= h(date('d/m/Y')) ?></span></section>
    <?php if ($notice): ?><div class="notice <?= h($notice['type']) ?>" role="status"><?= h($notice['text']) ?></div><?php endif; ?>
    <?php if ($errorMessage): ?><div class="notice error" role="alert"><?= h($errorMessage) ?></div><?php endif; ?>
    <?php if (!$library): ?><section class="panel setup"><h2>Configuración requerida</h2><p>Prepara la base de datos con <code>biblioteca.sql</code> y crea <code>config.local.php</code> a partir de <code>config.example.php</code>. Los datos de conexión se mantienen en ese archivo local ignorado por Git.</p></section><?php else: ?>
    <section class="stats" aria-label="Resumen">
        <div class="stat"><span>Catálogo</span><strong><?= (int) ($summary['libros'] ?? 0) ?></strong><small>títulos registrados</small></div>
        <div class="stat"><span>Lectores</span><strong><?= (int) ($summary['usuarios'] ?? 0) ?></strong><small>usuarios registrados</small></div>
        <div class="stat"><span>En circulación</span><strong><?= (int) ($summary['activos'] ?? 0) ?></strong><small>préstamos activos</small></div>
    </section>
    <nav class="tabs" aria-label="Secciones">
        <a class="<?= $section === 'libros' ? 'active' : '' ?>" href="index.php?seccion=libros">Libros <span><?= count($books) ?></span></a>
        <a class="<?= $section === 'usuarios' ? 'active' : '' ?>" href="index.php?seccion=usuarios">Usuarios <span><?= count($users) ?></span></a>
        <a class="<?= $section === 'prestamos' ? 'active' : '' ?>" href="index.php?seccion=prestamos">Préstamos <span><?= count($loans) ?></span></a>
    </nav>

    <?php if ($section === 'libros'): ?>
    <section class="content-grid"><div class="panel form-panel"><div class="panel-heading"><div><p class="eyebrow"><?= $editingBook ? 'ACTUALIZACIÓN' : 'NUEVO REGISTRO' ?></p><h2><?= $editingBook ? 'Editar libro' : 'Agregar libro' ?></h2></div></div>
        <form method="post" action="index.php?seccion=libros" class="form-stack"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="<?= $editingBook ? 'editar_libro' : 'crear_libro' ?>"><?php if ($editingBook): ?><input type="hidden" name="id" value="<?= (int) $editingBook['id'] ?>"><?php endif; ?>
            <label>Título<input name="titulo" maxlength="255" required value="<?= h($editingBook['titulo'] ?? '') ?>"></label>
            <label>Autor<input name="autor" maxlength="255" required value="<?= h($editingBook['autor'] ?? '') ?>"></label>
            <div class="field-row"><label>ISBN <span class="optional">(opcional)</span><input name="isbn" maxlength="20" value="<?= h($editingBook['isbn'] ?? '') ?>"></label><label>Ejemplares<input type="number" name="cantidad" min="0" required value="<?= h($editingBook['cantidad'] ?? 1) ?>"></label></div>
            <div class="form-actions"><button class="button primary" type="submit"><?= $editingBook ? 'Guardar cambios' : 'Registrar libro' ?></button><?php if ($editingBook): ?><a class="button secondary" href="index.php?seccion=libros">Cancelar</a><?php endif; ?></div>
        </form></div>
        <div class="panel table-panel"><div class="panel-heading"><div><p class="eyebrow">COLECCIÓN</p><h2>Libros registrados</h2></div></div>
        <?php if (!$books): ?><p class="empty">Aún no hay libros en el catálogo.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Libro</th><th>ISBN</th><th>Disponibles</th><th>Acciones</th></tr></thead><tbody><?php foreach ($books as $book): ?><tr><td><strong><?= h($book['titulo']) ?></strong><small><?= h($book['autor']) ?></small></td><td><?= h($book['isbn'] ?: '—') ?></td><td><span class="badge <?= (int) $book['cantidad'] > 0 ? 'available' : 'unavailable' ?>"><?= (int) $book['cantidad'] > 0 ? (int) $book['cantidad'] . ' disponibles' : 'Agotado' ?></span></td><td><div class="actions"><a href="index.php?editar_libro=<?= (int) $book['id'] ?>" class="text-action">Editar</a><form method="post" action="index.php?seccion=libros" onsubmit="return confirm('¿Eliminar este libro? Si tiene historial de préstamos, la acción no se permitirá.');"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="eliminar_libro"><input type="hidden" name="id" value="<?= (int) $book['id'] ?>"><button class="text-action danger-link" type="submit">Eliminar</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
    <?php elseif ($section === 'usuarios'): ?>
    <section class="content-grid"><div class="panel form-panel"><div class="panel-heading"><div><p class="eyebrow"><?= $editingUser ? 'ACTUALIZACIÓN' : 'NUEVO REGISTRO' ?></p><h2><?= $editingUser ? 'Editar usuario' : 'Agregar usuario' ?></h2></div></div>
        <form method="post" action="index.php?seccion=usuarios" class="form-stack"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="<?= $editingUser ? 'editar_usuario' : 'crear_usuario' ?>"><?php if ($editingUser): ?><input type="hidden" name="id" value="<?= (int) $editingUser['id'] ?>"><?php endif; ?>
            <label>Nombre completo<input name="nombre" maxlength="255" required value="<?= h($editingUser['nombre'] ?? '') ?>"></label><label>Correo electrónico<input type="email" name="email" maxlength="255" required value="<?= h($editingUser['email'] ?? '') ?>"></label><label>Teléfono <span class="optional">(opcional)</span><input name="telefono" maxlength="20" value="<?= h($editingUser['telefono'] ?? '') ?>"></label>
            <div class="form-actions"><button class="button primary" type="submit"><?= $editingUser ? 'Guardar cambios' : 'Registrar usuario' ?></button><?php if ($editingUser): ?><a class="button secondary" href="index.php?seccion=usuarios">Cancelar</a><?php endif; ?></div>
        </form></div><div class="panel table-panel"><div class="panel-heading"><div><p class="eyebrow">DIRECTORIO</p><h2>Usuarios registrados</h2></div></div>
        <?php if (!$users): ?><p class="empty">Aún no hay usuarios registrados.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Contacto</th><th>Acciones</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><strong><?= h($user['nombre']) ?></strong></td><td><?= h($user['email']) ?><small><?= h($user['telefono'] ?: 'Sin teléfono') ?></small></td><td><div class="actions"><a href="index.php?editar_usuario=<?= (int) $user['id'] ?>" class="text-action">Editar</a><form method="post" action="index.php?seccion=usuarios" onsubmit="return confirm('¿Eliminar este usuario? Si conserva préstamos en su historial, la acción no se permitirá.');"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="eliminar_usuario"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button class="text-action danger-link" type="submit">Eliminar</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
    <?php else: ?>
    <section class="loan-grid"><div class="panel form-panel"><div class="panel-heading"><div><p class="eyebrow">CIRCULACIÓN</p><h2>Registrar préstamo</h2></div></div>
        <form method="post" action="index.php?seccion=prestamos" class="form-stack"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="prestar"><label>Libro<select name="libro_id" required><option value="">Selecciona un libro</option><?php foreach ($books as $book): if ((int) $book['cantidad'] > 0): ?><option value="<?= (int) $book['id'] ?>"><?= h($book['titulo']) ?> (<?= (int) $book['cantidad'] ?> disponibles)</option><?php endif; endforeach; ?></select></label><label>Usuario<select name="usuario_id" required><option value="">Selecciona un usuario</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= h($user['nombre']) ?> · <?= h($user['email']) ?></option><?php endforeach; ?></select></label><button class="button primary" type="submit" <?= (!$books || !$users) ? 'disabled' : '' ?>>Confirmar préstamo</button></form><p class="helper">La disponibilidad se actualiza en la misma transacción del préstamo.</p></div>
        <div class="panel table-panel"><div class="panel-heading"><div><p class="eyebrow">MOVIMIENTOS</p><h2>Historial de préstamos</h2></div><span class="count-label"><?= count($loans) ?> registros</span></div>
        <?php if (!$loans): ?><p class="empty">Todavía no hay préstamos registrados.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Libro y lector</th><th>Fecha</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php foreach ($loans as $loan): ?><tr><td><strong><?= h($loan['titulo']) ?></strong><small><?= h($loan['nombre']) ?> · <?= h($loan['email']) ?></small></td><td><?= h(date('d/m/Y', strtotime($loan['fecha_prestamo']))) ?><?php if ($loan['fecha_devolucion']): ?><small>Devuelto <?= h(date('d/m/Y', strtotime($loan['fecha_devolucion']))) ?></small><?php endif; ?></td><td><span class="badge <?= $loan['estado'] === 'activo' ? 'active-loan' : 'returned' ?>"><?= $loan['estado'] === 'activo' ? 'Activo' : 'Devuelto' ?></span></td><td><?php if ($loan['estado'] === 'activo'): ?><form method="post" action="index.php?seccion=prestamos" onsubmit="return confirm('¿Registrar la devolución de este ejemplar?');"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="devolver"><input type="hidden" name="prestamo_id" value="<?= (int) $loan['id'] ?>"><button class="text-action" type="submit">Registrar devolución</button></form><?php else: ?><span class="muted">Completado</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
    <?php endif; ?>
    <?php endif; ?>
    <footer class="footer">Sistema de Gestión de Biblioteca <span>Actividad práctica de Kodigo</span></footer>
</main>
</body>
</html>
