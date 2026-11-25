<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Biblioteca.php';

$library = new Biblioteca();
$pdo = (new Database())->getConnection();
$tag = bin2hex(random_bytes(4));
$bookId = null;
$userId = null;
$loanId = null;
$checks = [];
$assert = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FALLÓ: ' . $name);
    $checks[] = $name;
};

try {
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $assert((bool) $version, 'Conexión PDO a MariaDB');
    $assert(count($pdo->query('SHOW TABLES')->fetchAll()) >= 3, 'Tablas principales disponibles');

    $bookId = $library->agregarLibro(new Libro('Prueba ' . $tag, 'Autora de prueba', 'T-' . $tag, 1));
    $assert($library->buscarLibro($bookId)['titulo'] === 'Prueba ' . $tag, 'Crear y consultar libro');
    $library->editarLibro($bookId, ['titulo' => 'Libro editado ' . $tag, 'autor' => 'Autora actualizada', 'isbn' => 'T-' . $tag, 'cantidad' => 1]);
    $assert($library->buscarLibro($bookId)['titulo'] === 'Libro editado ' . $tag, 'Editar libro');
    try { new Libro('', 'Autora', null, 1); $valid = false; } catch (InvalidArgumentException) { $valid = true; }
    $assert($valid, 'Rechazar título vacío');
    try { new Libro('Título', 'Autora', null, -1); $valid = false; } catch (InvalidArgumentException) { $valid = true; }
    $assert($valid, 'Rechazar cantidad negativa');
    try { $library->agregarLibro(new Libro('Duplicado', 'Autora', 'T-' . $tag, 1)); $valid = false; } catch (PDOException) { $valid = true; }
    $assert($valid, 'Respetar ISBN único');

    $userId = $library->agregarUsuario(new Usuario('Lector ' . $tag, 'lector-' . $tag . '@example.test', '7000-0000'));
    $assert($library->buscarUsuario($userId)['nombre'] === 'Lector ' . $tag, 'Crear y consultar usuario');
    $library->editarUsuario($userId, ['nombre' => 'Lector editado ' . $tag, 'email' => 'lector-' . $tag . '@example.test', 'telefono' => '7000-0001']);
    $assert($library->buscarUsuario($userId)['nombre'] === 'Lector editado ' . $tag, 'Editar usuario');
    try { new Usuario('Nombre', 'correo-no-valido'); $valid = false; } catch (InvalidArgumentException) { $valid = true; }
    $assert($valid, 'Rechazar correo inválido');
    try { $library->agregarUsuario(new Usuario('Duplicado', 'lector-' . $tag . '@example.test')); $valid = false; } catch (PDOException) { $valid = true; }
    $assert($valid, 'Respetar correo único');
    $loanModel = new Prestamo($bookId, $userId);
    $loanModel->setFechaDevolucion(date('Y-m-d'));
    $assert($loanModel->getLibroId() === $bookId && $loanModel->getUsuarioId() === $userId, 'Entidad de préstamo y accesores');
    try { $loanModel->setFechaPrestamo('2026-02-31'); $valid = false; } catch (InvalidArgumentException) { $valid = true; }
    $assert($valid, 'Rechazar fecha de préstamo imposible');

    $loanId = $library->prestarLibro($bookId, $userId);
    $assert((int) $library->buscarLibro($bookId)['cantidad'] === 0, 'Reducir disponibilidad al prestar');
    $assert(count(array_filter($library->obtenerPrestamosActivos(), static fn(array $loan): bool => (int) $loan['id'] === $loanId)) === 1, 'Listar préstamo activo');
    foreach ([[999999, $userId], [$bookId, 999999]] as [$badBook, $badUser]) {
        try { $library->prestarLibro($badBook, $badUser); $valid = false; } catch (Throwable) { $valid = true; }
        $assert($valid, 'Rechazar préstamo con referencia inexistente');
    }
    try { $library->prestarLibro($bookId, $userId); $valid = false; } catch (RuntimeException) { $valid = true; }
    $assert($valid && (int) $library->buscarLibro($bookId)['cantidad'] === 0, 'Rechazar préstamo sin disponibilidad sin alterar stock');
    try { $library->editarLibro($bookId, ['cantidad' => 0]); $valid = false; } catch (InvalidArgumentException) { $valid = true; }
    $assert($valid, 'No reducir existencias por debajo de préstamos activos');

    $library->devolverLibro($loanId);
    $assert((int) $library->buscarLibro($bookId)['cantidad'] === 1, 'Restaurar disponibilidad al devolver');
    $assert(count(array_filter($library->obtenerPrestamos(), static fn(array $loan): bool => (int) $loan['id'] === $loanId && $loan['estado'] === 'devuelto')) === 1, 'Registrar estado y fecha de devolución');
    try { $library->devolverLibro($loanId); $valid = false; } catch (RuntimeException) { $valid = true; }
    $assert($valid, 'Rechazar devolución duplicada');

    try { $library->eliminarLibro($bookId); $valid = false; } catch (RuntimeException) { $valid = true; }
    $assert($valid, 'Conservar libro con historial');
    try { $library->eliminarUsuario($userId); $valid = false; } catch (RuntimeException) { $valid = true; }
    $assert($valid, 'Conservar usuario con historial');

    $otherBook = $library->agregarLibro(new Libro('Temporal ' . $tag, 'Autora temporal', 'X-' . $tag, 1));
    $otherUser = $library->agregarUsuario(new Usuario('Temporal ' . $tag, 'temp-' . $tag . '@example.test'));
    $assert($library->eliminarLibro($otherBook), 'Eliminar libro sin historial');
    $assert($library->eliminarUsuario($otherUser), 'Eliminar usuario sin historial');
    echo "Aprobadas: " . count($checks) . PHP_EOL;
    foreach ($checks as $check) echo "[OK] {$check}" . PHP_EOL;
} finally {
    if ($loanId !== null) {
        $cleanup = $pdo->prepare('DELETE FROM prestamos WHERE id = ?');
        $cleanup->execute([$loanId]);
    }
    if ($bookId !== null) { $cleanup = $pdo->prepare('DELETE FROM libros WHERE id = ?'); $cleanup->execute([$bookId]); }
    if ($userId !== null) { $cleanup = $pdo->prepare('DELETE FROM usuarios WHERE id = ?'); $cleanup->execute([$userId]); }
}
