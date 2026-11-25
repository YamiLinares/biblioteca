<?php
declare(strict_types=1);

final class Usuario
{
    private ?int $id = null;
    private string $nombre;
    private string $email;
    private ?string $telefono;

    public function __construct(?string $nombre = null, ?string $email = null, ?string $telefono = null)
    {
        $this->setNombre($nombre ?? '');
        $this->setEmail($email ?? '');
        $this->setTelefono($telefono);
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { if ($id !== null && $id < 1) throw new InvalidArgumentException('El identificador no es válido.'); $this->id = $id; }
    public function getNombre(): string { return $this->nombre; }
    public function setNombre(string $nombre): void { $nombre = trim($nombre); if ($nombre === '') throw new InvalidArgumentException('El nombre es obligatorio.'); if (mb_strlen($nombre) > 255) throw new InvalidArgumentException('El nombre supera 255 caracteres.'); $this->nombre = $nombre; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $email = trim($email); if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Correo electrónico inválido.'); if (mb_strlen($email) > 255) throw new InvalidArgumentException('El correo supera 255 caracteres.'); $this->email = $email; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function setTelefono(?string $telefono): void { $telefono = trim((string) $telefono); if (mb_strlen($telefono) > 20) throw new InvalidArgumentException('El teléfono supera 20 caracteres.'); $this->telefono = $telefono === '' ? null : $telefono; }
}
