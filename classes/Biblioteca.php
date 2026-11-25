<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Libro.php';
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Prestamo.php';

final class Biblioteca
{
    private PDO $conn;

    public function __construct(?PDO $connection = null)
    {
        $this->conn = $connection ?? (new Database())->getConnection();
    }

    public function agregarLibro(Libro $libro): int
    {
        $stmt = $this->conn->prepare('INSERT INTO libros (titulo, autor, isbn, cantidad) VALUES (:titulo, :autor, :isbn, :cantidad)');
        $stmt->execute(['titulo' => $libro->getTitulo(), 'autor' => $libro->getAutor(), 'isbn' => $libro->getIsbn(), 'cantidad' => $libro->getCantidad()]);
        return (int) $this->conn->lastInsertId();
    }

    public function editarLibro(int $id, array $nuevosDatos): bool
    {
        $actual = $this->buscarLibro($id);
        if (!$actual) throw new RuntimeException('El libro seleccionado no existe.');
        $libro = new Libro(
            (string) ($nuevosDatos['titulo'] ?? $actual['titulo']),
            (string) ($nuevosDatos['autor'] ?? $actual['autor']),
            array_key_exists('isbn', $nuevosDatos) ? (string) $nuevosDatos['isbn'] : $actual['isbn'],
            array_key_exists('cantidad', $nuevosDatos) ? $this->entero($nuevosDatos['cantidad'], 'cantidad') : (int) $actual['cantidad']
        );
        $activos = $this->conn->prepare("SELECT COUNT(*) FROM prestamos WHERE libro_id = :id AND estado = 'activo'");
        $activos->execute(['id' => $id]);
        if ($libro->getCantidad() < (int) $activos->fetchColumn()) {
            throw new InvalidArgumentException('La cantidad no puede ser menor que los ejemplares actualmente prestados.');
        }
        $stmt = $this->conn->prepare('UPDATE libros SET titulo = :titulo, autor = :autor, isbn = :isbn, cantidad = :cantidad WHERE id = :id');
        $stmt->execute(['titulo' => $libro->getTitulo(), 'autor' => $libro->getAutor(), 'isbn' => $libro->getIsbn(), 'cantidad' => $libro->getCantidad(), 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function eliminarLibro(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM libros WHERE id = :id');
        try {
            $stmt->execute(['id' => $id]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') throw new RuntimeException('No se puede eliminar este libro porque forma parte del historial de préstamos.');
            throw $error;
        }
        return $stmt->rowCount() > 0;
    }

    public function obtenerLibros(): array
    {
        return $this->conn->query('SELECT id, titulo, autor, isbn, cantidad, created_at FROM libros ORDER BY titulo')->fetchAll();
    }

    public function buscarLibro(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, titulo, autor, isbn, cantidad, created_at FROM libros WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function agregarUsuario(Usuario $usuario): int
    {
        $stmt = $this->conn->prepare('INSERT INTO usuarios (nombre, email, telefono) VALUES (:nombre, :email, :telefono)');
        $stmt->execute(['nombre' => $usuario->getNombre(), 'email' => $usuario->getEmail(), 'telefono' => $usuario->getTelefono()]);
        return (int) $this->conn->lastInsertId();
    }

    public function editarUsuario(int $id, array $nuevosDatos): bool
    {
        $actual = $this->buscarUsuario($id);
        if (!$actual) throw new RuntimeException('El usuario seleccionado no existe.');
        $usuario = new Usuario((string) ($nuevosDatos['nombre'] ?? $actual['nombre']), (string) ($nuevosDatos['email'] ?? $actual['email']), array_key_exists('telefono', $nuevosDatos) ? (string) $nuevosDatos['telefono'] : $actual['telefono']);
        $stmt = $this->conn->prepare('UPDATE usuarios SET nombre = :nombre, email = :email, telefono = :telefono WHERE id = :id');
        $stmt->execute(['nombre' => $usuario->getNombre(), 'email' => $usuario->getEmail(), 'telefono' => $usuario->getTelefono(), 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function eliminarUsuario(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM usuarios WHERE id = :id');
        try {
            $stmt->execute(['id' => $id]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') throw new RuntimeException('No se puede eliminar este usuario porque conserva préstamos en el historial.');
            throw $error;
        }
        return $stmt->rowCount() > 0;
    }

    public function obtenerUsuarios(): array
    {
        return $this->conn->query('SELECT id, nombre, email, telefono, created_at FROM usuarios ORDER BY nombre')->fetchAll();
    }

    public function buscarUsuario(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT id, nombre, email, telefono, created_at FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function prestarLibro(int $libro_id, int $usuario_id): int
    {
        $prestamo = new Prestamo($libro_id, $usuario_id);
        $this->conn->beginTransaction();
        try {
            $stock = $this->conn->prepare('SELECT cantidad FROM libros WHERE id = :id FOR UPDATE');
            $stock->execute(['id' => $prestamo->getLibroId()]);
            $libro = $stock->fetch();
            if (!$libro) throw new RuntimeException('El libro seleccionado no existe.');
            if ((int) $libro['cantidad'] < 1) throw new RuntimeException('No hay ejemplares disponibles de este libro.');
            $usuario = $this->conn->prepare('SELECT id FROM usuarios WHERE id = :id');
            $usuario->execute(['id' => $prestamo->getUsuarioId()]);
            if (!$usuario->fetch()) throw new RuntimeException('El usuario seleccionado no existe.');
            $update = $this->conn->prepare('UPDATE libros SET cantidad = cantidad - 1 WHERE id = :id AND cantidad > 0');
            $update->execute(['id' => $prestamo->getLibroId()]);
            if ($update->rowCount() !== 1) throw new RuntimeException('El ejemplar ya no está disponible. Actualiza la página e inténtalo de nuevo.');
            $insert = $this->conn->prepare("INSERT INTO prestamos (libro_id, usuario_id, fecha_prestamo, estado) VALUES (:libro, :usuario, :fecha, 'activo')");
            $insert->execute(['libro' => $prestamo->getLibroId(), 'usuario' => $prestamo->getUsuarioId(), 'fecha' => $prestamo->getFechaPrestamo()]);
            $id = (int) $this->conn->lastInsertId();
            $this->conn->commit();
            return $id;
        } catch (Throwable $error) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $error;
        }
    }

    public function devolverLibro(int $prestamo_id): bool
    {
        $this->conn->beginTransaction();
        try {
            $query = $this->conn->prepare('SELECT id, libro_id, estado FROM prestamos WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $prestamo_id]);
            $prestamo = $query->fetch();
            if (!$prestamo) throw new RuntimeException('El préstamo seleccionado no existe.');
            if ($prestamo['estado'] !== 'activo') throw new RuntimeException('Este préstamo ya fue devuelto.');
            $update = $this->conn->prepare("UPDATE prestamos SET fecha_devolucion = :fecha, estado = 'devuelto' WHERE id = :id AND estado = 'activo'");
            $update->execute(['fecha' => date('Y-m-d'), 'id' => $prestamo_id]);
            if ($update->rowCount() !== 1) throw new RuntimeException('No se pudo completar la devolución.');
            $stock = $this->conn->prepare('UPDATE libros SET cantidad = cantidad + 1 WHERE id = :id');
            $stock->execute(['id' => (int) $prestamo['libro_id']]);
            if ($stock->rowCount() !== 1) throw new RuntimeException('No se encontró el libro asociado al préstamo.');
            $this->conn->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $error;
        }
    }

    public function obtenerPrestamosActivos(): array
    {
        $stmt = $this->conn->query("SELECT p.id, p.libro_id, p.usuario_id, p.fecha_prestamo, p.fecha_devolucion, p.estado, l.titulo, u.nombre, u.email FROM prestamos p JOIN libros l ON l.id = p.libro_id JOIN usuarios u ON u.id = p.usuario_id WHERE p.estado = 'activo' ORDER BY p.fecha_prestamo DESC, p.id DESC");
        return $stmt->fetchAll();
    }

    public function obtenerPrestamos(): array
    {
        return $this->conn->query('SELECT p.id, p.fecha_prestamo, p.fecha_devolucion, p.estado, l.titulo, u.nombre, u.email FROM prestamos p JOIN libros l ON l.id = p.libro_id JOIN usuarios u ON u.id = p.usuario_id ORDER BY p.id DESC')->fetchAll();
    }

    public function obtenerResumen(): array
    {
        return [
            'libros' => (int) $this->conn->query('SELECT COUNT(*) FROM libros')->fetchColumn(),
            'usuarios' => (int) $this->conn->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(),
            'activos' => (int) $this->conn->query("SELECT COUNT(*) FROM prestamos WHERE estado = 'activo'")->fetchColumn(),
        ];
    }

    private function entero(mixed $valor, string $campo): int
    {
        $resultado = filter_var($valor, FILTER_VALIDATE_INT);
        if ($resultado === false) throw new InvalidArgumentException('El campo ' . $campo . ' debe ser un número entero.');
        return $resultado;
    }
}
