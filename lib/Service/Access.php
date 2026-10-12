<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Vereinsinventar\Service;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
final class Access {
    public function __construct(private IGroupManager $groupManager, private IUserSession $userSession, private IConfig $config) {}
    public function isAdmin(): bool {
        $user = $this->userSession->getUser();
        return $user !== null && $this->groupManager->isAdmin($user->getUID());
    }
    public function allowedIds(): array {
        return $this->list('allowed_groups') ?? [];
    }
    public function readonlyIds(): array {
        return $this->list('readonly_groups') ?? [];
    }
    public function groups(): array {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return [];
        }
        $admin = $this->isAdmin();
        $groups = [];
        foreach ($this->allowedIds() as $id) {
            $group = $this->groupManager->get($id);
            if ($group !== null && ($admin || $this->groupManager->isInGroup($user->getUID(), $id))) {
                $groups[] = ['id' => $id, 'name' => $group->getDisplayName()];
            }
        }
        usort($groups, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $groups;
    }
    public function check(string $groupId, bool $write = false): void {
        $user = $this->userSession->getUser();
        if ($user === null || !in_array($groupId, $this->allowedIds(), true)
            || $this->groupManager->get($groupId) === null
            || (!$this->isAdmin() && !$this->groupManager->isInGroup($user->getUID(), $groupId))) {
            throw new \DomainException('Kein Zugriff auf dieses Gruppeninventar.', 403);
        }
        if ($write && !$this->writable($groupId)) {
            throw new \DomainException('Dieses Gruppeninventar ist schreibgeschützt.', 403);
        }
    }
    public function writable(string $groupId): bool {
        $readonly = $this->list('readonly_groups');
        return in_array($groupId, $this->allowedIds(), true)
            && $readonly !== null && !in_array($groupId, $readonly, true);
    }
    private function list(string $key): ?array {
        $groups = json_decode($this->config->getAppValue('vereinsinventar', $key, '[]'));
        if (!is_array($groups) || !array_is_list($groups)
            || count(array_filter($groups, 'is_string')) !== count($groups)) {
            return null;
        }
        return array_values(array_unique($groups));
    }
}
