<?php
declare(strict_types=1);

final class DbSessionHandler implements SessionHandlerInterface
{
    private ?SessionHandler $files = null;

    private bool $useFiles = false;

    private string $sessionName = '';

    private string $savePath = '';

    public function open(string $path, string $name): bool
    {
        $this->savePath = $path;
        $this->sessionName = $name;

        return true;
    }

    public function close(): bool
    {
        if ($this->files !== null) {
            return $this->files->close();
        }

        return true;
    }

    public function read(string $id): string
    {
        if ($this->useFiles) {
            return $this->fileRead($id);
        }

        for ($try = 0; $try < 2; $try++) {
            try {
                $stmt = db($try > 0)->prepare(
                    'SELECT data FROM sessions WHERE id = ? AND last_activity >= ? LIMIT 1'
                );
                $stmt->execute([$id, time() - $this->lifetime()]);
                $data = $stmt->fetchColumn();

                return $data === false ? '' : (string) $data;
            } catch (Throwable $er) {
                error_log('session read: ' . $er->getMessage());
            }
        }

        $this->useFiles = true;

        return $this->fileRead($id);
    }

    public function write(string $id, string $data): bool
    {
        if ($this->useFiles) {
            return $this->fileWrite($id, $data);
        }

        for ($try = 0; $try < 2; $try++) {
            try {
                $stmt = db($try > 0)->prepare(
                    'INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
                );
                $stmt->execute([$id, $data, time()]);

                return true;
            } catch (Throwable $er) {
                error_log('session write: ' . $er->getMessage());
            }
        }

        $this->useFiles = true;

        return $this->fileWrite($id, $data);
    }

    public function destroy(string $id): bool
    {
        if ($this->useFiles) {
            return $this->fileDestroy($id);
        }

        for ($try = 0; $try < 2; $try++) {
            try {
                db($try > 0)->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);

                return true;
            } catch (Throwable $er) {
                error_log('session destroy: ' . $er->getMessage());
            }
        }

        $this->useFiles = true;

        return $this->fileDestroy($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        if ($this->useFiles) {
            $result = $this->fileHandler()?->gc($max_lifetime);

            return $result === false ? 0 : $result;
        }

        for ($try = 0; $try < 2; $try++) {
            try {
                $stmt = db($try > 0)->prepare('DELETE FROM sessions WHERE last_activity < ?');
                $stmt->execute([time() - $max_lifetime]);

                return $stmt->rowCount();
            } catch (Throwable $er) {
                error_log('session gc: ' . $er->getMessage());
            }
        }

        return 0;
    }

    public function create_sid(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function fileHandler(): ?SessionHandler
    {
        if ($this->files !== null) {
            return $this->files;
        }

        $path = $this->savePath;
        if ($path === '' || !is_dir($path) || !is_writable($path)) {
            $path = sys_get_temp_dir();
        }

        $handler = new SessionHandler();
        if (!$handler->open($path, $this->sessionName)) {
            error_log('session fallback: cannot open file session path ' . $path);

            return null;
        }

        $this->files = $handler;

        return $handler;
    }

    private function fileRead(string $id): string
    {
        $data = $this->fileHandler()?->read($id);

        return $data === false || $data === null ? '' : $data;
    }

    private function fileWrite(string $id, string $data): bool
    {
        return $this->fileHandler()?->write($id, $data) ?? false;
    }

    private function fileDestroy(string $id): bool
    {
        return $this->fileHandler()?->destroy($id) ?? false;
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
