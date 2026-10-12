<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
// Kleine Test-Doubles ausschließlich für die verwendeten öffentlichen OCP-Verträge.
namespace OCP {
    interface IConfig { public function getAppValue($app, $key, $default = ''); }
    interface IGroupManager { public function getUserGroups($user); public function isInGroup($uid, $gid); public function isAdmin($uid); public function get($gid); }
    interface IUserSession { public function getUser(); }
    interface IDBConnection {}
    interface IRequest {}
    interface IURLGenerator {}
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require __DIR__ . '/../lib/Db/Store.php';
    require __DIR__ . '/../lib/Service/Access.php';
    require __DIR__ . '/../lib/Service/Inventory.php';
    final class Config implements \OCP\IConfig {
        public string $value = '[]';
        public string $editors = '{}';
        public string $allowed = '["verein-a","verein-b"]';
        public function getAppValue($app, $key, $default = '') { return match ($key) { 'allowed_groups' => $this->allowed, 'editor_groups' => $this->editors, default => $this->value };  }
        public function setAppValue($app, $key, $value) { if ($key === 'allowed_groups') { $this->allowed = $value; } elseif ($key === 'editor_groups') { $this->editors = $value; } else { $this->value = $value; } }
    }
    final class User { public function getUID() { return 'mitglied'; } }
    final class Session implements \OCP\IUserSession {
        public bool $loggedIn = true;
        public function getUser() { return $this->loggedIn ? new User() : null; }
    }
    final class Groups implements \OCP\IGroupManager {
        public array $ids = ['verein-a'];
        public bool $admin = false;
        public function isAdmin($uid) { return $this->admin; }
        public function get($gid) {
            if (!in_array($gid, ['verein-a', 'verein-b', 'verwaltung'], true)) { return null; }
            return new class($gid) {
                public function __construct(private string $id) {}
                public function getGID() { return $this->id; }
                public function getDisplayName() { return $this->id; }
            };
        }
        public function getUserGroups($user) { return []; }
        public function isInGroup($uid, $gid) { return in_array($gid, $this->ids, true); }
    }
    final class MemoryStore extends \OCA\Vereinsinventar\Db\Store {
        public array $rows = [];
        public function __construct() {}
        public function all(string $groupId): array { return array_values(array_filter($this->rows, fn($r) => $r['group_id'] === $groupId)); }
        public function get(string $groupId, int $id): array {
            if (!isset($this->rows[$id]) || $this->rows[$id]['group_id'] !== $groupId) { throw new \DomainException('Eintrag nicht gefunden.', 404); }
            return $this->rows[$id];
        }
        public function save(string $groupId, int $id, array $data): void {
            $id = $id ?: ($this->rows === [] ? 1 : max(array_keys($this->rows)) + 1);
            $this->rows[$id] = array_merge($data, ['id' => $id, 'group_id' => $groupId]);
        }
        public function remove(string $groupId, int $id): void { $this->get($groupId, $id); unset($this->rows[$id]); }
    }
    $count = 0;
    function expect(bool $condition, string $label): void {
        global $count;
        if (!$condition) { throw new \RuntimeException($label); }
        $count++;
    }
    function denied(callable $call, int $code, string $label): void {
        try { $call(); } catch (\DomainException $e) { expect($e->getCode() === $code, $label); return; }
        throw new \RuntimeException('Nicht abgewiesen: ' . $label);
    }
    $config = new Config(); $groups = new Groups(); $session = new Session(); $store = new MemoryStore();
    $access = new \OCA\Vereinsinventar\Service\Access($groups, $session, $config);
    $inventory = new \OCA\Vereinsinventar\Service\Inventory($store, $access);
    $save = fn($g, $id, $p, $k, $n, $q = 1, $no = '', $loc = '', $notes = '') => $inventory->save($g, $id, $p, $k, $n, $q, $no, $loc, $notes);
    $save('verein-a', 0, 0, 'folder', 'Lager');
    $save('verein-a', 0, 1, 'folder', 'Technik');
    $save('verein-a', 0, 2, 'item', ' Mikrofon ', 2, 'M-01', 'Regal', '<script>alert(1)</script>');
    expect($store->rows[3]['name'] === 'Mikrofon' && $store->rows[3]['quantity'] === 2, 'Anlegen und Normalisieren');
    expect(count($inventory->listing('verein-a', 2)) === 3, 'Verschachtelte Kategorie lesen');
    denied(fn() => $inventory->listing('verein-b', 0), 403, 'Fremde Gruppe lesen');
    denied(fn() => $save('verein-b', 3, 0, 'item', 'Übernahme'), 403, 'Fremde Gruppe bearbeiten');
    denied(fn() => $inventory->remove('verein-b', 3), 403, 'Fremde Gruppe löschen');
    $groups->ids[] = 'verein-b';
    $save('verein-b', 0, 0, 'folder', 'Fremdlager');
    denied(fn() => $save('verein-a', 0, 4, 'item', 'Falsch'), 404, 'Kategorie einer anderen eigenen Gruppe');
    denied(fn() => $inventory->get('verein-b', 3), 404, 'Objekt-ID und Gruppe müssen zusammenpassen');
    denied(fn() => $save('verein-b', 3, 0, 'item', 'Falsch'), 404, 'Update über fremde Objekt-ID');
    denied(fn() => $save('verein-a', 0, 3, 'item', 'Falsch'), 400, 'Gegenstand als Kategorie');
    denied(fn() => $save('verein-a', 1, 2, 'folder', 'Zyklus'), 400, 'Kategorien nicht verschieben');
    denied(fn() => $save('verein-a', 1, 0, 'item', 'Typwechsel'), 400, 'Typwechsel');
    denied(fn() => $inventory->remove('verein-a', 1), 400, 'Kategorie nicht löschen');
    foreach ([-1, 1000001] as $q) { denied(fn() => $save('verein-a', 0, 0, 'item', 'X', $q), 400, 'Anzahl begrenzen'); }
    denied(fn() => $save('verein-a', 0, 0, 'item', '  '), 400, 'Name erforderlich');
    denied(fn() => $save('verein-a', 0, 0, 'item', str_repeat('ä', 201)), 400, 'Unicode-Länge');
    denied(fn() => $save('verein-a', 0, 0, 'item', "X\0Y"), 400, 'NUL abweisen');
    denied(fn() => $save('verein-a', 0, 0, 'item', 'X', 1, '', '', str_repeat('x', 5001)), 400, 'Notiz begrenzen');
    $config->value = '["verein-a"]';
    expect(count($inventory->listing('verein-a', 0)) === 3, 'Nur-Lesen darf lesen');
    denied(fn() => $save('verein-a', 3, 2, 'item', 'Gesperrt'), 403, 'Nur-Lesen Update');
    denied(fn() => $inventory->remove('verein-a', 3), 403, 'Nur-Lesen Delete');
    $config->value = 'kaputt';
    denied(fn() => $save('verein-a', 0, 0, 'item', 'Gesperrt'), 403, 'Konfiguration fail-closed');
    foreach (['{}', '[1]', '[[]]'] as $invalid) {
        $config->value = $invalid;
        denied(fn() => $save('verein-a', 0, 0, 'item', 'Gesperrt'), 403, 'Ungültige Nur-Lesen-Liste');
    }
    $config->value = '[]';
    $groups->ids = [];
    denied(fn() => $inventory->get('verein-a', 3), 403, 'Entzogene Mitgliedschaft');
    $session->loggedIn = false;
    denied(fn() => $inventory->listing('verein-a', 0), 403, 'Keine Sitzung');
    $session->loggedIn = true; $groups->ids = ['verein-a'];
    $save('verein-a', 3, 0, 'item', 'Mikrofon', 0);
    expect($store->rows[3]['parent_id'] === 0 && $store->rows[3]['quantity'] === 0, 'Verschieben und Nullbestand');
    $inventory->remove('verein-a', 3);
    denied(fn() => $inventory->get('verein-a', 3), 404, 'Löschen');
    expect(array_column($access->groups(), 'id') === ['verein-a'], 'Mitglied sieht nur eigene freigegebene Gruppe');
    $groups->admin = true;
    expect(array_column($access->groups(), 'id') === ['verein-a', 'verein-b'], 'Admin sieht alle freigegebenen Gruppen');
    $access->check('verein-b', true);
    expect(true, 'Admin darf freigegebene fremde Gruppe bearbeiten');
    denied(fn() => $access->check('verwaltung'), 403, 'Verwaltungsgruppe auch für Admin gesperrt');
    denied(fn() => $save('verwaltung', 0, 0, 'folder', 'Unzulässig'), 403, 'Kein Anlegen in nicht freigegebener Gruppe');
    $config->value = '["verein-b"]';
    denied(fn() => $access->check('verein-b', true), 403, 'Nur-Lesen gilt auch für Admin');
    $config->value = '[]';
    $config->allowed = '[]';
    expect($access->groups() === [], 'Ohne Freigabe keine Gruppen');
    denied(fn() => $access->check('verein-a'), 403, 'Freigabe entziehen sperrt Admin');
    $groups->admin = false;
    denied(fn() => $access->check('verein-a'), 403, 'Freigabe entziehen sperrt Mitglied');
    foreach (['kaputt', '{}', '[1]', '[[]]'] as $invalid) {
        $config->allowed = $invalid;
        expect($access->groups() === [], 'Ungültige Freigabeliste bleibt geschlossen');
        denied(fn() => $access->check('verein-a'), 403, 'Ungültige Freigabe sperrt Zugriff');
    }
    $config->allowed = '["gelöscht"]';
    expect($access->groups() === [], 'Gelöschte Gruppen überspringen');
    denied(fn() => $access->check('gelöscht'), 403, 'Gelöschte Gruppe sperren');
    $config->allowed = '["verein-a"]';
    $session->loggedIn = false;
    expect(!$access->isAdmin() && $access->groups() === [], 'Anonyme Sitzung hat keine Gruppen');
    $session->loggedIn = true; $groups->admin = false; $groups->ids = ['verwaltung'];
    $config->allowed = '["verein-a","verein-b"]';
    $config->value = '["verein-a","verein-b"]';
    $config->editors = '{"verein-a":["verwaltung"]}';
    expect(array_column($access->groups(), 'id') === ['verein-a'], 'Bearbeiter sieht zugeordnetes Inventar ohne eigene Mitgliedschaft');
    $access->check('verein-a', true);
    expect($access->writable('verein-a'), 'Bearbeiter überschreibt Nur-Lesen für genau ein Inventar');
    denied(fn() => $access->check('verein-b', true), 403, 'Bearbeiterrechte nicht auf anderes Inventar übertragen');
    denied(fn() => $access->check('verwaltung'), 403, 'Bearbeitergruppe erhält keinen eigenen Inventarbereich');
    $save('verein-a', 0, 1, 'item', 'Bearbeitergegenstand');
    $created = max(array_keys($store->rows));
    $save('verein-a', $created, 2, 'item', 'Verschoben');
    expect($inventory->get('verein-a', $created)['parent_id'] === 2, 'Bearbeiter darf Gegenstand anlegen und verschieben');
    $save('verein-a', 2, 1, 'folder', 'Technik neu');
    expect($inventory->get('verein-a', 2)['name'] === 'Technik neu', 'Bearbeiter darf Kategorie umbenennen');
    $inventory->remove('verein-a', $created);
    denied(fn() => $inventory->get('verein-a', $created), 404, 'Bearbeiter darf Gegenstand löschen');
    $groups->ids = ['verein-a'];
    $access->check('verein-a');
    expect(!$access->writable('verein-a'), 'Normales Mitglied bleibt Nur-Lesen');
    denied(fn() => $save('verein-a', 0, 0, 'item', 'Gesperrt'), 403, 'Normales Mitglied darf trotz Bearbeiterzuordnung nicht schreiben');
    $groups->admin = true;
    denied(fn() => $access->check('verein-a', true), 403, 'Admin ohne Bearbeiterzuordnung bleibt Nur-Lesen');
    $groups->ids[] = 'verwaltung';
    $access->check('verein-a', true);
    expect($access->writable('verein-a'), 'Admin als zugeordneter Bearbeiter darf schreiben');
    $config->allowed = '[]';
    denied(fn() => $access->check('verein-a', true), 403, 'Freigabe sperrt auch Bearbeiter');
    $config->allowed = '["verein-a","verein-b"]'; $groups->admin = false; $groups->ids = ['verwaltung'];
    $config->editors = '{}';
    denied(fn() => $access->check('verein-a'), 403, 'Entzogene Bearbeiterzuordnung sperrt Zugriff');
    $config->editors = '{"verein-a":["verwaltung"]}'; $groups->ids = [];
    denied(fn() => $access->check('verein-a'), 403, 'Entzogene Bearbeitermitgliedschaft sperrt Zugriff');
    $groups->ids = ['verein-a']; $config->value = '[]';
    foreach (['kaputt', '[]', '{"verein-a":"verwaltung"}', '{"verein-a":[1]}', '{"verein-a":{}}'] as $invalid) {
        $config->editors = $invalid;
        expect(!$access->writable('verein-a'), 'Ungültige Bearbeiterkonfiguration sperrt Schreiben');
    }
    $config->editors = '{"verein-a":["gelöscht"]}'; $config->value = '["verein-a"]';
    expect(!$access->writable('verein-a'), 'Gelöschte Bearbeitergruppe verleiht keine Rechte');
    echo "OK: $count Prüfungen\n";
}
