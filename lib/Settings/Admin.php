<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Vereinsinventar\Settings;
use OCA\Vereinsinventar\Service\Access;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
final class Admin implements ISettings {
    public function __construct(private IGroupManager $groupManager, private Access $access, private IURLGenerator $urls) {}
    public function getForm(): TemplateResponse {
        $groups = [];
        // Auch direkte Aufrufe dürfen normalen Benutzern keine Gruppenliste liefern.
        if (!$this->access->isAdmin()) {
            return new TemplateResponse('vereinsinventar', 'admin', ['groups' => [], 'allowed' => [], 'readonly' => [], 'action' => ''], 'blank');
        }
        foreach ($this->groupManager->search('') as $group) {
            $groups[] = ['id' => $group->getGID(), 'name' => $group->getDisplayName()];
        }
        usort($groups, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return new TemplateResponse('vereinsinventar', 'admin', [
            'groups' => $groups, 'allowed' => $this->access->allowedIds(),
            'readonly' => $this->access->readonlyIds(),
            'action' => $this->urls->linkToRoute('vereinsinventar.settings.save'),
        ], 'blank');
    }
    public function getSection(): string { return 'additional'; }
    public function getPriority(): int { return 50; }
}
