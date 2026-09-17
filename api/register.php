<?php

header('Content-Type: application/json; charset=utf-8');

function respond($status, array $data)
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
 * Sólo POST.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    respond(405, [
        'ok'      => false,
        'code'    => 'METHOD_NOT_ALLOWED',
        'message' => 'Método no permitido.',
    ]);
}

/*
 * Evitamos bodies absurdamente grandes.
 */
$contentLength = isset($_SERVER['CONTENT_LENGTH'])
    ? (int) $_SERVER['CONTENT_LENGTH']
    : 0;

if ($contentLength > 32768) {

    respond(413, [
        'ok'      => false,
        'code'    => 'REQUEST_TOO_LARGE',
        'message' => 'La solicitud es demasiado grande.',
    ]);
}

try {

    require_once __DIR__ . '/../src/bootstrap.php';

    $rawBody = file_get_contents('php://input');

    if ($rawBody === false) {
        respond(400, [
            'ok'      => false,
            'code'    => 'INVALID_REQUEST',
            'message' => 'La solicitud no es válida.',
        ]);
    }

    if (strlen($rawBody) > 32768) {
        respond(413, [
            'ok'      => false,
            'code'    => 'REQUEST_TOO_LARGE',
            'message' => 'La solicitud es demasiado grande.',
        ]);
    }

    $payload = json_decode($rawBody, true);

    if (! is_array($payload)) {

        respond(400, [
            'ok'      => false,
            'code'    => 'INVALID_JSON',
            'message' => 'La solicitud no es válida.',
        ]);
    }

    /*
     * Honeypot.
     */
    if (
        isset($payload['website']) &&
        trim((string) $payload['website']) !== ''
    ) {

        /*
         * Respondemos de forma neutra.
         * El bot no necesita saber que fue detectado.
         */
        respond(200, [
            'ok'   => true,
            'code' => 'REGISTERED',
        ]);
    }

    $ipAddress = isset($_SERVER['REMOTE_ADDR'])
        ? $_SERVER['REMOTE_ADDR']
        : 'unknown';

    $rateLimiter = new RateLimiter(
        __DIR__ . '/../storage/ratelimit',
        isset($config['security']['rate_limit_window'])
            ? $config['security']['rate_limit_window']
            : 300,
        isset($config['security']['rate_limit_max_attempts'])
            ? $config['security']['rate_limit_max_attempts']
            : 20
    );

    $rate = $rateLimiter->consume(
        'register|' . $ipAddress
    );

    if (! $rate['allowed']) {

        header(
            'Retry-After: ' .
            (int) $rate['retry_after']
        );

        respond(429, [
            'ok'      => false,
            'code'    => 'RATE_LIMITED',
            'message' =>
            'Se han realizado demasiados intentos. Intenta nuevamente más tarde.',
        ]);
    }

    $eventSlug = isset($payload['event'])
        ? $payload['event']
        : '';

    $attendees = isset($payload['attendees'])
    && is_array($payload['attendees'])
        ? $payload['attendees']
        : [];

    $utm = isset($payload['utm'])
    && is_array($payload['utm'])
        ? $payload['utm']
        : [];

    $registrantType = isset($payload['registrant_type'])
        ? trim((string) $payload['registrant_type'])
        : '';

    $meta = [
        'utm_source'      => isset($utm['source'])
            ? $utm['source']
            : null,

        'utm_medium'      => isset($utm['medium'])
            ? $utm['medium']
            : null,

        'utm_campaign'    => isset($utm['campaign'])
            ? $utm['campaign']
            : null,

        'ip_address'      => $ipAddress,

        'registrant_type' => $registrantType,

        'user_agent'      => isset($_SERVER['HTTP_USER_AGENT'])
            ? $_SERVER['HTTP_USER_AGENT']
            : null,

        'referrer'        => isset($_SERVER['HTTP_REFERER'])
            ? $_SERVER['HTTP_REFERER']
            : null,
    ];

    $eventRepository = new EventRepository($pdo);

    $registrationService = new RegistrationService(
        $pdo,
        $eventRepository,
        isset($config['app']['timezone'])
            ? $config['app']['timezone']
            : 'America/Mexico_City'
    );

    $result = $registrationService->register(
        $eventSlug,
        $attendees,
        $meta
    );

    respond(201, [
        'ok'              => true,
        'code'            => 'REGISTERED',
        'registration_id' => $result['registration_id'],
        'attendees'       => $result['attendees'],
    ]);

} catch (RegistrationException $e) {

    respond($e->getHttpStatus(), [
        'ok'      => false,
        'code'    => $e->getErrorCode(),
        'message' => $e->getMessage(),
    ]);

} catch (Throwable $e) {

    /*
     * Detalle sólo en log del servidor.
     */
    error_log(
        '[events/register] ' .
        $e->getMessage()
    );

    respond(500, [
        'ok'      => false,
        'code'    => 'SERVER_ERROR',
        'message' => 'No fue posible completar el registro.',
    ]);
}
