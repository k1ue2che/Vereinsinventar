<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Vereinsinventar\Controller;
use OCA\Vereinsinventar\Service\Access;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
final class SettingsController extends Controller {
    public function __construct(string $appName, IRequest $request, private IConfig $config, private IGroupManager $groups, private Access $access, private IURLGenerator $urls) {
        parent::__construct($appName, $request);
    }
    // Absichtlich kein NoAdminRequired und kein NoCSRFRequired.
    public function save(array $allowedGroups = [], array $readonlyGroups = [], array $editorGroups = [], array $inventoryIds = []): DataResponse|RedirectResponse {
        if (!$this->access->isAdmin()) {
            return new DataResponse(['message' => 'Nur Administratoren dürfen Inventargruppen freigeben.'], 403);
        }
        foreach (array_merge($allowedGroups, $readonlyGroups) as $id) {
            if (!is_string($id) || $this->groups->get($id) === null) {
                return new DataResponse(['message' => 'Ungültige Gruppenauswahl. Bitte die Einstellungen neu laden.'], 400);
            }
        }
        $allowedGroups = array_values(array_unique($allowedGroups));
        $readonlyGroups = array_values(array_intersect(array_unique($readonlyGroups), $allowedGroups));
        $editors = new \stdClass();
        foreach ($editorGroups as $index => $ids) {
            $target = $inventoryIds[$index] ?? null;
            if (!is_string($target) || $this->groups->get($target) === null || !is_array($ids)) {
                return new DataResponse(['message' => 'Ungültige Zuordnung der Bearbeitergruppen.'], 400);
            }
            foreach ($ids as $id) {
                if (!is_string($id) || $this->groups->get($id) === null) {
                    return new DataResponse(['message' => 'Eine ausgewählte Bearbeitergruppe existiert nicht mehr. Bitte die Einstellungen neu laden.'], 400);
                }
            }
            if (in_array($target, $allowedGroups, true)) {
                $editors->{$target} = array_values(array_unique($ids));
            }
        }
        // Alle Werte erst nach vollständiger Prüfung speichern.
        $this->config->setAppValue('vereinsinventar', 'editor_groups', json_encode($editors, JSON_THROW_ON_ERROR));
        $this->config->setAppValue('vereinsinventar', 'readonly_groups', json_encode($readonlyGroups, JSON_THROW_ON_ERROR));
        $this->config->setAppValue('vereinsinventar', 'allowed_groups', json_encode($allowedGroups, JSON_THROW_ON_ERROR));
        return new RedirectResponse($this->urls->linkToRoute('settings.AdminSettings.index', ['section' => 'additional']) . '#vereinsinventar', 303);
    }
}
