<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
/** @var array $_ */
\OCP\Util::addStyle('vereinsinventar', 'style');
$urls = $_['urls'];
$link = static fn(array $args = []): string => $urls->linkToRoute('vereinsinventar.page.index', array_merge(['groupId' => $_['groupId']], $args));
$folders = array_values(array_filter($_['nodes'], static fn(array $n): bool => $n['kind'] === 'folder'));
$byId = array_column($folders, null, 'id');
$path = static function (int $id) use ($byId): string {
    $names = [];
    $seen = [];
    while ($id !== 0 && isset($byId[$id]) && !isset($seen[$id])) {
        $seen[$id] = true;
        array_unshift($names, $byId[$id]['name']);
        $id = $byId[$id]['parent_id'];
    }
    return implode(' / ', $names);
};
usort($folders, static fn(array $a, array $b): int => strnatcasecmp($path($a['id']), $path($b['id'])));
$selected = $_['selected'];
$isFolder = $selected !== null && $selected['kind'] === 'folder';
$hidden = static function (string $key, string|int $value): void { ?>
    <input type="hidden" name="<?php p($key); ?>" value="<?php p($value); ?>">
<?php };
$common = static function () use ($hidden, $_): void {
    $hidden('requesttoken', $_['requesttoken']);
    $hidden('groupId', $_['groupId']);
    $hidden('folder', $_['folder']);
};
?>
<div id="vi-app">
    <aside aria-label="Inventarauswahl">
        <h2>Vereinsinventar</h2>
        <form method="get" action="<?php p($link()); ?>">
            <label for="vi-group">Gruppe</label>
            <select id="vi-group" name="groupId">
                <?php foreach ($_['groups'] as $group): ?>
                    <option value="<?php p($group['id']); ?>" <?php if ($group['id'] === $_['groupId']) { echo 'selected'; } ?>><?php p($group['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Gruppe öffnen</button>
        </form>
        <nav aria-label="Kategorien">
            <a href="<?php p($link()); ?>" <?php if ($_['folder'] === 0) { echo 'aria-current="page"'; } ?>>Oberste Ebene</a>
            <?php foreach ($folders as $entry): ?>
                <a href="<?php p($link(['folder' => $entry['id']])); ?>" <?php if ($_['folder'] === $entry['id']) { echo 'aria-current="page"'; } ?>><?php p($path($entry['id'])); ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main>
        <h1><?php p($_['folder'] === 0 ? 'Inventar' : $path($_['folder'])); ?></h1>
        <?php if ($_['error'] !== ''): ?><p class="vi-error" role="alert"><?php p($_['error']); ?></p><?php endif; ?>
        <?php if ($_['groups'] === []): ?>
            <p>Du gehörst noch keiner Nextcloud-Gruppe an. Bitte wende dich an die Administration.</p>
        <?php elseif ($_['groupId'] !== '' && $_['writable'] === false && $_['error'] === ''): ?>
            <p>Dieses Gruppeninventar kannst du nur lesen.</p>
        <?php endif; ?>
        <?php if ($_['folder'] > 0 && isset($byId[$_['folder']])): ?>
            <p><a href="<?php p($link(['folder' => $byId[$_['folder']]['parent_id']])); ?>">Eine Ebene zurück</a>
            <?php if ($_['writable']): ?> · <a href="<?php p($link(['folder' => $_['folder'], 'edit' => $_['folder']])); ?>">Kategorie umbenennen</a><?php endif; ?></p>
        <?php endif; ?>
        <ul class="vi-subfolders">
            <?php foreach ($folders as $entry): if ($entry['parent_id'] !== $_['folder']) { continue; } ?>
                <li><a href="<?php p($link(['folder' => $entry['id']])); ?>">▸ <?php p($entry['name']); ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php $items = array_values(array_filter($_['nodes'], static fn(array $n): bool => $n['kind'] === 'item' && $n['parent_id'] === $_['folder'])); ?>
        <?php if ($items === [] && $_['groups'] !== [] && $_['error'] === ''): ?><p>Hier sind noch keine Gegenstände eingetragen.</p><?php endif; ?>
        <?php if ($items !== []): ?>
        <div class="vi-table"><table>
            <caption>Gegenstände in dieser Kategorie</caption>
            <thead><tr><th scope="col">Name</th><th scope="col">Anzahl</th><th scope="col">Inventarnummer</th><th scope="col">Standort</th><th scope="col">Notiz</th><?php if ($_['writable']): ?><th scope="col">Aktion</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($items as $item): ?><tr>
                <td><?php p($item['name']); ?></td><td><?php p($item['quantity']); ?></td>
                <td><?php p($item['inventory_no']); ?></td><td><?php p($item['location']); ?></td><td class="vi-notes"><?php p($item['notes']); ?></td>
                <?php if ($_['writable']): ?><td><a href="<?php p($link(['folder' => $_['folder'], 'edit' => $item['id']])); ?>">Bearbeiten</a></td><?php endif; ?>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
        <?php if ($_['writable']): ?>
        <?php if (!$isFolder || $selected['id'] > 0): ?>
        <section aria-labelledby="vi-editor-title">
            <h2 id="vi-editor-title"><?php p($selected === null ? 'Gegenstand hinzufügen' : ($isFolder ? 'Kategorie umbenennen' : 'Gegenstand bearbeiten')); ?></h2>
            <form method="post" action="<?php p($urls->linkToRoute('vereinsinventar.page.save')); ?>" class="vi-form">
                <?php $common(); $hidden('id', $selected['id'] ?? 0); $hidden('kind', $isFolder ? 'folder' : 'item'); ?>
                <label for="vi-name">Name *</label><input id="vi-name" name="name" maxlength="200" required value="<?php p($selected['name'] ?? ''); ?>">
                <?php if ($isFolder): $hidden('parent', $selected['parent_id']); else: ?>
                <label for="vi-parent">Kategorie</label><select id="vi-parent" name="parent">
                    <option value="0">Oberste Ebene</option>
                    <?php foreach ($folders as $entry): ?><option value="<?php p($entry['id']); ?>" <?php if ($entry['id'] === ($selected['parent_id'] ?? $_['folder'])) { echo 'selected'; } ?>><?php p($path($entry['id'])); ?></option><?php endforeach; ?>
                </select>
                <label for="vi-quantity">Anzahl</label><input id="vi-quantity" type="number" name="quantity" min="0" max="1000000" step="1" required value="<?php p($selected['quantity'] ?? 1); ?>">
                <label for="vi-number">Inventarnummer</label><input id="vi-number" name="inventoryNo" maxlength="100" value="<?php p($selected['inventory_no'] ?? ''); ?>">
                <label for="vi-location">Standort</label><input id="vi-location" name="location" maxlength="200" value="<?php p($selected['location'] ?? ''); ?>">
                <label for="vi-notes">Notiz</label><textarea id="vi-notes" name="notes" maxlength="5000" rows="3"><?php p($selected['notes'] ?? ''); ?></textarea>
                <?php endif; ?>
                <div><button type="submit" class="primary">Speichern</button> <a href="<?php p($link(['folder' => $_['folder']])); ?>">Abbrechen</a></div>
            </form>
            <?php if ($selected !== null && $selected['id'] > 0 && !$isFolder): ?>
                <details class="vi-delete"><summary>Gegenstand löschen …</summary>
                    <p>„<?php p($selected['name']); ?>“ endgültig löschen? Das lässt sich nicht rückgängig machen.</p>
                    <form method="post" action="<?php p($urls->linkToRoute('vereinsinventar.page.remove')); ?>"><?php $common(); $hidden('id', $selected['id']); ?><button type="submit">Endgültig löschen</button></form>
                </details>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <details class="vi-new-folder" <?php if ($selected !== null && $selected['id'] === 0 && $isFolder) { echo 'open'; } ?>>
            <summary>Unterkategorie hinzufügen</summary>
            <form method="post" action="<?php p($urls->linkToRoute('vereinsinventar.page.save')); ?>" class="vi-form">
                <?php $common(); $hidden('parent', $_['folder']); $hidden('kind', 'folder'); ?>
                <label for="vi-folder-name">Kategoriename *</label><input id="vi-folder-name" name="name" maxlength="200" required value="<?php p($selected !== null && $selected['id'] === 0 && $isFolder ? $selected['name'] : ''); ?>">
                <button type="submit">Kategorie anlegen</button>
            </form>
        </details>
        <?php endif; ?>
    </main>
</div>
