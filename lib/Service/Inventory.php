<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Vereinsinventar\Service;
use OCA\Vereinsinventar\Db\Store;
final class Inventory {
    public function __construct(private Store $store, private Access $access) {}
    public function listing(string $groupId, int $folder): array {
        $this->access->check($groupId);
        $this->folder($groupId, $folder);
        return $this->store->all($groupId);
    }
    public function get(string $groupId, int $id): array {
        $this->access->check($groupId);
        return $this->store->get($groupId, $id);
    }
    public function save(string $groupId, int $id, int $parent, string $kind, string $name, int $quantity, string $inventoryNo, string $location, string $notes): void {
        $this->access->check($groupId, true);
        if ($id < 0 || !in_array($kind, ['folder', 'item'], true)) {
            throw new \DomainException('Ungültiger Eintrag.', 400);
        }
        $old = $id > 0 ? $this->store->get($groupId, $id) : null;
        if ($old !== null && ($old['kind'] !== $kind || ($kind === 'folder' && $old['parent_id'] !== $parent))) {
            throw new \DomainException('Kategorien können nur umbenannt werden.', 400);
        }
        $this->folder($groupId, $parent);
        if ($quantity < 0 || $quantity > 1000000) {
            throw new \DomainException('Die Anzahl muss zwischen 0 und 1.000.000 liegen.', 400);
        }
        $name = $this->field($name, 200, 'Name');
        if ($name === '') {
            throw new \DomainException('Bitte einen Namen eingeben.', 400);
        }
        $this->store->save($groupId, $id, [
            'parent_id' => $parent, 'kind' => $kind, 'name' => $name,
            'quantity' => $kind === 'item' ? $quantity : 1,
            'inventory_no' => $kind === 'item' ? $this->field($inventoryNo, 100, 'Inventarnummer') : '',
            'location' => $kind === 'item' ? $this->field($location, 200, 'Standort') : '',
            'notes' => $kind === 'item' ? $this->field($notes, 5000, 'Notiz') : '',
        ]);
    }
    public function remove(string $groupId, int $id): void {
        $this->access->check($groupId, true);
        if ($this->store->get($groupId, $id)['kind'] !== 'item') {
            throw new \DomainException('Kategorien werden im MVP nicht gelöscht.', 400);
        }
        $this->store->remove($groupId, $id);
    }
    private function folder(string $groupId, int $id): void {
        if ($id === 0) {
            return;
        }
        if ($id < 0 || $this->store->get($groupId, $id)['kind'] !== 'folder') {
            throw new \DomainException('Ungültige Kategorie.', 400);
        }
    }
    private function field(string $value, int $limit, string $label): string {
        $value = trim($value);
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $limit || str_contains($value, "\0")) {
            throw new \DomainException($label . ': ungültiger oder zu langer Text.', 400);
        }
        return $value;
    }
}
