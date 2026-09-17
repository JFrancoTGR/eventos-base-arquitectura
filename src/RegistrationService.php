<?php

class RegistrationService
{
    private $pdo;
    private $eventRepository;
    private $timezone;

    public function __construct(
        PDO $pdo,
        EventRepository $eventRepository,
        $timezone = 'America/Mexico_City'
    ) {
        $this->pdo             = $pdo;
        $this->eventRepository = $eventRepository;
        $this->timezone        = $timezone;
    }

    public function register($eventSlug, array $attendees, array $meta = [])
    {
        $eventSlug = trim((string) $eventSlug);

        if ($eventSlug === '') {
            throw new RegistrationException(
                'EVENT_REQUIRED',
                'No se especificó el evento.',
                400
            );
        }

        /*
         * 1. Resolver evento
         */
        $event = $this->eventRepository->findBySlug($eventSlug);

        if (! $event) {
            throw new RegistrationException(
                'EVENT_NOT_FOUND',
                'El evento no existe.',
                404
            );
        }

        $registrantType = isset($meta['registrant_type'])
            ? trim((string) $meta['registrant_type'])
            : '';

        if (! in_array($registrantType, ['client', 'broker'], true)) {
            throw new RegistrationException(
                'INVALID_REGISTRANT_TYPE',
                'Debes indicar si eres cliente o broker.',
                422
            );
        }

        $meta['registrant_type'] = $registrantType;

        /*
         * 2. Validar estado
         */
        if ($event['registration_status'] !== 'open') {
            throw new RegistrationException(
                'REGISTRATION_CLOSED',
                'El registro para este evento no está disponible.',
                403
            );
        }

        /*
         * 3. Validar ventana temporal
         */
        $this->validateRegistrationWindow($event);

        /*
         * 4. Titular + máximo permitido de invitados
         */
        $attendeeCount = count($attendees);
        $maxTotal      = 1 + (int) $event['max_guests'];

        if ($attendeeCount < 1) {
            throw new RegistrationException(
                'ATTENDEE_REQUIRED',
                'Debes registrar al menos al titular.',
                422
            );
        }

        if ($attendeeCount > $maxTotal) {
            throw new RegistrationException(
                'TOO_MANY_ATTENDEES',
                'Se excedió el número de invitados permitido.',
                422
            );
        }

        /*
         * 5. Normalizar asistentes
         */
        $normalizedAttendees = [];

        $seenEmails     = [];
        $seenIdentities = [];

        foreach ($attendees as $position => $attendee) {

            if (! is_array($attendee)) {
                throw new RegistrationException(
                    'INVALID_ATTENDEE',
                    'Los datos de uno de los asistentes no son válidos.',
                    422
                );
            }

            $email = isset($attendee['email'])
                ? trim((string) $attendee['email'])
                : '';

            $phone = isset($attendee['phone'])
                ? trim((string) $attendee['phone'])
                : '';

            try {
                $emailNorm   = EmailNormalizer::normalize($email);
                $identityKey = EmailNormalizer::identityKey($emailNorm);
            } catch (InvalidArgumentException $e) {
                throw new RegistrationException(
                    'INVALID_EMAIL',
                    'Uno de los correos electrónicos no es válido.',
                    422
                );
            }

            try {
                $phoneNorm = PhoneNormalizer::normalize($phone);
            } catch (InvalidArgumentException $e) {
                throw new RegistrationException(
                    'INVALID_PHONE',
                    'Uno de los teléfonos no es válido.',
                    422
                );
            }

            /*
             * Duplicados dentro del propio formulario.
             */
            if (
                isset($seenEmails[$emailNorm]) ||
                isset($seenIdentities[$identityKey])
            ) {
                throw new RegistrationException(
                    'DUPLICATE_ATTENDEE',
                    'No puedes registrar el mismo correo más de una vez.',
                    409
                );
            }

            $firstName = isset($attendee['first_name'])
                ? trim((string) $attendee['first_name'])
                : '';

            $lastName = isset($attendee['last_name'])
                ? trim((string) $attendee['last_name'])
                : '';

            if (
                $firstName === '' ||
                $lastName === '' ||
                strlen($firstName) > 100 ||
                strlen($lastName) > 150
            ) {
                throw new RegistrationException(
                    'INVALID_NAME',
                    'Nombre y apellido son obligatorios para todos los asistentes.',
                    422
                );
            }

            $seenEmails[$emailNorm]       = true;
            $seenIdentities[$identityKey] = true;

            $normalizedAttendees[] = [
                'position'           => (int) $position,
                'email'              => $email,
                'email_norm'         => $emailNorm,
                'email_identity_key' => $identityKey,
                'phone'              => $phone,
                'phone_norm'         => $phoneNorm,
                'first_name'         => $firstName,
                'last_name'          => $lastName,
            ];
        }

        /*
         * 6. Buscar duplicados existentes en este evento.
         */
        $this->assertNoExistingAttendees(
            (int) $event['id'],
            $normalizedAttendees
        );

        /*
         * 7. Guardar todo dentro de una transacción.
         */
        try {
            $this->pdo->beginTransaction();

            $registrationId = $this->insertRegistration(
                (int) $event['id'],
                $meta
            );

            $this->insertAttendees(
                (int) $event['id'],
                $registrationId,
                $normalizedAttendees
            );

            $this->pdo->commit();

            return [
                'registration_id' => $registrationId,
                'event_id'        => (int) $event['id'],
                'attendees'       => count($normalizedAttendees),
            ];

        } catch (PDOException $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            /*
             * 23000 = integrity constraint violation.
             *
             * Nos protege también contra una condición de carrera:
             * dos requests intentando insertar el mismo email al mismo tiempo.
             */
            if ($e->getCode() === '23000') {
                throw new RegistrationException(
                    'DUPLICATE_ATTENDEE',
                    'Uno de los correos ya está registrado para este evento.',
                    409
                );
            }

            throw $e;
        }
    }

