<?php
session_start();

// Función para comprobar si el usuario está autenticado
function estaAutenticado() {
    return isset($_SESSION['usuario']);
}

// Función para crear un usuario si no existe
function crearUsuarioSiNoExiste($nombre, $contrasena) {
    $usuarios = file('usuarios.txt', FILE_IGNORE_NEW_LINES);
    foreach ($usuarios as $linea) {
        $usuario = json_decode($linea, true);
        if ($usuario['nombre'] === $nombre) {
            // El usuario ya existe, no es necesario crearlo de nuevo
            return;
        }
    }

    // Si llega aquí, significa que el usuario no existe, entonces lo creamos
    $nuevoUsuario = [
        'nombre' => $nombre,
        'contrasena' => password_hash($contrasena, PASSWORD_DEFAULT),
        'archivos' => []
    ];
    file_put_contents('usuarios.txt', json_encode($nuevoUsuario) . PHP_EOL, FILE_APPEND);
}

// Función para verificar las credenciales del usuario
function verificarCredenciales($nombre, $contrasena) {
    $usuarios = file('usuarios.txt', FILE_IGNORE_NEW_LINES);
    foreach ($usuarios as $linea) {
        $usuario = json_decode($linea, true);
        if ($usuario && isset($usuario['nombre']) && isset($usuario['contrasena']) && isset($usuario['archivos'])) {
            if ($usuario['nombre'] === $nombre && password_verify($contrasena, $usuario['contrasena'])) {
                $_SESSION['usuario'] = $usuario['nombre'];
                $_SESSION['archivos'] = $usuario['archivos']; // Almacenar los archivos del usuario en la sesión
                return true;
            }
        }
    }
    return false;
}

// Función para subir un archivo para el usuario autenticado
function subirArchivo($nombreArchivo, $archivoTmp) {
    if (!estaAutenticado()) {
        return false;
    }

    $carpetaDestino = "archivos_subidos/";
    if (move_uploaded_file($archivoTmp, $carpetaDestino . $nombreArchivo)) {
        $_SESSION['archivos'][] = $nombreArchivo; // Añadir el nombre del archivo a la lista de archivos del usuario en la sesión
        actualizarUsuario(); // Actualizar la información del usuario en el archivo
        return true;
    } else {
        return false;
    }
}

// Función para mostrar los archivos del usuario autenticado
function mostrarArchivos() {
    if (!estaAutenticado() || !isset($_SESSION['archivos']) || !is_array($_SESSION['archivos']) || empty($_SESSION['archivos'])) {
        echo "<li>No hay archivos disponibles.</li>";
        return;
    }

    foreach ($_SESSION['archivos'] as $archivo) {
        echo "<li>$archivo <a href='?accion=borrar&archivo=$archivo'>Borrar</a></li>";
    }
}
// Función para borrar un archivo del usuario autenticado
function borrarArchivo($nombreArchivo) {
    if(isset($_SESSION['archivos']) && is_array($_SESSION['archivos']) && !empty($_SESSION['archivos'])) {
        $carpetaDestino = "archivos_subidos/";
        $rutaArchivo = $carpetaDestino . $nombreArchivo;
        if (file_exists($rutaArchivo)) {
            unlink($rutaArchivo); // Borrar el archivo del sistema de archivos
            $index = array_search($nombreArchivo, $_SESSION['archivos']);
            if ($index !== false) {
                unset($_SESSION['archivos'][$index]); // Eliminar el nombre del archivo de la lista de archivos del usuario en la sesión
                actualizarUsuario(); // Actualizar la información del usuario en el archivo
                // Redirigir después de borrar el archivo exitosamente
                header("Location: {$_SERVER['PHP_SELF']}");
                exit();
            }
        }
    }
    return false;
}


// Función para actualizar la información del usuario en el archivo
function actualizarUsuario() {
    $usuarios = file('usuarios.txt');
    $nuevasLineas = [];
    foreach ($usuarios as $linea) {
        $usuario = json_decode($linea, true);
        if ($usuario['nombre'] === $_SESSION['usuario']) {
            $usuario['archivos'] = $_SESSION['archivos'];
            $linea = json_encode($usuario) . PHP_EOL;
        }
        $nuevasLineas[] = $linea;
    }
    file_put_contents('usuarios.txt', implode($nuevasLineas));
}

