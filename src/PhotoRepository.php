<?php

class PhotoRepository
{
    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO photos (event_id, session_id, picture_uuid, delete_code, file_path, is_approved, created_at)'
            . ' VALUES (:event_id, :session_id, :picture_uuid, :delete_code, :file_path, :is_approved, NOW())'
        );
        $stmt->execute([
            'event_id' => $data['event_id'],
            'session_id' => $data['session_id'],
            'picture_uuid' => $data['picture_uuid'],
            'delete_code' => $data['delete_code'],
            'file_path' => $data['file_path'],
            'is_approved' => $data['is_approved'] ?? 0,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function deleteByCode(string $deleteCode): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, file_path, event_id FROM photos WHERE delete_code = :code');
        $stmt->execute(['code' => $deleteCode]);
        $photo = $stmt->fetch();
        if (!$photo) {
            return null;
        }
        if (is_file($photo['file_path'])) {
            unlink($photo['file_path']);
        }
        $db->prepare('UPDATE photos SET deleted_at = NOW(), delete_request_status = :status WHERE id = :id')
            ->execute(['id' => $photo['id'], 'status' => 'approved']);
        return $photo;
    }

    public function findByEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM photos WHERE event_id = :event_id ORDER BY created_at DESC');
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll();
    }

    public function findPublicByEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM photos WHERE event_id = :event_id AND deleted_at IS NULL AND is_approved = 1"
            . " AND (delete_request_status IS NULL OR delete_request_status = 'rejected')"
            . ' ORDER BY created_at DESC'
        );
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll();
    }

    public function findApprovedSince(int $eventId, ?string $since = null): array
    {
        $query = "SELECT * FROM photos WHERE event_id = :event_id AND deleted_at IS NULL AND is_approved = 1"
            . " AND (delete_request_status IS NULL OR delete_request_status = 'rejected')";
        $params = ['event_id' => $eventId];
        if ($since) {
            $query .= ' AND created_at > :since';
            $params['since'] = $since;
        }
        $query .= ' ORDER BY created_at DESC';
        $stmt = Database::connection()->prepare($query);
        $stmt->execute($params);
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

    public function setApproval(int $photoId, bool $approved): void
    {
        $stmt = Database::connection()->prepare('UPDATE photos SET is_approved = :approved WHERE id = :id');
        $stmt->execute(['approved' => $approved ? 1 : 0, 'id' => $photoId]);
    }

    public function findById(int $photoId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM photos WHERE id = :id');
        $stmt->execute(['id' => $photoId]);
        $photo = $stmt->fetch();
        return $photo ?: null;
    }

    public function requestDeletionByCode(string $deleteCode, ?string $reason, ?string $note): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM photos WHERE delete_code = :code AND deleted_at IS NULL');
        $stmt->execute(['code' => $deleteCode]);
        $photo = $stmt->fetch();
        if (!$photo) {
            return null;
        }

        $db->prepare(
            'UPDATE photos SET delete_request_status = :status, delete_request_reason = :reason, delete_request_note = :note, delete_request_at = NOW()'
            . ' WHERE id = :id'
        )->execute([
            'status' => 'pending',
            'reason' => $reason ?: null,
            'note' => $note ?: null,
            'id' => $photo['id'],
        ]);

        return $this->findById((int) $photo['id']);
    }

    public function approveDeleteRequest(int $photoId): ?array
    {
        $photo = $this->findById($photoId);
        if (!$photo) {
            return null;
        }

        if (is_file($photo['file_path'])) {
            unlink($photo['file_path']);
        }

        $stmt = Database::connection()->prepare(
            'UPDATE photos SET deleted_at = NOW(), delete_request_status = :status WHERE id = :id'
        );
        $stmt->execute(['status' => 'approved', 'id' => $photoId]);

        return $this->findById($photoId);
    }

    public function rejectDeleteRequest(int $photoId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE photos SET delete_request_status = :status WHERE id = :id'
        );
        $stmt->execute(['status' => 'rejected', 'id' => $photoId]);
    }

    public function resetDeleteRequest(int $photoId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE photos SET delete_request_status = NULL, delete_request_reason = NULL, delete_request_note = NULL, delete_request_at = NULL'
            . ' WHERE id = :id'
        );
        $stmt->execute(['id' => $photoId]);
    }
}
