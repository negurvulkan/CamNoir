<?php

class UnlockCodeRepository
{
    private const TYPES = ['single_use', 'per_session', 'unlimited'];

    public function findAllWithDetails(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT uc.*, e.name AS event_name, COUNT(ucu.id) AS usage_count'
            . ' FROM unlock_codes uc'
            . ' LEFT JOIN events e ON e.id = uc.event_id'
            . ' LEFT JOIN unlock_code_usage ucu ON ucu.code_id = uc.id'
            . ' GROUP BY uc.id'
            . ' ORDER BY uc.created_at DESC'
        );
        $stmt->execute();
        $codes = $stmt->fetchAll();
        foreach ($codes as &$code) {
            $code['items'] = $this->findItemsForCode((int)$code['id']);
        }
        return $codes;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM unlock_codes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $row['items'] = $this->findItemsForCode((int)$row['id']);
        }
        return $row ?: null;
    }

    public function create(array $data, array $itemIds): int
    {
        $db = Database::connection();
        $db->beginTransaction();
        $stmt = $db->prepare(
            'INSERT INTO unlock_codes (code, description, type, expires_at, event_id, created_at)'
            . ' VALUES (:code, :description, :type, :expires_at, :event_id, NOW())'
        );
        $stmt->execute([
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'type' => $this->normalizeType($data['type'] ?? ''),
            'expires_at' => $data['expires_at'] ?? null,
            'event_id' => $data['event_id'],
        ]);
        $codeId = (int) $db->lastInsertId();
        $this->replaceCodeItems($codeId, $itemIds, $db);
        $db->commit();
        return $codeId;
    }

    public function update(int $id, array $data, array $itemIds): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        $stmt = $db->prepare(
            'UPDATE unlock_codes SET code=:code, description=:description, type=:type, expires_at=:expires_at, event_id=:event_id'
            . ' WHERE id=:id'
        );
        $stmt->execute([
            'id' => $id,
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'type' => $this->normalizeType($data['type'] ?? ''),
            'expires_at' => $data['expires_at'] ?? null,
            'event_id' => $data['event_id'],
        ]);
        $this->replaceCodeItems($id, $itemIds, $db);
        $db->commit();
    }

    public function delete(int $id): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM unlock_code_items WHERE code_id = :id')->execute(['id' => $id]);
        $db->prepare('DELETE FROM unlock_code_usage WHERE code_id = :id')->execute(['id' => $id]);
        $db->prepare('DELETE FROM unlock_codes WHERE id = :id')->execute(['id' => $id]);
    }

    public function findByCode(string $code): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM unlock_codes WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findItemsForCode(int $codeId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ui.* FROM unlock_code_items uci JOIN unlockable_items ui ON ui.id = uci.item_id WHERE uci.code_id = :code_id'
        );
        $stmt->execute(['code_id' => $codeId]);
        return $stmt->fetchAll();
    }

    public function hasSessionUsage(int $codeId, int $sessionId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM unlock_code_usage WHERE code_id = :code_id AND session_id = :session_id LIMIT 1'
        );
        $stmt->execute(['code_id' => $codeId, 'session_id' => $sessionId]);
        return (bool) $stmt->fetch();
    }

    public function countUsage(int $codeId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) AS cnt FROM unlock_code_usage WHERE code_id = :code_id');
        $stmt->execute(['code_id' => $codeId]);
        $row = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    public function logUsage(int $codeId, int $sessionId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO unlock_code_usage (code_id, session_id, redeemed_at) VALUES (:code_id, :session_id, NOW())'
        );
        $stmt->execute(['code_id' => $codeId, 'session_id' => $sessionId]);
    }

    public function normalizeType(string $type): string
    {
        return in_array($type, self::TYPES, true) ? $type : 'single_use';
    }

    private function replaceCodeItems(int $codeId, array $itemIds, \PDO $db): void
    {
        $db->prepare('DELETE FROM unlock_code_items WHERE code_id = :code_id')->execute(['code_id' => $codeId]);
        if (empty($itemIds)) {
            return;
        }
        $stmt = $db->prepare('INSERT INTO unlock_code_items (code_id, item_id) VALUES (:code_id, :item_id)');
        foreach ($itemIds as $itemId) {
            $stmt->execute(['code_id' => $codeId, 'item_id' => $itemId]);
        }
    }
}
