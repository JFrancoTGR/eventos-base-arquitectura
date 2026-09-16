<?php

class EventRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findBySlug($slug)
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                id,
                slug,
                name,
                starts_at,
                ends_at,
                registration_status,
                max_guests,
                registration_opens_at,
                registration_closes_at
             FROM events
             WHERE slug = ?
             LIMIT 1'
        );

        $stmt->execute([$slug]);

        $event = $stmt->fetch();

        return $event ?: null;
    }
}