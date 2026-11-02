<?php

declare(strict_types=1);

namespace App\Tests\Service\Mail;

use App\Entity\User\User;
use App\Service\Mail\RecipientGroups;
use PHPUnit\Framework\TestCase;

final class RecipientGroupsTest extends TestCase
{
    public function testListsAndQuotedNamesAreParsedAndDeduplicated(): void
    {
        $library = new RecipientGroups();
        self::assertSame(['a@example.com', 'b@example.com', 'c@example.com'], $library->parse(
            '"Lechat, Maurice; X" <A@example.com>; b@example.com,' . "\r\n" . 'Gertrude <c@example.com>; a@example.com',
        ));
    }

    public function testAnInvalidMemberRefusesTheWholeGroup(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RecipientGroups())->parse('a@example.com; invalid; b@example.com');
    }

    public function testMalformedNamedListCannotKeepOnlyItsLastAddress(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RecipientGroups())->parse('A <a@example.com> B <b@example.com>');
    }

    public function testGroupsArePrivateToTheirUser(): void
    {
        $first = new User();
        $second = new User();
        $first->setSetting(RecipientGroups::SETTING, ['test' => ['name' => 'TOTO', 'emails' => ['a@example.com']]]);
        self::assertCount(1, (new RecipientGroups())->all($first));
        self::assertSame([], (new RecipientGroups())->all($second));
        self::assertContains(RecipientGroups::SETTING, \App\Service\Backup\ConfigBackupUsers::USER_SETTINGS);
    }

    public function testEmptyGroupsAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RecipientGroups())->parse(" ; ,\n");
    }

    public function testOversizedGroupsAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RecipientGroups())->parse(implode(';', array_map(static fn (int $i): string => 'a' . $i . '@example.com', range(0, 500))));
    }
}
