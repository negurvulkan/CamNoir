<?php

class SessionRepository
{
    public function findByToken(string $token): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM sessions WHERE session_token = :token');
        $stmt->execute(['token' => $token]);
        $session = $stmt->fetch();
        return $session ?: null;
    }

    public function create(int $eventId, string $token): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO sessions (event_id, session_token, photo_count, created_at, last_activity_at)
             VALUES (:event_id, :token, 0, NOW(), NOW())'
        );
        $stmt->execute(['event_id' => $eventId, 'token' => $token]);
        return (int) Database::connection()->lastInsertId();
    }

    public function incrementPhotoCount(int $sessionId): void
    {
        $stmt = Database::connection()->prepare('UPDATE sessions SET photo_count = photo_count + 1, last_activity_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $sessionId]);
    }

    public function deleteByToken(string $token): int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM sessions WHERE session_token = :token');
        $stmt->execute(['token' => $token]);
        $session = $stmt->fetch();
        if (!$session) {
            return 0;
        }
        $sessionId = (int) $session['id'];
        $db = Database::connection();
        $db->beginTransaction();
        $photoStmt = $db->prepare('SELECT file_path FROM photos WHERE session_id = :sid');
        $photoStmt->execute(['sid' => $sessionId]);
        foreach ($photoStmt->fetchAll() as $photo) {
            if (is_file($photo['file_path'])) {
                unlink($photo['file_path']);
            }
        }
        $db->prepare('DELETE FROM photos WHERE session_id = :sid')->execute(['sid' => $sessionId]);
        $db->prepare('DELETE FROM sessions WHERE id = :sid')->execute(['sid' => $sessionId]);
        $db->commit();
        return $photoStmt->rowCount();
    }
}
