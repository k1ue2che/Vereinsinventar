<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Vereinsinventar\Service;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
final class Access {
    public function __construct(private IGroupManager $groupManager, private IUserSession $userSession, private IConfig $config) {}
    public function groups(): array {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return [];
        }
        $groups = [];
        foreach ($this->groupManager->getUserGroups($user) as $group) {
            $groups[] = ['id' => $group->getGID(), 'name' => $group->getDisplayName()];
        }
        usort($groups, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $groups;
    }
    public function check(string $groupId, bool $write = false): void {
        $user = $this->userSession->getUser();
        if ($user === null || !$this->groupManager->isInGroup($user->getUID(), $groupId)) {
            throw new \DomainException('Kein Zugriff auf diese Gruppe.', 403);
        }
        if ($write && !$this->writable($groupId)) {
            throw new \DomainException('Dieses Gruppeninventar ist schreibgeschützt.', 403);
        }
    }
    public function writable(string $groupId): bool {
        $value = $this->config->getAppValue('vereinsinventar', 'readonly_groups', '[]');
        $groups = json_decode($value);
        // Fehlerhafte Konfiguration erlaubt keine Schreibzugriffe.
        return is_array($groups) && array_is_list($groups)
            && count(array_filter($groups, 'is_string')) === count($groups)
            && !in_array($groupId, $groups, true);
    }
}
