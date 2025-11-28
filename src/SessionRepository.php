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
            'INSERT INTO sessions (event_id, session_token, photo_count, created_at, last_activity_at)'
            . ' VALUES (:event_id, :token, 0, NOW(), NOW())'
        );
        $stmt->execute(['event_id' => $eventId, 'token' => $token]);
        return (int) Database::connection()->lastInsertId();
    }

    public function incrementPhotoCount(int $sessionId): void
    {
        $stmt = Database::connection()->prepare('UPDATE sessions SET photo_count = photo_count + 1, last_activity_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $sessionId]);
    }

    public function countByEvent(int $eventId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) AS cnt FROM sessions WHERE event_id = :event_id');
        $stmt->execute(['event_id' => $eventId]);
        return (int) ($stmt->fetch()['cnt'] ?? 0);
    }

    public function findByEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM sessions WHERE event_id = :event_id ORDER BY last_activity_at DESC');
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll();
    }

    public function deleteByToken(string $token): array
    {
        $stmt = Database::connection()->prepare('SELECT id, event_id FROM sessions WHERE session_token = :token');
        $stmt->execute(['token' => $token]);
        $session = $stmt->fetch();
        if (!$session) {
            return ['deleted' => 0, 'event_id' => null];
        }
        $sessionId = (int) $session['id'];
        $eventId = (int) $session['event_id'];
        $db = Database::connection();
        $db->beginTransaction();
        $photoStmt = $db->prepare('SELECT id, file_path FROM photos WHERE session_id = :sid');
        $photoStmt->execute(['sid' => $sessionId]);
        foreach ($photoStmt->fetchAll() as $photo) {
            if (is_file($photo['file_path'])) {
                unlink($photo['file_path']);
            }
            $db->prepare('UPDATE photos SET deleted_at = NOW() WHERE id = :id')->execute(['id' => $photo['id']]);
        }
        $db->prepare('DELETE FROM sessions WHERE id = :sid')->execute(['sid' => $sessionId]);
        $db->commit();
        return ['deleted' => $photoStmt->rowCount(), 'event_id' => $eventId];
    }
}