// Función para eliminar un usuario por el administrador
function eliminarUsuario($nombreUsuario) {
    $usuarios = file('usuarios.txt');
    $nuevosUsuarios = [];
    foreach ($usuarios as $linea) {
        $usuario = json_decode($linea, true);
        if ($usuario['nombre'] !== $nombreUsuario) {
            $nuevosUsuarios[] = $linea;
        }
    }
    file_put_contents('usuarios.txt', implode($nuevosUsuarios));
}

// Función para listar todos los usuarios (solo para el administrador)
function listarUsuarios() {
    $usuarios = file('usuarios.txt');
    foreach ($usuarios as $linea) {
        $usuario = json_decode($linea, true);
        echo "<li>{$usuario['nombre']} <a href='?accion=eliminar_usuario&nombre={$usuario['nombre']}'>Eliminar</a></li>";
    }
}

// Manejar acciones según la solicitud
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['accion'])) {
        $accion = $_POST['accion'];
        switch ($accion) {
            case 'registro':
                $nombreUsuario = trim($_POST['nombre']);
                $contrasena = $_POST['contrasena'];
                crearUsuarioSiNoExiste($nombreUsuario, $contrasena);
                break;
            case 'inicio_sesion':
                $nombreUsuario = $_POST['nombre'];
                $contrasena = $_POST['contrasena'];
                verificarCredenciales($nombreUsuario, $contrasena);
                break;
            case 'subir_archivo':
                if (isset($_FILES["archivo"])) {
                    $nombreArchivo = $_FILES["archivo"]["name"];
                    $archivoTmp = $_FILES["archivo"]["tmp_name"];
                    subirArchivo($nombreArchivo, $archivoTmp);
                }
                break;
            case 'logout':
                if (estaAutenticado()) {
                    session_unset();
                    session_destroy();
                }
                break;
            default:
                break;
        }
    }
}

// Manejar acciones a través de la URL
if (isset($_GET['accion'])) {
    $accion = $_GET['accion'];
    switch ($accion) {
        case 'borrar':
            if (estaAutenticado() && isset($_GET['archivo'])) {
                $nombreArchivo = $_GET['archivo'];
                borrarArchivo($nombreArchivo);
            }
            break;
        case 'eliminar_usuario':
            if (estaAutenticado() && $_SESSION['usuario'] === 'admin' && isset($_GET['nombre'])) {
                $nombreUsuario = $_GET['nombre'];
                eliminarUsuario($nombreUsuario);
            }
            break;
        default:
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplicación Web</title>
</head>
<body>

<?php if (estaAutenticado()): ?>
    <h2>Bienvenido, <?php echo $_SESSION['usuario']; ?></h2>
    <?php if ($_SESSION['usuario'] === 'admin'): ?>
        <h3>Lista de usuarios:</h3>
        <ul>
            <?php listarUsuarios(); ?>
        </ul>
    <?php else: ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <input type="file" name="archivo" id="archivo">
            <input type="submit" value="Subir Archivo" name="accion">
            <input type="hidden" name="accion" value="subir_archivo">
        </form>
        <h3>Tus archivos:</h3>
        <ul>
            <?php mostrarArchivos(); ?>
        </ul>
    <?php endif; ?>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="submit" value="Cerrar Sesión" name="accion">
        <input type="hidden" name="accion" value="logout">
    </form>
<?php else: ?>
    <h2>Registro</h2>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        Nombre de usuario: <input type="text" name="nombre" required><br>
        Contraseña: <input type="password" name="contrasena" required><br>
        <input type="submit" value="Registrarse" name="accion">
        <input type="hidden" name="accion" value="registro">
    </form>
    <h2>Iniciar Sesión</h2>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        Nombre de usuario: <input type="text" name="nombre" required><br>
        Contraseña: <input type="password" name="contrasena" required><br>
        <input type="submit" value="Iniciar Sesión" name="accion">
        <input type="hidden" name="accion" value="inicio_sesion">
    </form>
<?php endif; ?>

</body>
</html>
