<?php

namespace Propel\Bundle\PropelBundle\Tests\Fixtures\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * Stands in for a generated Query class that implements the password upgrade.
 */
class UpgradableUserQuery implements PasswordUpgraderInterface
{
    /**
     * @var list<array{PasswordAuthenticatedUserInterface, string}>
     */
    public static array $upgrades = [];

    public static function create(): static
    {
        return new static();
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        self::$upgrades[] = [$user, $newHashedPassword];

        if ($user instanceof UpgradableUser) {
            $user->setPassword($newHashedPassword);
        }
    }
}
