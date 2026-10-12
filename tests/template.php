<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCP { final class Util { public static function addStyle(string $app, string $file): void {} } }
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    function p(mixed $value): void { echo htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    function render(bool $write, ?array $selected = null, array $groups = [['id' => 'verein', 'name' => 'Verein']]): string {
        $_ = [
            'urls' => new class {
                public function linkToRoute($route, $args = []) { return '/apps/vereinsinventar/' . str_replace('vereinsinventar.page.', '', $route) . '?' . http_build_query($args); }
            },
            'groups' => $groups, 'groupId' => 'verein', 'folder' => 0, 'writable' => $write,
            'isAdmin' => false, 'selected' => $selected, 'error' => '', 'requesttoken' => 'nextcloud-token',
            'nodes' => [
                ['id' => 1, 'parent_id' => 0, 'kind' => 'folder', 'name' => 'Lager'],
                ['id' => 2, 'parent_id' => 1, 'kind' => 'folder', 'name' => 'Technik'],
                ['id' => 3, 'parent_id' => 0, 'kind' => 'item', 'name' => '<script>alert(1)</script>', 'quantity' => 2, 'inventory_no' => 'A"1', 'location' => 'Halle', 'notes' => '<img src=x onerror=alert(1)>'],
            ],
        ];
        ob_start(); include __DIR__ . '/../templates/main.php'; return ob_get_clean();
    }
    function check(bool $condition, string $label): void { if (!$condition) { throw new \RuntimeException($label); } }
    function dom(string $html): \DOMXPath {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        return new \DOMXPath($doc);
    }
    $metadata = new \DOMDocument();
    check($metadata->load(__DIR__ . '/../appinfo/info.xml'), 'Metadaten sind gültiges XML');
    check($metadata->getElementsByTagName('id')->item(0)->textContent === 'vereinsinventar', 'Eindeutige App-ID');
    $html = render(true); $xpath = dom($html);
    check(!str_contains($html, '<script>') && !str_contains($html, '<img src=x'), 'HTML muss escaped sein');
    check(str_contains($html, 'Lager / Technik'), 'Kategoriepfad');
    $posts = $xpath->query('//form[@method="post"]');
    check($posts->length === 2, 'Anlegen von Gegenstand und Kategorie');
    foreach ($posts as $post) {
        check($xpath->query('.//input[@name="requesttoken" and @value="nextcloud-token"]', $post)->length === 1, 'CSRF-Token in jedem POST');
        check($xpath->query('.//input[@name="groupId" and @value="verein"]', $post)->length === 1, 'Gruppe in jedem POST');
    }
    check(dom(render(false))->query('//form[@method="post"]')->length === 0, 'Nur-Lesen hat keine Schreibformulare');
    $item = ['id' => 3, 'parent_id' => 0, 'kind' => 'item', 'name' => 'Zelt', 'quantity' => 1, 'inventory_no' => '', 'location' => '', 'notes' => ''];
    check(dom(render(true, $item))->query('//details[contains(@class,"vi-delete")]//form')->length === 1, 'Löschen erst im Bestätigungsbereich');
    $folder = ['id' => 0, 'parent_id' => 0, 'kind' => 'folder', 'name' => 'Entwurf'];
    check(dom(render(true, $folder))->query('//input[@name="name" and @value="Entwurf"]')->length === 1, 'Fehlerhafter Kategorieentwurf bleibt genau einmal sichtbar');
    check(str_contains(render(false, null, []), 'keine Inventargruppe'), 'Hinweis ohne Gruppen');
    $_ = ['requesttoken' => 'admin-token', 'action' => '/settings/groups', 'allowed' => ['technik'], 'readonly' => ['technik'], 'editors' => ['technik' => ['verwaltung']], 'groups' => [['id' => 'technik', 'name' => '<script>Technik</script>'], ['id' => 'verwaltung', 'name' => 'Verwaltung']]];
    ob_start(); include __DIR__ . '/../templates/admin.php'; $adminHtml = ob_get_clean();
    $adminDom = dom($adminHtml);
    check(!str_contains($adminHtml, '<script>'), 'Gruppennamen in Einstellungen escaped');
    check($adminDom->query('//input[@name="allowedGroups[]" and @checked]')->length === 1, 'Nur freigegebene Gruppe ausgewählt');
    check($adminDom->query('//input[@name="readonlyGroups[]" and @checked]')->length === 1, 'Nur-Lesen korrekt ausgewählt');
    check($adminDom->query('//form[@method="post"]//input[@name="requesttoken" and @value="admin-token"]')->length === 1, 'Adminformular hat CSRF-Token');
    check($adminDom->query('//select[@name="editorGroups[0][]" and @multiple]/option[@selected and @value="verwaltung"]')->length === 1, 'Bearbeitergruppen ausgewählt');
    check($adminDom->query('//input[@name="inventoryIds[0]" and @value="technik"]')->length === 1, 'Inventarzuordnung nutzt sicheren Zeilenindex');
    parse_str('allowedGroups%5B%5D=technik&readonlyGroups%5B%5D=technik&inventoryIds%5B0%5D=technik&editorGroups%5B0%5D%5B%5D=verwaltung', $posted);
    check($posted['editorGroups'][0] === ['verwaltung'] && $posted['inventoryIds'][0] === 'technik', 'Native Formularfelder werden korrekt übertragen');
    echo "OK: Templates, Escaping, Formulare und Nur-Lesen\n";
}
