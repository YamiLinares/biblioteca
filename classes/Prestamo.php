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
        $this->setLibroId($libro_id ?? 0);
        $this->setUsuarioId($usuario_id ?? 0);
        $this->setFechaPrestamo(date('Y-m-d'));
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { if ($id !== null && $id < 1) throw new InvalidArgumentException('El identificador no es válido.'); $this->id = $id; }
    public function getLibroId(): int { return $this->libro_id; }
    public function setLibroId(int $libro_id): void { if ($libro_id < 1) throw new InvalidArgumentException('El libro seleccionado no es válido.'); $this->libro_id = $libro_id; }
    public function getUsuarioId(): int { return $this->usuario_id; }
    public function setUsuarioId(int $usuario_id): void { if ($usuario_id < 1) throw new InvalidArgumentException('El usuario seleccionado no es válido.'); $this->usuario_id = $usuario_id; }
    public function getFechaPrestamo(): string { return $this->fecha_prestamo; }
    public function setFechaPrestamo(string $fecha): void { if (!$this->fechaValida($fecha)) throw new InvalidArgumentException('La fecha del préstamo no es válida.'); $this->fecha_prestamo = $fecha; }
    public function getFechaDevolucion(): ?string { return $this->fecha_devolucion; }
    public function setFechaDevolucion(?string $fecha): void
    {
        if ($fecha !== null && (!$this->fechaValida($fecha) || $fecha < $this->fecha_prestamo)) throw new InvalidArgumentException('La fecha de devolución no es válida.');
        $this->fecha_devolucion = $fecha;
    }
    public function getEstado(): string { return $this->estado; }
    public function setEstado(string $estado): void { if (!in_array($estado, ['activo', 'devuelto'], true)) throw new InvalidArgumentException('El estado del préstamo no es válido.'); $this->estado = $estado; }

    private function fechaValida(string $fecha): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $fecha);
        return $date !== false && $date->format('Y-m-d') === $fecha;
    }
}
