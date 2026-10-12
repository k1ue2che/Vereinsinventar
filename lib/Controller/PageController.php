<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Vereinsinventar\Controller;
use OCA\Vereinsinventar\Service\Access;
use OCA\Vereinsinventar\Service\Inventory;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
final class PageController extends Controller {
    public function __construct(string $appName, IRequest $request, private Inventory $inventory, private Access $access, private IURLGenerator $urls) {
        parent::__construct($appName, $request);
    }
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(string $groupId = '', int $folder = 0, int $edit = 0): TemplateResponse {
        return $this->render($groupId, $folder, $edit);
    }
    #[NoAdminRequired]
    public function save(string $groupId, int $folder = 0, int $id = 0, int $parent = 0, string $kind = 'item', string $name = '', int $quantity = 1, string $inventoryNo = '', string $location = '', string $notes = ''): TemplateResponse|RedirectResponse {
        try {
            $this->inventory->save($groupId, $id, $parent, $kind, $name, $quantity, $inventoryNo, $location, $notes);
            return $this->redirect($groupId, $folder);
        } catch (\DomainException $e) {
            return $this->render($groupId, $folder, 0, $e->getMessage(), (int)$e->getCode(), compact('id', 'parent', 'kind', 'name', 'quantity', 'inventoryNo', 'location', 'notes'));
        }
    }
    #[NoAdminRequired]
    public function remove(string $groupId, int $id, int $folder = 0): TemplateResponse|RedirectResponse {
        try {
            $this->inventory->remove($groupId, $id);
            return $this->redirect($groupId, $folder);
        } catch (\DomainException $e) {
            return $this->render($groupId, $folder, 0, $e->getMessage(), (int)$e->getCode());
        }
    }
    private function redirect(string $groupId, int $folder): RedirectResponse {
        return new RedirectResponse($this->urls->linkToRoute('vereinsinventar.page.index', compact('groupId', 'folder')), 303);
    }
    private function render(string $groupId, int $folder, int $edit, string $error = '', int $status = 200, ?array $draft = null): TemplateResponse {
        $groups = $this->access->groups();
        if ($groupId === '' && $groups !== []) {
            $groupId = $groups[0]['id'];
        }
        $nodes = [];
        $selected = null;
        $writable = false;
        try {
            if ($groupId !== '') {
                $nodes = $this->inventory->listing($groupId, $folder);
                $writable = $this->access->writable($groupId);
                if ($edit > 0) {
                    $selected = $this->inventory->get($groupId, $edit);
                }
            }
        } catch (\DomainException $e) {
            $error = $e->getMessage();
            $status = (int)$e->getCode();
            $nodes = [];
            $writable = false;
        }
        if ($draft !== null && $writable) {
            $selected = ['id' => $draft['id'], 'parent_id' => $draft['parent'], 'kind' => $draft['kind'], 'name' => $draft['name'], 'quantity' => $draft['quantity'], 'inventory_no' => $draft['inventoryNo'], 'location' => $draft['location'], 'notes' => $draft['notes']];
        }
        $response = new TemplateResponse('vereinsinventar', 'main', [
            'groups' => $groups, 'groupId' => $groupId, 'folder' => $folder,
            'nodes' => $nodes, 'selected' => $selected, 'writable' => $writable,
            'error' => $error, 'urls' => $this->urls,
        ]);
        $response->setStatus($status);
        return $response;
    }
}
