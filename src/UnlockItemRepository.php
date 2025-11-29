<?php

class UnlockItemRepository
{
    private const TYPES = ['sticker', 'overlay', 'filter', 'frame'];
    private const RARITIES = ['common', 'uncommon', 'rare', 'legendary'];

    public function findAllWithEvent(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ui.*, e.name AS event_name FROM unlockable_items ui'
            . ' LEFT JOIN events e ON e.id = ui.event_id'
            . ' ORDER BY ui.created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM unlockable_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO unlockable_items (event_id, type, name, asset_path, css_filter, rarity, created_at)'
            . ' VALUES (:event_id, :type, :name, :asset_path, :css_filter, :rarity, NOW())'
        );
        $stmt->execute([
            'event_id' => $data['event_id'],
            'type' => $this->normalizeType($data['type'] ?? ''),
            'name' => $data['name'],
            'asset_path' => $data['asset_path'] ?? null,
            'css_filter' => $data['css_filter'] ?? null,
            'rarity' => $this->normalizeRarity($data['rarity'] ?? null),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE unlockable_items SET event_id=:event_id, type=:type, name=:name, asset_path=:asset_path,'
            . ' css_filter=:css_filter, rarity=:rarity WHERE id=:id'
        );
        $stmt->execute([
            'id' => $id,
            'event_id' => $data['event_id'],
            'type' => $this->normalizeType($data['type'] ?? ''),
            'name' => $data['name'],
            'asset_path' => $data['asset_path'] ?? null,
            'css_filter' => $data['css_filter'] ?? null,
            'rarity' => $this->normalizeRarity($data['rarity'] ?? null),
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM unlockable_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findAvailableForSession(int $eventId, int $sessionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT ui.* FROM unlockable_items ui'
            . ' LEFT JOIN unlocked_items_sessions uis ON uis.item_id = ui.id AND uis.session_id = :session_id'
            . ' WHERE ui.event_id IS NULL OR ui.event_id = :event_id OR uis.id IS NOT NULL'
        );
        $stmt->execute([
            'event_id' => $eventId,
            'session_id' => $sessionId,
        ]);
        return $stmt->fetchAll();
    }

    public function addUnlockedItems(int $sessionId, array $itemIds): void
    {
        if (empty($itemIds)) {
            return;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT IGNORE INTO unlocked_items_sessions (session_id, item_id, unlocked_at)'
            . ' VALUES (:session_id, :item_id, NOW())'
        );
        foreach ($itemIds as $itemId) {
            $stmt->execute(['session_id' => $sessionId, 'item_id' => $itemId]);
        }
    }

    private function normalizeType(string $type): string
    {
        return in_array($type, self::TYPES, true) ? $type : 'sticker';
    }

    private function normalizeRarity(?string $rarity): ?string
    {
        if ($rarity === null || $rarity === '') {
            return null;
        }
        return in_array($rarity, self::RARITIES, true) ? $rarity : null;
    }
}
