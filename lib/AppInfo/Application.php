<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);
namespace OCA\Vereinsinventar\AppInfo;
use OCP\AppFramework\App;
final class Application extends App {
    public const APP_ID = 'vereinsinventar';
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }
}
