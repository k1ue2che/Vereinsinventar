<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
// In einer frisch installierten Test-Nextcloud ausführen; siehe README.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('OC_CONSOLE', true);
require '/var/www/html/lib/base.php';
use OCP\Server;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IConfig;
use OCP\IDBConnection;
use OCA\Vereinsinventar\Db\Store;
use OCA\Vereinsinventar\Service\Access;
use OCA\Vereinsinventar\Service\Inventory;
$users = Server::get(IUserManager::class);
$groups = Server::get(IGroupManager::class);
$session = Server::get(IUserSession::class);
$config = Server::get(IConfig::class);
$store = new Store(Server::get(IDBConnection::class));
$inventory = new Inventory($store, new Access($groups, $session, $config));
$suffix = bin2hex(random_bytes(5));
$uid = 'vi-test-' . $suffix;
$gid = 'vi-a-' . $suffix;
$other = 'vi-b-' . $suffix;
$user = $users->createUser($uid, bin2hex(random_bytes(24)));
$a = $groups->createGroup($gid);
$b = $groups->createGroup($other);
$previousAllowed = $config->getAppValue('vereinsinventar', 'allowed_groups', '[]');
$previous = $config->getAppValue('vereinsinventar', 'readonly_groups', '[]');
$previousUser = $session->getUser();
function check(bool $condition): void { if (!$condition) { throw new RuntimeException('Integrationsprüfung fehlgeschlagen.'); } }
function reject(callable $call, int $status): void {
    try { $call(); } catch (DomainException $e) { check($e->getCode() === $status); return; }
    throw new RuntimeException('Zugriff wurde unerwartet erlaubt.');
}
try {
    $config->setAppValue('vereinsinventar', 'allowed_groups', json_encode([$gid, $other]));
    $a->addUser($user); $session->setUser($user);
    $inventory->save($gid, 0, 0, 'folder', 'Lager', 1, '', '', '');
    $folder = $inventory->listing($gid, 0)[0]['id'];
    $inventory->save($gid, 0, $folder, 'item', 'Zelt', 2, 'Z-1', 'Halle', 'Trocken lagern');
    $rows = $inventory->listing($gid, $folder);
    $item = array_values(array_filter($rows, fn($row) => $row['kind'] === 'item'))[0];
    check($item['quantity'] === 2 && $item['parent_id'] === $folder);
    reject(fn() => $inventory->listing($other, 0), 403);
    $b->addUser($user);
    reject(fn() => $inventory->get($other, $item['id']), 404);
    reject(fn() => $inventory->save($other, 0, $folder, 'item', 'Falsch', 1, '', '', ''), 404);
    $config->setAppValue('vereinsinventar', 'readonly_groups', json_encode([$gid]));
    reject(fn() => $inventory->remove($gid, $item['id']), 403);
    $config->setAppValue('vereinsinventar', 'readonly_groups', '[]');
    $inventory->save($gid, $item['id'], 0, 'item', 'Zelt neu', 3, '', '', '');
    check($inventory->get($gid, $item['id'])['quantity'] === 3);
    $inventory->remove($gid, $item['id']);
    reject(fn() => $inventory->get($gid, $item['id']), 404);
    $a->removeUser($user);
    reject(fn() => $inventory->listing($gid, 0), 403);
    $controller = new \OCA\Vereinsinventar\Controller\PageController(
        'vereinsinventar', Server::get(\OCP\IRequest::class), $inventory,
        new Access($groups, $session, $config), Server::get(\OCP\IURLGenerator::class)
    );
    $b->addUser($user);
    $page = $controller->index($other)->render();
    check(str_contains($page, 'name="requesttoken"') && str_contains($page, 'Gegenstand hinzufügen'));
    $adminGroup = $groups->get('admin');
    $adminGroup->addUser($user);
    $access = new Access($groups, $session, $config);
    check(count($access->groups()) === 2);
    $access->check($gid, true);
    reject(fn() => $access->check('admin'), 403);
    $settings = new \OCA\Vereinsinventar\Controller\SettingsController('vereinsinventar', Server::get(\OCP\IRequest::class), $config, $groups, $access, Server::get(\OCP\IURLGenerator::class));
    check($settings->save([$gid], [$gid])->getStatus() === 303);
    reject(fn() => $access->check($gid, true), 403);
    reject(fn() => $access->check($other), 403);
    $adminForm = (new \OCA\Vereinsinventar\Settings\Admin($groups, $access, Server::get(\OCP\IURLGenerator::class)))->getForm()->render();
    check(str_contains($adminForm, 'allowedGroups[]'));
    $adminGroup->removeUser($user);
    check($settings->save([$other])->getStatus() === 403);
    echo "OK: Nextcloud-Datenbank, Gruppenfreigaben, Adminrechte und Seitenrendering\n";
} finally {
    $qb = Server::get(IDBConnection::class)->getQueryBuilder();
    $qb->delete('vi_nodes')->where($qb->expr()->in('group_id', $qb->createNamedParameter([$gid, $other], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR_ARRAY)))->executeStatement();
    $config->setAppValue('vereinsinventar', 'readonly_groups', $previous);
    $config->setAppValue('vereinsinventar', 'allowed_groups', $previousAllowed);
    $session->setUser($previousUser);
    $user->delete(); $a->delete(); $b->delete();
}
