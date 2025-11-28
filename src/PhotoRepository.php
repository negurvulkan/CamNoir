<?php

class PhotoRepository
{
    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO photos (event_id, session_id, picture_uuid, delete_code, file_path, created_at)
            VALUES (:event_id, :session_id, :picture_uuid, :delete_code, :file_path, NOW())'
        );
        $stmt->execute([
            'event_id' => $data['event_id'],
            'session_id' => $data['session_id'],
            'picture_uuid' => $data['picture_uuid'],
            'delete_code' => $data['delete_code'],
            'file_path' => $data['file_path'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function deleteByCode(string $deleteCode): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, file_path FROM photos WHERE delete_code = :code');
        $stmt->execute(['code' => $deleteCode]);
        $photo = $stmt->fetch();
        if (!$photo) {
            return false;
        }
        if (is_file($photo['file_path'])) {
            unlink($photo['file_path']);
        }
        $db->prepare('DELETE FROM photos WHERE id = :id')->execute(['id' => $photo['id']]);
        return true;
    }

    public function findByEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM photos WHERE event_id = :event_id ORDER BY created_at DESC');
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll();
    }

    public function findPublicByEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM photos WHERE event_id = :event_id AND deleted_at IS NULL ORDER BY created_at DESC');
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll();
    }

    public function countByEvent(int $eventId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) AS cnt FROM photos WHERE event_id = :event_id');
        $stmt->execute(['event_id' => $eventId]);
        return (int) ($stmt->fetch()['cnt'] ?? 0);
    }

    public function latestUploadAt(int $eventId): ?string
    {
        $stmt = Database::connection()->prepare('SELECT created_at FROM photos WHERE event_id = :event_id ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['event_id' => $eventId]);
        $row = $stmt->fetch();
        return $row['created_at'] ?? null;
    }

    public function findByIdsForEvent(int $eventId, array $photoIds): array
    {
        if (empty($photoIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
        $stmt = Database::connection()->prepare("SELECT * FROM photos WHERE event_id = ? AND id IN ($placeholders)");
        $stmt->execute(array_merge([$eventId], $photoIds));
        return $stmt->fetchAll();
    }

    public function averagePerSession(int $eventId): float
    {
        $stmt = Database::connection()->prepare('SELECT AVG(photo_count) AS avg_photos FROM sessions WHERE event_id = :event_id');
        $stmt->execute(['event_id' => $eventId]);
        return (float) ($stmt->fetch()['avg_photos'] ?? 0.0);
    }

    public function findByUuid(string $uuid): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM photos WHERE picture_uuid = :uuid');
        $stmt->execute(['uuid' => $uuid]);
        $photo = $stmt->fetch();
        return $photo ?: null;
    }

    public function deleteOlderThan(int $eventId, int $days): int
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, file_path FROM photos WHERE event_id = :event_id AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)');
        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $photos = $stmt->fetchAll();
        foreach ($photos as $photo) {
            if (is_file($photo['file_path'])) {
                unlink($photo['file_path']);
            }
            $db->prepare('DELETE FROM photos WHERE id = :id')->execute(['id' => $photo['id']]);
        }
        return count($photos);
    }
}
