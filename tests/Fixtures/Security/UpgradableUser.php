<?php

namespace Propel\Bundle\PropelBundle\Tests\Fixtures\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user whose Query class upgrades passwords.
 */
class UpgradableUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private string $password = 'old',
    ) {
    }

    public function getRoles(): array
    {
        return [];
    }

    public function getUserIdentifier(): string
    {
        return 'user';
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function eraseCredentials(): void
    {
    }
}
