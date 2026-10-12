<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCP\AppFramework {
    class Controller { public function __construct(string $appName, \OCP\IRequest $request) {} }
}
namespace OCP\AppFramework\Http {
    class DataResponse {
        public function __construct(public array $data, private int $status = 200) {}
        public function getStatus(): int { return $this->status; }
    }
    class RedirectResponse {
        public function __construct(public string $url, private int $status = 303) {}
        public function getStatus(): int { return $this->status; }
    }
}
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require __DIR__ . '/run.php';
    require __DIR__ . '/../lib/Controller/SettingsController.php';
    $config = new Config(); $session = new Session(); $groups = new Groups();
    $access = new \OCA\Vereinsinventar\Service\Access($groups, $session, $config);
    $urls = new class implements \OCP\IURLGenerator {
        public function linkToRoute($route, $args = []) { return '/settings/admin/' . ($args['section'] ?? ''); }
    };
    $settings = new \OCA\Vereinsinventar\Controller\SettingsController('vereinsinventar', new class implements \OCP\IRequest {}, $config, $groups, $access, $urls);
    expect($settings->save(['verwaltung'])->getStatus() === 403, 'Normales Mitglied darf Einstellungen nicht speichern');
    expect($config->allowed === '["verein-a","verein-b"]', 'Abgewiesener POST ändert nichts');
    $groups->admin = true;
    expect($settings->save(['verein-a', 'verein-a'], ['verein-a', 'verein-b'])->getStatus() === 303, 'Admin darf Auswahl speichern');
    expect($config->allowed === '["verein-a"]' && $config->value === '["verein-a"]', 'Doppelte und nicht freigegebene Nur-Lesen-Gruppen bereinigen');
    expect($settings->save(['gelöscht'])->getStatus() === 400, 'Unbekannte Gruppen nicht speichern');
    expect($settings->save([['verein-a']])->getStatus() === 400, 'Verschachtelte Werte abweisen');
    expect($settings->save([1])->getStatus() === 400, 'Nicht-Text-ID abweisen');
    expect($config->allowed === '["verein-a"]', 'Ungültige Auswahl lässt Einstellung unverändert');
    expect($settings->save()->getStatus() === 303, 'Alle Freigaben entfernen');
    expect($config->allowed === '[]' && $config->value === '[]', 'Keine Checkbox speichert leere Listen');
    $session->loggedIn = false;
    expect($settings->save(['verein-a'])->getStatus() === 403, 'Anonym darf Einstellungen nicht speichern');
    $method = new \ReflectionMethod($settings, 'save');
    expect($method->getAttributes() === [], 'Admin- und CSRF-Middleware nicht deaktiviert');
    echo "OK: insgesamt $count Rechte-, Daten- und Einstellungsprüfungen\n";
}