    private function validateRegistrationWindow(array $event)
    {
        $timezone = new DateTimeZone($this->timezone);
        $now      = new DateTimeImmutable('now', $timezone);

        if (! empty($event['registration_opens_at'])) {

            $opensAt = new DateTimeImmutable(
                $event['registration_opens_at'],
                $timezone
            );

            if ($now < $opensAt) {
                throw new RegistrationException(
                    'REGISTRATION_NOT_OPEN',
                    'El registro para este evento aún no está disponible.',
                    403
                );
            }
        }

        if (! empty($event['registration_closes_at'])) {

            $closesAt = new DateTimeImmutable(
                $event['registration_closes_at'],
                $timezone
            );

            if ($now > $closesAt) {
                throw new RegistrationException(
                    'REGISTRATION_CLOSED',
                    'El registro para este evento ha finalizado.',
                    403
                );
            }
        }
    }

    private function assertNoExistingAttendees(
        $eventId,
        array $attendees
    ) {
        $emailNorms   = [];
        $identityKeys = [];

        foreach ($attendees as $attendee) {
            $emailNorms[]   = $attendee['email_norm'];
            $identityKeys[] = $attendee['email_identity_key'];
        }

        $emailPlaceholders = implode(
            ',',
            array_fill(0, count($emailNorms), '?')
        );

        $identityPlaceholders = implode(
            ',',
            array_fill(0, count($identityKeys), '?')
        );

        $sql = '
            SELECT id
            FROM attendees
            WHERE event_id = ?
            AND (
                email_norm IN (' . $emailPlaceholders . ')
                OR email_identity_key IN (' . $identityPlaceholders . ')
            )
            LIMIT 1
        ';

        $params = array_merge(
            [$eventId],
            $emailNorms,
            $identityKeys
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            throw new RegistrationException(
                'DUPLICATE_ATTENDEE',
                'Uno de los correos ya está registrado para este evento.',
                409
            );
        }
    }

    private function insertRegistration($eventId, array $meta)
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO registrations (
        event_id,
        status,
        registrant_type,
        utm_source,
        utm_medium,
        utm_campaign,
        ip_address,
        user_agent,
        referrer
    ) VALUES (
        ?,
        "active",
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )'
        );

        $stmt->execute([
            $eventId,
            $meta['registrant_type'],
            $this->nullableString($meta, 'utm_source', 100),
            $this->nullableString($meta, 'utm_medium', 100),
            $this->nullableString($meta, 'utm_campaign', 150),
            $this->nullableString($meta, 'ip_address', 45),
            $this->nullableString($meta, 'user_agent', 500),
            $this->nullableString($meta, 'referrer', 500),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertAttendees(
        $eventId,
        $registrationId,
        array $attendees
    ) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO attendees (
    event_id,
    registration_id,
    attendee_position,
    first_name,
    last_name,
    email,
    email_norm,
    email_identity_key,
    phone,
    phone_norm
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($attendees as $attendee) {

            $stmt->execute([
                $eventId,
                $registrationId,
                $attendee['position'],
                $attendee['first_name'],
                $attendee['last_name'],
                $attendee['email'],
                $attendee['email_norm'],
                $attendee['email_identity_key'],
                $attendee['phone'],
                $attendee['phone_norm'],
            ]);
        }
    }

    private function nullableString(array $data, $key, $maxLength)
    {
        if (! isset($data[$key])) {
            return null;
        }

        $value = trim((string) $data[$key]);

        if ($value === '') {
            return null;
        }

        return substr($value, 0, $maxLength);
    }
}
