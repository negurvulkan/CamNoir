<?php

class DeleteLogRepository
{
    public function log(int $eventId, string $type, ?string $detail = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO delete_logs (event_id, type, detail, created_at) VALUES (:event_id, :type, :detail, NOW())'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'type' => $type,
            'detail' => $detail,
        ]);
    }

    public function countsForEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT type, COUNT(*) AS cnt FROM delete_logs WHERE event_id = :event_id GROUP BY type'
        );
        $stmt->execute(['event_id' => $eventId]);
        $rows = $stmt->fetchAll();
        $result = ['delete_code' => 0, 'session' => 0];
        foreach ($rows as $row) {
            $result[$row['type']] = (int) $row['cnt'];
        }
        return $result;
    }

    public function latestForEvent(int $eventId, int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT type, detail, created_at FROM delete_logs WHERE event_id = :event_id ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
