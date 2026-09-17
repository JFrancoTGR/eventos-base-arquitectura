<?php

    require_once __DIR__ . '/src/bootstrap.php';

    $eventSlug = isset($_GET['event'])
    ? trim((string) $_GET['event'])
    : '';

    $event = null;

    if ($eventSlug !== '') {
    $eventRepository = new EventRepository($pdo);
    $event           = $eventRepository->findBySlug($eventSlug);
    }

    if (
    ! $event ||
    $event['registration_status'] !== 'open'
    ) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Evento no disponible</title>
        <link rel="stylesheet" href="assets/css/app.css">
    </head>
    <body>
        <main class="event-wrapper">
            <h1>Registro no disponible</h1>
            <p>El evento solicitado no se encuentra disponible.</p>
        </main>
    </body>
    </html>
    <?php
        exit;
        }

        $maxGuests = (int) $event['max_guests'];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Registro | <?php echo htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?>
    </title>

    <link rel="stylesheet" href="assets/css/app.css">

    <!-- Si ya utilizamos SweetAlert en el proyecto anterior -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

<header class="siteHeader">
    <div class="headerContainer">
        <img
            src="assets/img/logo-eu.svg"
            class="logo"
            alt="Estrategia Urbana"
        >
    </div>
</header>

<main class="formContainer">

    <section class="infoContainer">
        <h2 class="title">
            <?php echo htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <p class="description">
            Registra tus datos para confirmar tu asistencia.
        </p>
    </section>

    <section class="formContent">

        <form
            id="registration-form"
            data-event="<?php echo htmlspecialchars($event['slug'], ENT_QUOTES, 'UTF-8') ?>"
            data-max-guests="<?php echo $maxGuests ?>"
            novalidate
        >

            <div class="dataContainer">
    <p class="formSubtitle">Tus datos</p>

    <input
        type="text"
        id="primary-first-name"
        placeholder="Nombre"
        autocomplete="given-name"
        required
    >

    <input
        type="text"
        id="primary-last-name"
        placeholder="Apellidos"
        autocomplete="family-name"
        required
    >

    <input
        type="email"
        id="primary-email"
        placeholder="Correo electrónico"
        autocomplete="email"
        required
    >

    <input
        type="tel"
        id="primary-phone"
        placeholder="Teléfono"
        autocomplete="tel"
        required
    >

    <div class="labelsContainer">
    <label class="cliente">
        <input
            type="radio"
            name="registrant_type"
            value="client"
            required
        >
        Soy cliente
    </label>

    <label class="broker">
        <input
            type="radio"
            name="registrant_type"
            value="broker"
            required
        >
        Soy broker
    </label>
</div>
</div>

            <div id="guests-container"></div>

            <?php if ($maxGuests > 0): ?>
                <button
                    type="button"
                    id="add-guest"
                >
                    + Agregar invitado
                </button>
            <?php endif; ?>

            <div
                aria-hidden="true"
                style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"
            >
                <input
                    type="text"
                    id="website"
                    name="website"
                    tabindex="-1"
                    autocomplete="off"
                >
            </div>

            <button
                type="submit"
                id="submit-registration"
            >
                Registrar asistencia
            </button>

        </form>

    </section>

</main>

<script src="assets/js/register.js"></script>

</body>
</html>