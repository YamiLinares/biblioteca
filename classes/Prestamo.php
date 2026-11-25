<?php
declare(strict_types=1);

final class Prestamo
{
    private ?int $id = null;
    private int $libro_id;
    private int $usuario_id;
    private string $fecha_prestamo;
    private ?string $fecha_devolucion = null;
    private string $estado = 'activo';

    public function __construct(?int $libro_id = null, ?int $usuario_id = null)
    {
        if (($libro_id ?? 0) < 1 || ($usuario_id ?? 0) < 1) throw new InvalidArgumentException('Selecciona un libro y un usuario válidos.');
        $this->libro_id = $libro_id;
        $this->usuario_id = $usuario_id;
        $this->fecha_prestamo = date('Y-m-d');
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { if ($id !== null && $id < 1) throw new InvalidArgumentException('El identificador no es válido.'); $this->id = $id; }
    public function getLibroId(): int { return $this->libro_id; }
    public function getUsuarioId(): int { return $this->usuario_id; }
    public function getFechaPrestamo(): string { return $this->fecha_prestamo; }
    public function getFechaDevolucion(): ?string { return $this->fecha_devolucion; }
    public function setFechaDevolucion(?string $fecha): void { if ($fecha !== null && !DateTime::createFromFormat('Y-m-d', $fecha)) throw new InvalidArgumentException('La fecha de devolución no es válida.'); $this->fecha_devolucion = $fecha; }
    public function getEstado(): string { return $this->estado; }
    public function setEstado(string $estado): void { if (!in_array($estado, ['activo', 'devuelto'], true)) throw new InvalidArgumentException('El estado del préstamo no es válido.'); $this->estado = $estado; }
}
