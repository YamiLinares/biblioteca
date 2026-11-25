<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Database.php';

$baseUrl = 'http://127.0.0.1:8085/';
$cookie = '';
$tag = bin2hex(random_bytes(4));
$isbn = 'WEB-' . $tag;
$email = 'web-' . $tag . '@example.test';
$deleteIsbn = 'DEL-' . $tag;
$deleteEmail = 'del-' . $tag . '@example.test';
$pdo = (new Database())->getConnection();
$ids = ['book' => null, 'user' => null, 'loan' => null, 'deleteBook' => null, 'deleteUser' => null];
$checks = [];

$request = null;
$request = static function (string $path, ?array $form = null) use (&$cookie, &$request, $baseUrl): array {
    $headers = ['Connection: close'];
    if ($cookie !== '') $headers[] = 'Cookie: ' . $cookie;
    $options = ['method' => $form === null ? 'GET' : 'POST', 'header' => $headers, 'ignore_errors' => true, 'follow_location' => 0, 'max_redirects' => 0, 'timeout' => 8];
    if ($form !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $options['header'] = $headers;
        $options['content'] = http_build_query($form);
    }
    $context = stream_context_create(['http' => $options]);
    $body = file_get_contents($baseUrl . ltrim($path, '/'), false, $context);
    $responseHeaders = $http_response_header ?? [];
    if ($body === false || !$responseHeaders) throw new RuntimeException('No fue posible contactar el servidor local en ' . $baseUrl);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $header, $match)) $cookie = $match[1];
    }
    preg_match('/^HTTP\/\S+\s+(\d+)/', $responseHeaders[0], $match);
    $status = (int) ($match[1] ?? 0);
    $response = ['status' => $status, 'headers' => $responseHeaders, 'body' => $body];
    if ($status === 302) {
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
                return $request($location, null);
            }
        }
    }
    return $response;
};
$csrf = static function (string $body): string {
    if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $match)) throw new RuntimeException('No se encontró token CSRF en el formulario.');
    return $match[1];
};
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('Falló la prueba HTTP: ' . $label);
    $checks[] = $label;
};

