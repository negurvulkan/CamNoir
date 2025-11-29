<?php

class BonusCodeRepository
{
    private const TYPES = ['single_use', 'per_session', 'unlimited'];

    public function findByCode(string $code): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM bonus_codes WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM bonus_codes WHERE event_id = :event_id ORDER BY created_at DESC');
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO bonus_codes (code, description, extra_photos, type, max_uses, used_count, event_id, created_at, expires_at)'
            . ' VALUES (:code, :description, :extra_photos, :type, :max_uses, 0, :event_id, NOW(), :expires_at)'
        );
        $stmt->execute([
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'extra_photos' => $data['extra_photos'],
            'type' => $this->normalizeType($data['type'] ?? ''),
            'max_uses' => $data['max_uses'],
            'event_id' => $data['event_id'],
            'expires_at' => $data['expires_at'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE bonus_codes SET code=:code, description=:description, extra_photos=:extra_photos, type=:type, max_uses=:max_uses,'
            . ' expires_at=:expires_at WHERE id=:id'
        );
        $stmt->execute([
            'id' => $id,
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'extra_photos' => $data['extra_photos'],
            'type' => $this->normalizeType($data['type'] ?? ''),
            'max_uses' => $data['max_uses'],
            'expires_at' => $data['expires_at'],
        ]);
    }

    public function delete(int $id): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM bonus_code_sessions WHERE bonus_code_id = :id')->execute(['id' => $id]);
        $db->prepare('DELETE FROM bonus_codes WHERE id = :id')->execute(['id' => $id]);
    }

    public function incrementUsedCount(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE bonus_codes SET used_count = used_count + 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function hasSessionRedemption(int $codeId, int $sessionId): bool
    {
        $stmt = Database::connection()->prepare('SELECT id FROM bonus_code_sessions WHERE bonus_code_id = :code_id AND session_id = :session_id');
        $stmt->execute(['code_id' => $codeId, 'session_id' => $sessionId]);
        return (bool) $stmt->fetch();
    }

    public function logSessionRedemption(int $codeId, int $sessionId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO bonus_code_sessions (bonus_code_id, session_id, redeemed_at) VALUES (:code_id, :session_id, NOW())'
        );
        $stmt->execute(['code_id' => $codeId, 'session_id' => $sessionId]);
    }

    public function findRedemptionsForEvent(int $eventId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT bcs.id, bcs.bonus_code_id, bcs.session_id, bcs.redeemed_at, bc.code, s.session_token'
            . ' FROM bonus_code_sessions bcs'
            . ' JOIN bonus_codes bc ON bc.id = bcs.bonus_code_id'
            . ' LEFT JOIN sessions s ON s.id = bcs.session_id'
            . ' WHERE bc.event_id = :event_id'
            . ' ORDER BY bcs.redeemed_at DESC'
        );
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->fetchAll();
    }

    private function normalizeType(string $type): string
    {
        if (in_array($type, self::TYPES, true)) {
            return $type;
        }
        return 'single_use';
    }
}
