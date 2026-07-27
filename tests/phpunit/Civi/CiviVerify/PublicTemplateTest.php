<?php

declare(strict_types=1);

namespace Civi\CiviVerify;

use PHPUnit\Framework\TestCase;

final class PublicTemplateTest extends TestCase {

  public function testTemplateNeverRendersRawToken(): void {
    $template = file_get_contents(dirname(__DIR__, 4) . '/templates/CRM/CiviVerify/Page/Confirm.tpl');
    self::assertIsString($template);
    self::assertStringNotContainsString('token', strtolower($template));
    self::assertStringContainsString('civiverify_state', $template);
    self::assertStringContainsString('method="post"', $template);
  }

}
