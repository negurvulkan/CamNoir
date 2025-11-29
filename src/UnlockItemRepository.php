<?php

class UnlockItemRepository
{
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
}
