<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Bundle\PropelBundle\Security\User;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use function get_class;
use function is_subclass_of;
use function json_encode;
use function sprintf;
use function trigger_deprecation;
use function ucfirst;

/**
 * Provides easy to use provisioning for Propel model users.
 *
 * @author William DURAND <william.durand1@gmail.com>
 *
 * @implements \Symfony\Component\Security\Core\User\UserProviderInterface<\Symfony\Component\Security\Core\User\UserInterface>
 */
class PropelUserProvider implements UserProviderInterface
{
    /**
     * A Model class name.
     *
     * @var string
     */
    protected $class;

    /**
     * A Query class name.
     *
     * @var string
     */
    protected $queryClass;

    /**
     * A property to use to retrieve the user.
     *
     * @var string|null
     */
    protected $property;

    /**
     * @param string $class The User model class.
     * @param string|null $property The property to use to retrieve a user, defaults to "username".
     */
    public function __construct(string $class, ?string $property = null)
    {
        $this->class = $class;
        $this->queryClass = $class . 'Query';
        $this->property = $property;
    }

    /**
     * @param string $identifier
     *
     * @throws \Symfony\Component\Security\Core\Exception\UserNotFoundException
     *
     * @return \Symfony\Component\Security\Core\User\UserInterface
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $filter = 'filterBy' . ucfirst($this->property ?? 'username');
        $user = $this->queryClass::create()->$filter($identifier)->findOne();

        if (!$user instanceof UserInterface) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $user;
    }

    /**
     * @deprecated since 1.0, use loadUserByIdentifier() instead
     *
     * @param string $username
     *
     * @return \Symfony\Component\Security\Core\User\UserInterface
     */
    public function loadUserByUsername(string $username): UserInterface
    {
        trigger_deprecation('perplorm/perpl-bundle', '1.0', 'Method "%s()" is deprecated, use "loadUserByIdentifier()" instead.', __METHOD__);

        return $this->loadUserByIdentifier($username);
    }

    /**
     * @param \Symfony\Component\Security\Core\User\UserInterface $user
     *
     * @throws \Symfony\Component\Security\Core\Exception\UnsupportedUserException
     * @throws \Symfony\Component\Security\Core\Exception\UserNotFoundException
     *
     * @return \Symfony\Component\Security\Core\User\UserInterface
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof $this->class || !$user instanceof ActiveRecordInterface) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $refreshedUser = $this->queryClass::create()->findPk($user->getPrimaryKey());

        if (!$refreshedUser instanceof UserInterface) {
            throw new UserNotFoundException(sprintf('User with id "%s" not found.', json_encode($user->getPrimaryKey())));
        }

        return $refreshedUser;
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass(string $class): bool
    {
        return $class === $this->class || is_subclass_of($class, $this->class);
    }
}
