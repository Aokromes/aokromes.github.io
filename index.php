<?php
// 1. Configuración de seguridad y diccionarios
$paises_hispanos = [
    'AR', 'BO', 'CL', 'CO', 'CR', 'CU', 'DO', 'EC', 'SV', 'ES', 
    'GT', 'HN', 'MX', 'NI', 'PA', 'PY', 'PE', 'PR', 'UY', 'VE', 'GQ'
];

$blacklist_organizaciones = [
    'google', 'amazon', 'aws', 'microsoft', 'azure', 'digitalocean', 'linode', 
    'hetzner', 'ovh', 'cloudflare', 'fastly', 'akamai', 'vpn', 'hosting', 'datacenter', 'm247'
];

// 2. Obtener la IP real del visitante (manejando proxies como Cloudflare o Reverse Proxies)
$client_ip = $_SERVER['REMOTE_ADDR'];
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $client_ip = trim($ips[0]);
}

// 3. Consultar a ipapi.co de forma interna (Servidor a Servidor)
$api_url = "https://ipapi.co" . urlencode($client_ip) . "/json/";

// Configurar un timeout para evitar que la web se quede colgada si la API externa tarda en responder
$options = ['http' => ['timeout' => 5, 'user_agent' => 'Mozilla/5.0']];
$context = stream_context_create($options);
$response = @file_get_contents($api_url, false, $context);

$acceso_permitido = false;
$motivo_rechazo = "Error al verificar metadatos de red.";
$pais_nombre = "";
$isp_nombre = "";

if ($response !== false) {
    $data = json_decode($response, true);
    
    if ($data && !isset($data['error'])) {
        $pais_codigo = isset($data['country_code']) ? $data['country_code'] : '';
        $pais_nombre = isset($data['country_name']) ? $data['country_name'] : '';
        $isp_nombre = isset($data['org']) ? $data['org'] : '';
        $isp_lower = strtolower($isp_nombre);

        // Validar si pertenece a un Datacenter, Hosting o VPN
        $es_datacenter_o_vpn = false;
        foreach ($blacklist_organizaciones as $keyword) {
            if (strpos($isp_lower, $keyword) !== false) {
                $es_datacenter_o_vpn = true;
                break;
            }
        }

        if ($es_datacenter_o_vpn) {
            $motivo_rechazo = "Conexión rechazada: Se detectó un Datacenter o VPN.";
        } elseif (!in_array($pais_codigo, $paises_hispanos)) {
            $motivo_rechazo = "Conexión rechazada: País no autorizado.";
        } else {
            // SI CUMPLE TODOS LOS REQUISITOS
            $acceso_permitido = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Acceso Seguro</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .card {
            background-color: var(--card-bg);
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        h2 { margin-top: 0; font-size: 1.5rem; }
        
        .status {
            font-size: 1.25rem;
            font-weight: bold;
            margin: 1.5rem 0;
            padding: 0.75rem;
            border-radius: 0.5rem;
        }

        .permitido { background-color: rgba(16, 185, 129, 0.15); color: var(--success); }
        .rechazado { background-color: rgba(239, 68, 68, 0.15); color: var(--danger); }

        .info-group {
            text-align: left;
            margin-bottom: 1.5rem;
            border-top: 1px solid #334155;
            padding-top: 1rem;
        }

        .info-item {
            margin: 0.5rem 0;
            font-size: 0.95rem;
        }

        .label { color: var(--text-muted); font-weight: 500; }

        .btn {
            display: inline-block;
            background-color: var(--success);
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 600;
            width: 100%;
            box-sizing: border-box;
            transition: opacity 0.2s;
        }

        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>

<div class="card">
    <h2>Sistema de Verificación</h2>

    <?php if ($acceso_permitido): ?>
        <!-- Este bloque SOLO se envía al navegador si pasa el filtro. La URL nace aquí -->
        <div class="status permitido">Conexión Permitida</div>
        <div class="info-group">
            <div class="info-item"><span class="label">País:</span> <?php echo htmlspecialchars($pais_nombre); ?></div>
            <div class="info-item"><span class="label">Proveedor (ISP):</span> <?php echo htmlspecialchars($isp_nombre); ?></div>
        </div>
        <a href="https://google.es" class="btn">Acceder al Sitio</a>

    <?php else: ?>
        <!-- Si no cumple los requisitos, el código HTML del botón y la URL de Google jamás se generan -->
        <div class="status rechazado">Conexión  Rechazada</div>
        <p style="color: #94a3b8; font-size: 0.9rem;"><?php echo htmlspecialchars($motivo_rechazo); ?></p>
    <?php endif; ?>

</div>

</body>
</html>