try {
    $page = $request('?seccion=libros');
    $assert($page['status'] === 200 && str_contains($page['body'], 'Libros registrados'), 'Abrir catálogo');
    $token = $csrf($page['body']);

    $book = $request('?seccion=libros', ['csrf' => $token, 'action' => 'crear_libro', 'titulo' => 'Libro <script>alert(1)</script> ' . $tag, 'autor' => 'Autora web', 'isbn' => $isbn, 'cantidad' => '1']);
    $assert(str_contains($book['body'], 'Libro registrado correctamente.'), 'Alta de libro');
    $assert(str_contains($book['body'], '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($book['body'], '<script>alert(1)</script>'), 'Escapar texto HTML de libro');
    $stmt = $pdo->prepare('SELECT id FROM libros WHERE isbn = ?'); $stmt->execute([$isbn]); $ids['book'] = (int) $stmt->fetchColumn();
    $token = $csrf($book['body']);
    $edited = $request('?seccion=libros', ['csrf' => $token, 'action' => 'editar_libro', 'id' => $ids['book'], 'titulo' => 'Libro editado ' . $tag, 'autor' => 'Autora web', 'isbn' => $isbn, 'cantidad' => '1']);
    $assert(str_contains($edited['body'], 'Libro actualizado correctamente.'), 'Edición de libro');

    $page = $request('?seccion=usuarios');
    $user = $request('?seccion=usuarios', ['csrf' => $csrf($page['body']), 'action' => 'crear_usuario', 'nombre' => 'Lector web ' . $tag, 'email' => $email, 'telefono' => '7000-3000']);
    $assert(str_contains($user['body'], 'Usuario registrado correctamente.'), 'Alta de usuario');
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?'); $stmt->execute([$email]); $ids['user'] = (int) $stmt->fetchColumn();
    $editedUser = $request('?seccion=usuarios', ['csrf' => $csrf($user['body']), 'action' => 'editar_usuario', 'id' => $ids['user'], 'nombre' => 'Lector editado ' . $tag, 'email' => $email, 'telefono' => '7000-3001']);
    $assert(str_contains($editedUser['body'], 'Usuario actualizado correctamente.'), 'Edición de usuario');

    $page = $request('?seccion=prestamos');
    $loan = $request('?seccion=prestamos', ['csrf' => $csrf($page['body']), 'action' => 'prestar', 'libro_id' => $ids['book'], 'usuario_id' => $ids['user']]);
    $assert(str_contains($loan['body'], 'Préstamo registrado y disponibilidad actualizada.'), 'Registro de préstamo');
    $stmt = $pdo->prepare('SELECT id FROM prestamos WHERE libro_id = ? AND usuario_id = ? ORDER BY id DESC LIMIT 1'); $stmt->execute([$ids['book'], $ids['user']]); $ids['loan'] = (int) $stmt->fetchColumn();
    $returned = $request('?seccion=prestamos', ['csrf' => $csrf($loan['body']), 'action' => 'devolver', 'prestamo_id' => $ids['loan']]);
    $assert(str_contains($returned['body'], 'Devolución registrada correctamente.'), 'Registrar devolución');
    $repeat = $request('?seccion=prestamos', ['csrf' => $csrf($returned['body']), 'action' => 'devolver', 'prestamo_id' => $ids['loan']]);
    $assert(str_contains($repeat['body'], 'Este préstamo ya fue devuelto.'), 'Rechazar devolución repetida');

    $invalidToken = $request('?seccion=libros', ['csrf' => 'invalid', 'action' => 'crear_libro', 'titulo' => 'No guardar', 'autor' => 'Prueba', 'cantidad' => '1']);
    $assert(str_contains($invalidToken['body'], 'La sesión del formulario venció.'), 'Rechazar token CSRF inválido');

    $page = $request('?seccion=libros');
    $extraBook = $request('?seccion=libros', ['csrf' => $csrf($page['body']), 'action' => 'crear_libro', 'titulo' => 'Temporal ' . $tag, 'autor' => 'Autora web', 'isbn' => $deleteIsbn, 'cantidad' => '1']);
    $stmt = $pdo->prepare('SELECT id FROM libros WHERE isbn = ?'); $stmt->execute([$deleteIsbn]); $ids['deleteBook'] = (int) $stmt->fetchColumn();
    $deletedBook = $request('?seccion=libros', ['csrf' => $csrf($extraBook['body']), 'action' => 'eliminar_libro', 'id' => $ids['deleteBook']]);
    $assert(str_contains($deletedBook['body'], 'Libro eliminado.'), 'Eliminar libro independiente');

    $page = $request('?seccion=usuarios');
    $extraUser = $request('?seccion=usuarios', ['csrf' => $csrf($page['body']), 'action' => 'crear_usuario', 'nombre' => 'Temporal ' . $tag, 'email' => $deleteEmail]);
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?'); $stmt->execute([$deleteEmail]); $ids['deleteUser'] = (int) $stmt->fetchColumn();
    $deletedUser = $request('?seccion=usuarios', ['csrf' => $csrf($extraUser['body']), 'action' => 'eliminar_usuario', 'id' => $ids['deleteUser']]);
    $assert(str_contains($deletedUser['body'], 'Usuario eliminado.'), 'Eliminar usuario independiente');
    $blocked = $request('?seccion=usuarios', ['csrf' => $csrf($deletedUser['body']), 'action' => 'eliminar_usuario', 'id' => $ids['user']]);
    $assert(str_contains($blocked['body'], 'conserva préstamos en el historial'), 'Proteger usuario con historial');

    echo 'Pruebas HTTP aprobadas: ' . count($checks) . PHP_EOL;
    foreach ($checks as $check) echo '[OK] ' . $check . PHP_EOL;
} finally {
    if ($ids['loan']) { $stmt = $pdo->prepare('DELETE FROM prestamos WHERE id = ?'); $stmt->execute([$ids['loan']]); }
    if ($ids['book']) { $stmt = $pdo->prepare('DELETE FROM libros WHERE id = ?'); $stmt->execute([$ids['book']]); }
    if ($ids['user']) { $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?'); $stmt->execute([$ids['user']]); }
    if ($ids['deleteBook']) { $stmt = $pdo->prepare('DELETE FROM libros WHERE id = ?'); $stmt->execute([$ids['deleteBook']]); }
    if ($ids['deleteUser']) { $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?'); $stmt->execute([$ids['deleteUser']]); }
}
