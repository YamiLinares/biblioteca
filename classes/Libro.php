<?php
declare(strict_types=1);

final class Libro
{
    private ?int $id = null;
    private string $titulo;
    private string $autor;
    private ?string $isbn;
    private int $cantidad;

    public function __construct(?string $titulo = null, ?string $autor = null, ?string $isbn = null, int $cantidad = 1)
    {
        $this->setTitulo($titulo ?? '');
        $this->setAutor($autor ?? '');
        $this->setIsbn($isbn);
        $this->setCantidad($cantidad);
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { if ($id !== null && $id < 1) throw new InvalidArgumentException('El identificador no es válido.'); $this->id = $id; }
    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): void { $titulo = trim($titulo); if ($titulo === '') throw new InvalidArgumentException('El título es obligatorio.'); $this->titulo = $titulo; }
    public function getAutor(): string { return $this->autor; }
    public function setAutor(string $autor): void { $autor = trim($autor); if ($autor === '') throw new InvalidArgumentException('El autor es obligatorio.'); $this->autor = $autor; }
    public function getIsbn(): ?string { return $this->isbn; }
    public function setIsbn(?string $isbn): void { $isbn = trim((string) $isbn); $this->isbn = $isbn === '' ? null : $isbn; }
    public function getCantidad(): int { return $this->cantidad; }
    public function setCantidad(int $cantidad): void { if ($cantidad < 0) throw new InvalidArgumentException('La cantidad no puede ser negativa.'); $this->cantidad = $cantidad; }
}
