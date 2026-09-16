<?php

class RateLimiter
{
    private $storagePath;
    private $windowSeconds;
    private $maxAttempts;

    public function __construct(
        $storagePath,
        $windowSeconds,
        $maxAttempts
    ) {
        $this->storagePath = rtrim($storagePath, '/\\');
        $this->windowSeconds = (int) $windowSeconds;
        $this->maxAttempts = (int) $maxAttempts;

        if (!is_dir($this->storagePath)) {
            if (
                !mkdir($this->storagePath, 0755, true) &&
                !is_dir($this->storagePath)
            ) {
                throw new RuntimeException(
                    'Unable to create rate limit storage.'
                );
            }
        }
    }

    public function consume($identifier)
    {
        $now = time();

        $hash = hash('sha256', (string) $identifier);

        $file = $this->storagePath . DIRECTORY_SEPARATOR . $hash . '.json';

        $handle = fopen($file, 'c+');

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to access rate limit storage.'
            );
        }

        try {

            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException(
                    'Unable to lock rate limit storage.'
                );
            }

            rewind($handle);

            $raw = stream_get_contents($handle);

            $data = [
                'window_started_at' => $now,
                'attempts' => 0,
            ];

            if ($raw !== false && trim($raw) !== '') {

                $decoded = json_decode($raw, true);

                if (
                    is_array($decoded) &&
                    isset(
                        $decoded['window_started_at'],
                        $decoded['attempts']
                    )
                ) {
                    $data = $decoded;
                }
            }

            $windowStartedAt = (int) $data['window_started_at'];
            $attempts = (int) $data['attempts'];

            /*
             * Ventana expirada:
             * empezamos un contador nuevo.
             */
            if (($now - $windowStartedAt) >= $this->windowSeconds) {
                $windowStartedAt = $now;
                $attempts = 0;
            }

            /*
             * Ya agotó el límite.
             */
            if ($attempts >= $this->maxAttempts) {

                $retryAfter =
                    $this->windowSeconds -
                    ($now - $windowStartedAt);

                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'retry_after' => max(1, $retryAfter),
                ];
            }

            $attempts++;

            $newData = [
                'window_started_at' => $windowStartedAt,
                'attempts' => $attempts,
            ];

            rewind($handle);
            ftruncate($handle, 0);

            fwrite(
                $handle,
                json_encode($newData)
            );

            fflush($handle);

            return [
                'allowed' => true,
                'remaining' => max(
                    0,
                    $this->maxAttempts - $attempts
                ),
                'retry_after' => 0,
            ];

        } finally {

            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}