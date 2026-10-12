<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
/** @var array $_ */
?>
<section class="section" id="vereinsinventar">
    <h2>Vereinsinventar – Inventargruppen</h2>
    <p>Nur freigegebene Gruppen erhalten einen Inventarbereich. Verwaltungs- und Untergruppen einfach nicht freigeben.</p>
    <p>Mitglieder sehen ihre freigegebenen Gruppen. Nextcloud-Administratoren können alle freigegebenen Inventare verwalten. Gruppenrechte werden nicht an andere Gruppen vererbt.</p>
    <?php if ($_['groups'] === []): ?>
        <p>Keine Gruppen verfügbar.</p>
    <?php else: ?>
    <form method="post" action="<?php p($_['action']); ?>">
        <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
        <table>
            <thead><tr><th scope="col">Gruppe</th><th scope="col">Für Inventar freigeben</th><th scope="col">Nur lesen</th></tr></thead>
            <tbody><?php foreach ($_['groups'] as $index => $group): ?><tr>
                <th scope="row"><?php p($group['name']); ?> <small>(<?php p($group['id']); ?>)</small></th>
                <td><input type="checkbox" id="vi-allow-<?php p($index); ?>" name="allowedGroups[]" value="<?php p($group['id']); ?>" <?php if (in_array($group['id'], $_['allowed'], true)) { echo 'checked'; } ?>><label for="vi-allow-<?php p($index); ?>">Freigeben: <?php p($group['name']); ?></label></td>
                <td><input type="checkbox" id="vi-readonly-<?php p($index); ?>" name="readonlyGroups[]" value="<?php p($group['id']); ?>" <?php if (in_array($group['id'], $_['readonly'], true)) { echo 'checked'; } ?>><label for="vi-readonly-<?php p($index); ?>">Nur lesen: <?php p($group['name']); ?></label></td>
            </tr><?php endforeach; ?></tbody>
        </table>
        <p>Ohne Freigabe ist eine Gruppe gesperrt. „Nur lesen“ gilt auch für Administratoren. Entfernte Freigaben löschen keine Inventardaten.</p>
        <button type="submit" class="primary">Inventargruppen speichern</button>
    </form>
    <?php endif; ?>
</section>
