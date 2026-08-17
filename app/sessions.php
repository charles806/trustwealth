<?php
declare(strict_types=1);

final class DbSessionHandler implements SessionHandlerInterface
{
    private ?PDO $pdo = null;

    private function db(): ?PDO
    {
        if ($this->pdo === null) {
            try {
                $this->pdo = db();
            } catch (Throwable $er) {
                error_log('session handler db: ' . $er->getMessage());

                return null;
            }
        }

        return $this->pdo;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return '';
        }

        try {
            $stmt = $pdo->prepare('SELECT data FROM sessions WHERE id = ? AND last_activity >= ? LIMIT 1');
            $stmt->execute([$id, time() - $this->lifetime()]);
            $data = $stmt->fetchColumn();

            return $data === false ? '' : (string) $data;
        } catch (Throwable $er) {
            error_log('session read: ' . $er->getMessage());

            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
            );
            $stmt->execute([$id, $data, time()]);

            return true;
        } catch (Throwable $er) {
            error_log('session write: ' . $er->getMessage());

            return false;
        }
    }

    public function destroy(string $id): bool
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE id = ?');
            $stmt->execute([$id]);

            return true;
        } catch (Throwable $er) {
            error_log('session destroy: ' . $er->getMessage());

            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        $pdo = $this->db();
        if ($pdo === null) {
            return false;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE last_activity < ?');
            $stmt->execute([time() - $max_lifetime]);

            return $stmt->rowCount();
        } catch (Throwable $er) {
            error_log('session gc: ' . $er->getMessage());

            return false;
        }
    }

    public function create_sid(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function lifetime(): int
    {
        $gc = (int) ini_get('session.gc_maxlifetime');

        return $gc > 0 ? $gc : 604800;
    }
}

function register_db_session_handler(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    try {
        _ensure_sessions_table(db());
    } catch (Throwable $er) {
        error_log('session handler setup: ' . $er->getMessage());
    }

    session_set_save_handler(new DbSessionHandler(), true);
}