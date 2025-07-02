<?php
// CONFIGURACION GENERAL
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
date_default_timezone_set('America/Argentina/Buenos_Aires');
header('Content-Type: application/json');

// MENSAJE DEL CLIENTE
$input = json_decode(file_get_contents('php://input'), true);
$mensaje = strtolower(trim($input['mensaje'] ?? ''));
$telefono = trim($input['telefono'] ?? '');
$nombreArchivo = 'clientes_mora_temprana.csv';
$logFile = 'interacciones_mora.csv';
$reporteFile = 'reporte_chats.csv';

// FUNCIONES AUXILIARES
function buscarCliente($telefono, $archivo) {
    if (!file_exists($archivo)) return null;
    $csv = array_map('str_getcsv', file($archivo));
    foreach ($csv as $linea) {
        if (isset($linea[2]) && limpiarNumero($linea[2]) === limpiarNumero($telefono)) {
            return [
                'nombre' => $linea[0],
                'dni' => $linea[1],
                'telefono' => $linea[2],
            ];
        }
    }
    return null;
}

function limpiarNumero($numero) {
    return preg_replace('/\D+/', '', $numero);
}

function registrarInteraccion($telefono) {
    $fecha = date('Y-m-d');
    $hora = date('H:i:s');
    file_put_contents('interacciones_mora.csv', "$telefono;$fecha;$hora\n", FILE_APPEND);
}

function registrarReporte($dni, $telefono, $detalle) {
    $linea = "$dni;$telefono;$detalle\n";
    file_put_contents('reporte_chats.csv', "$dni;" . "$detalle (Tel: $telefono)\n", FILE_APPEND);
}

function menuPrincipalSinValidar() {
    return "Hola, soy Carla del equipo de cobranzas de Naranja X. Para continuar necesito saber si estoy hablando con el titular de la cuenta. Por favor confirmalo para avanzar con la información.";
}

function menuPrincipalConfirmado($nombre) {
    return "Hola $nombre, gracias por confirmar que sos el titular. Tu tarjeta presenta una deuda en instancia prelegal. Elegí una opción para avanzar:\n\n1. Ver medios de pago\n2. Conocer plan disponible\n3. Ya pagué\n4. No reconozco la deuda";
}

function subMenuPago() {
    return "Podés abonar por:\n- App Naranja X\n- Home Banking (Link / Banelco)\n- Pago Fácil / Cobro Express / Rapipago\n- CBU o débito automático\n\nRecordá que siempre se sumarán intereses en el resumen del mes siguiente.\n\nPor favor, verificá tus datos personales en la app (domicilio, teléfono y mail).";
}

function subMenuPlanes() {
    return "Estás en instancia prelegal. Podés acceder al *Plan de Pago Total* o *Plan Excepción*, que financia toda la deuda pendiente.\n\n- El plástico queda inhabilitado hasta abonar el 60% del plan.\n- Los datos ya fueron informados al Banco Central.\n- Los débitos están suspendidos.\n- Siempre se aplicarán intereses en el próximo resumen.\n\nRecordá revisar en la app que tus datos personales estén actualizados (domicilio, teléfono y mail).";
}

function respuestaPagado() {
    return "🙌 Gracias por informarlo. Indicá por favor:\n- Monto pagado\n- Medio de pago\n- Fecha\nAsí actualizamos nuestros registros.\nTené en cuenta que podrían verse reflejados intereses en el próximo resumen.";
}

function respuestaNoReconoce() {
    return "Si no reconocés la deuda, podés iniciar un reclamo. Contactanos para más información.";
}

// LOGICA PRINCIPAL
$cliente = buscarCliente($telefono, $nombreArchivo);

if (!$cliente) {
    echo json_encode(['reply' => "Hola. Para poder ayudarte, por favor escribí tu DNI (solo números)."], JSON_UNESCAPED_UNICODE);
    exit;
}

registrarInteraccion($telefono);

$esTitular = strpos($mensaje, 'soy el titular') !== false || strpos($mensaje, 'si soy') !== false || strpos($mensaje, 'soy yo') !== false || strpos($mensaje, 'habla el titular') !== false;

if (!$esTitular && !in_array($mensaje, ['1', '2', '3', '4'])) {
    $respuesta = menuPrincipalSinValidar();
} elseif (!$esTitular && in_array($mensaje, ['1', '2', '3', '4'])) {
    $respuesta = "Necesito que primero confirmes si sos el titular de la cuenta para poder darte información.";
} else {
    switch ($mensaje) {
        case '1':
        case 'ver medios de pago':
            $respuesta = subMenuPago();
            registrarReporte($cliente['dni'], $cliente['telefono'], date('Y-m-d H:i:s') . ' - $1');
            break;
        case '2':
        case 'conocer plan disponible':
            $respuesta = subMenuPlanes();
            registrarReporte($cliente['dni'], $cliente['telefono'], 'El número de teléfono solicitó conocer plan disponible.');
            break;
        case '3':
        case 'ya pague':
        case 'ya pagué':
            $respuesta = respuestaPagado();
            registrarReporte($cliente['dni'], $cliente['telefono'], 'El número de teléfono manifestó que ya realizó el pago.');
            break;
        case '4':
        case 'no reconozco la deuda':
            $respuesta = respuestaNoReconoce();
            registrarReporte($cliente['dni'], $cliente['telefono'], 'El número de teléfono indicó que no reconoce la deuda.');
            break;
        default:
            $respuesta = menuPrincipalConfirmado($cliente['nombre']);
            break;
    }
}

echo json_encode(['reply' => $respuesta], JSON_UNESCAPED_UNICODE);
exit;
