<?php

class UnlockCodeRepository
{
    private const TYPES = ['single_use', 'per_session', 'unlimited'];

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
}
