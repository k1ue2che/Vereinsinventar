<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
/** @var array $_ */
\OCP\Util::addStyle('vereinsinventar', 'admin');
?>
<section class="section" id="vereinsinventar">
    <h2>Vereinsinventar – Inventargruppen</h2>
    <p>Nur freigegebene Gruppen erhalten einen Inventarbereich. Verwaltungs- und Untergruppen einfach nicht freigeben.</p>
    <p>Mitglieder sehen ihre freigegebenen Inventare. Bearbeitergruppen erhalten zusätzlich Lese- und Schreibzugriff auf das jeweilige Inventar, auch bei „Nur lesen“. Nur Nextcloud-Administratoren dürfen diese Zuordnungen ändern.</p>
    <?php if ($_['groups'] === []): ?>
        <p>Keine Gruppen verfügbar.</p>
    <?php else: ?>
    <form method="post" action="<?php p($_['action']); ?>">
        <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
        <div class="vi-settings-table"><table>
            <thead><tr><th scope="col">Gruppe</th><th scope="col">Für Inventar freigeben</th><th scope="col">Nur lesen</th><th scope="col">Bearbeitergruppen</th></tr></thead>
            <tbody><?php foreach ($_['groups'] as $index => $group): ?><tr>
                <th scope="row"><?php p($group['name']); ?> <small>(<?php p($group['id']); ?>)</small></th>
                <td><input type="checkbox" id="vi-allow-<?php p($index); ?>" name="allowedGroups[]" value="<?php p($group['id']); ?>" <?php if (in_array($group['id'], $_['allowed'], true)) { echo 'checked'; } ?>><label for="vi-allow-<?php p($index); ?>" aria-label="<?php p('Freigeben: ' . $group['name']); ?>">Freigeben</label></td>
                <td><input type="checkbox" id="vi-readonly-<?php p($index); ?>" name="readonlyGroups[]" value="<?php p($group['id']); ?>" <?php if (in_array($group['id'], $_['readonly'], true)) { echo 'checked'; } ?>><label for="vi-readonly-<?php p($index); ?>" aria-label="<?php p('Nur lesen: ' . $group['name']); ?>">Nur lesen</label></td>
                <td>
                    <input type="hidden" name="inventoryIds[<?php p($index); ?>]" value="<?php p($group['id']); ?>">
                    <select multiple size="3" name="editorGroups[<?php p($index); ?>][]" aria-label="<?php p('Bearbeitergruppen für ' . $group['name']); ?>">
                        <?php foreach ($_['groups'] as $editor): ?>
                        <option value="<?php p($editor['id']); ?>" <?php if (in_array($editor['id'], $_['editors'][$group['id']] ?? [], true)) { echo 'selected'; } ?>><?php p($editor['name'] . ' (' . $editor['id'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <p>„Nur lesen“ sperrt die normalen Mitglieder und Nextcloud-Administratoren; ausgewählte Bearbeitergruppen dürfen weiterhin bearbeiten. Ohne Freigabe bleibt das Inventar für alle gesperrt. Eine Bearbeitergruppe braucht keine eigene Inventarfreigabe. Ohne „Nur lesen“ dürfen auch die Mitglieder der Inventargruppe schreiben.</p>
        <p>Mehrere Bearbeitergruppen auswählen: Strg (Windows/Linux) oder ⌘ (Mac) gedrückt halten. Ohne Auswahl hat bei „Nur lesen“ niemand Schreibzugriff. Entfernte Freigaben löschen keine Inventardaten.</p>
        <button type="submit" class="primary">Inventargruppen speichern</button>
    </form>
    <?php endif; ?>
</section>
