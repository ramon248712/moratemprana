<?php
date_default_timezone_set("America/Argentina/Buenos_Aires");
header('Content-Type: application/json; charset=utf-8');

// Obtener datos desde WhatsAuto
$app     = $_POST["app"] ?? '';
$sender  = preg_replace('/\D/', '', $_POST["sender"] ?? '');
$message = strtolower(trim($_POST["message"] ?? ''));
$senderBase = substr($sender, -10);

// Validar teléfono
if (strlen($senderBase) != 10) {
    echo json_encode(["reply" => ""]);
    exit;
}

// Archivos
$csvFile       = __DIR__ . '/deudores.csv';
$reporteChats  = __DIR__ . '/reporte_chats.csv';

// Cargar deudores
$clientes = [];
if (file_exists($csvFile)) {
    $file = fopen($csvFile, 'r');
    while (($data = fgetcsv($file, 0, ';')) !== false) {
        if (count($data) < 3) continue;
        $clientes[] = [
            'nombre'   => $data[0],
            'dni'      => $data[1],
            'telefono' => substr(preg_replace('/\D/', '', $data[2]), -10)
        ];
    }
    fclose($file);
}

// Buscar cliente
$cliente = null;
foreach ($clientes as $c) {
    if ($c['telefono'] === $senderBase) {
        $cliente = $c;
        break;
    }
}

// Si no se encuentra el cliente, pedir el DNI
if (!$cliente) {
    echo json_encode(["reply" => "Hola. Para poder ayudarte, por favor escribí tu DNI (solo números)."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ver si confirmó titularidad
$esTitular = strpos($message, 'soy el titular') !== false ||
             strpos($message, 'si soy') !== false ||
             strpos($message, 'soy yo') !== false ||
             strpos($message, 'habla el titular') !== false;

$opciones = ['1', '2', '3', '4'];

// Funciones de respuesta
function menuSinConfirmar() {
    return "Hola, soy Carla del equipo de cobranzas de Naranja X. Para continuar necesito saber si estoy hablando con el titular de la cuenta. Por favor confirmalo para avanzar con la información.";
}
function menuConfirmado($nombre) {
    return "Hola $nombre, gracias por confirmar que sos el titular. Tu tarjeta presenta una deuda en instancia prelegal. Elegí una opción para avanzar:\n\n1. Ver medios de pago\n2. Conocer plan disponible\n3. Ya pagué\n4. No reconozco la deuda";
}
function respuesta1() {
    return "Podés abonar por:\n- App Naranja X\n- Home Banking (Link / Banelco)\n- Pago Fácil / Cobro Express / Rapipago\n- CBU o débito automático\n\nRecordá que siempre se sumarán intereses en el resumen del mes siguiente.";
}
function respuesta2() {
    return "Estás en instancia prelegal. Podés acceder al *Plan de Pago Total* o *Plan Excepción*, que financia toda la deuda pendiente.\n\n- El plástico queda inhabilitado hasta abonar el 60% del plan.\n- Los datos ya fueron informados al Banco Central.\n- Los débitos están suspendidos.\n- Siempre se aplicarán intereses en el próximo resumen.";
}
function respuesta3() {
    return "🙌 Gracias por informarlo. Indicá por favor:\n- Monto pagado\n- Medio de pago\n- Fecha\nAsí actualizamos nuestros registros.\nTené en cuenta que podrían verse reflejados intereses en el próximo resumen.";
}
function respuesta4() {
    return "Si no reconocés la deuda, podés iniciar un reclamo. Contactanos para más información.";
}

// Guardar en reporte
function registrarReporte($dni, $telefono, $detalle) {
    $fechaHora = date('Y-m-d H:i:s');
    $linea = "$dni;$fechaHora - $telefono $detalle\n";
    file_put_contents(__DIR__ . '/reporte_chats.csv', $linea, FILE_APPEND);
}

// Reglas de flujo
if (!$esTitular && in_array($message, $opciones)) {
    echo json_encode(["reply" => "Necesito que primero confirmes si sos el titular de la cuenta para poder darte información."], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!$esTitular && !in_array($message, $opciones)) {
    echo json_encode(["reply" => menuSinConfirmar()], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ya confirmó titularidad
switch ($message) {
    case '1':
    case 'ver medios de pago':
        $respuesta = respuesta1();
        registrarReporte($cliente['dni'], $senderBase, "solicitó ver medios de pago");
        break;
    case '2':
    case 'conocer plan disponible':
        $respuesta = respuesta2();
        registrarReporte($cliente['dni'], $senderBase, "solicitó conocer plan de pago");
        break;
    case '3':
    case 'ya pague':
    case 'ya pagué':
        $respuesta = respuesta3();
        registrarReporte($cliente['dni'], $senderBase, "informó que ya pagó");
        break;
    case '4':
    case 'no reconozco la deuda':
        $respuesta = respuesta4();
        registrarReporte($cliente['dni'], $senderBase, "indicó que no reconoce la deuda");
        break;
    default:
        $respuesta = menuConfirmado($cliente['nombre']);
        break;
}

echo json_encode(["reply" => $respuesta], JSON_UNESCAPED_UNICODE);
exit;
