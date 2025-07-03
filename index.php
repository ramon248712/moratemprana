<?php
date_default_timezone_set("America/Argentina/Buenos_Aires");
header('Content-Type: application/json; charset=utf-8');

$app     = $_POST["app"] ?? '';
$sender  = preg_replace('/\D/', '', $_POST["sender"] ?? '');
$message = strtolower(trim($_POST["message"] ?? ''));
$senderBase = substr($sender, -10);

if (strlen($senderBase) != 10) {
    echo json_encode(["reply" => ""]);
    exit;
}

$csvFile       = __DIR__ . '/deudores.csv';
$reporteChats  = __DIR__ . '/reporte_chats.csv';
$titularesFile = __DIR__ . '/titulares_confirmados.csv';

// Cargar clientes
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

// Buscar cliente por teléfono
$cliente = null;
foreach ($clientes as $i => $c) {
    if ($c['telefono'] === $senderBase) {
        $cliente = $clientes[$i];
        break;
    }
}

// Si no se encuentra por teléfono, permitir identificar por DNI
if (!$cliente && is_numeric($message) && strlen($message) >= 7) {
    foreach ($clientes as $i => $c) {
        if ($c['dni'] === $message) {
            $clientes[$i]['telefono'] = $senderBase;
            $cliente = $clientes[$i];
            // Actualizar CSV con nuevo número
            $f = fopen($csvFile, 'w');
            foreach ($clientes as $line) {
                fputcsv($f, [$line['nombre'], $line['dni'], $line['telefono']], ';');
            }
            fclose($f);
            break;
        }
    }
}

if (!$cliente) {
    echo json_encode([
        "reply" => "Hola. Para poder ayudarte, por favor escribí tu DNI (solo números)."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Confirmación de titularidad
$titularesConfirmados = [];
if (file_exists($titularesFile)) {
    $titularesConfirmados = file($titularesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}
$yaConfirmado = in_array($senderBase, $titularesConfirmados);

$esTitularAhora = strpos($message, 'soy el titular') !== false ||
                  strpos($message, 'si soy') !== false ||
                  strpos($message, 'soy yo') !== false ||
                  strpos($message, 'habla el titular') !== false;

if ($esTitularAhora && !$yaConfirmado) {
    file_put_contents($titularesFile, $senderBase . "\n", FILE_APPEND);
    $yaConfirmado = true;

    // Enviar mensaje partido
    echo json_encode([
        "reply" => "{$cliente['nombre']}, gracias por confirmar que sos el titular. Tu tarjeta presenta una deuda en instancia prelegal."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Opciones válidas
$opciones = ['1', '2', '3', '4'];

// Funciones de respuesta
function menuSinConfirmar() {
    return "Hola, soy Yne de Naranja X. Para continuar necesito saber si estoy hablando con el titular de la cuenta. Por favor escribí: *Si soy* para avanzar.";
}
function menuOpciones() {
    return "Elegí una opción para avanzar:\n\n1. Ver medios de pago\n2. Conocer plan disponible\n3. Ya pagué\n4. No reconozco la deuda";
}
function respuesta1() {
    return "💳 *Medios de pago disponibles:*\n\n✅ Recomendado: *App Naranja X*\n- Tocá 'Pagar tu resumen'\n- Elegí 'Con tu dinero en cuenta'\n\n📺 Instructivo paso a paso:\nhttps://www.youtube.com/watch?v=nx170-vVAGs&list=PL-e3bYhlJzeYqvSdFgrqB_NjOXe0EFXmu\n\n🏦 Otras opciones:\n- Home Banking (Red Link o Banelco, usando el OCR de tu tarjeta Naranja Clásica que empieza con 5895)\n- Pago Fácil / Cobro Express / Aseguradora San Juan (1% recargo)\n\n❌ *No se acepta Rapipago*.";
}
function respuesta2() {
    return "Estás en instancia *prelegal*. Podés acceder al *Plan de Pago Total*, que financia toda la deuda.\n\n📌 *¿Cómo pagás?* Usá la App Naranja X:\n1. Entrá a la app\n2. Tocá 'Pagar tu resumen'\n3. Elegí 'Con tu dinero en cuenta'\n4. Confirmá\n\n📺 Tutorial:\nhttps://www.youtube.com/watch?v=nx170-vVAGs\n\n📲 Alternativas:\n- Home Banking (Link/Banelco)\n- Pago Fácil / Cobro Express / Aseguradora San Juan\n\n⛔ Si no regularizás, la cuenta puede pasar a abogados con intereses y honorarios.";
}
function respuesta3() {
    return "🙌 Gracias por informarlo. En breve actualizaremos nuestros registros.\nTené en cuenta que podrían verse reflejados intereses en el próximo resumen.";
}
function respuesta4() {
    return "Si no reconocés la deuda, podés iniciar un reclamo. Contactanos para más información.";
}
function registrarReporte($dni, $telefono, $detalle) {
    $fechaHora = date('Y-m-d H:i:s');
    file_put_contents(__DIR__ . '/reporte_chats.csv', "$dni;$fechaHora - $telefono $detalle\n", FILE_APPEND);
}

// Flujo sin confirmación
if (!$yaConfirmado && in_array($message, $opciones)) {
    echo json_encode(["reply" => "Necesito que primero confirmes si sos el titular. Escribí: *Si soy*"], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!$yaConfirmado && !in_array($message, $opciones)) {
    echo json_encode(["reply" => menuSinConfirmar()], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ya confirmado
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
        $respuesta = menuOpciones(); // Si responde algo fuera de menú, se reenvía menú
        break;
}

echo json_encode(["reply" => $respuesta], JSON_UNESCAPED_UNICODE);
exit;
