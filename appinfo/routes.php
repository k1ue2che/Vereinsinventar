<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
return ['routes' => [
    ['name' => 'settings#save', 'url' => '/settings/groups', 'verb' => 'POST'],
    ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
    ['name' => 'page#save', 'url' => '/save', 'verb' => 'POST'],
    ['name' => 'page#remove', 'url' => '/remove', 'verb' => 'POST'],
]];
