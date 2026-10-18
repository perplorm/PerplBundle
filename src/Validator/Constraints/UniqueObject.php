<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

declare(strict_types = 1);

namespace Propel\Bundle\PropelBundle\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use function array_diff;
use function array_is_list;
use function array_keys;
use function implode;
use function is_array;
use function sprintf;
use function trigger_deprecation;

/**
 * Constraint for the Unique Object validator
 *
 * @author Maxime AILLOUD <maxime.ailloud@gmail.com>
 * @author Marek Kalnik <marekk@theodo.fr>
 */
#[\Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class UniqueObject extends Constraint
{
    public string $message = 'A {{ object_class }} object already exists with {{ fields }}';

    /**
     * Used to merge multiple fields in the message
     */
    public string $messageFieldSeparator = ' and ';

    /**
     * @var array<string>
     */
    public array $fields;

    /**
     * Used to set the path where the error will be attached, default is the first field.
     */
    public ?string $errorPath = null;

    /**
     * @param array<string, mixed>|array<string>|string $fields the field(s), or an array of options (deprecated)
     * @param array<string>|null $groups
     *
     * @throws \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    #[HasNamedArguments]
    public function __construct(
        string|array $fields,
        ?string $message = null,
        ?string $messageFieldSeparator = null,
        ?string $errorPath = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (is_array($fields) && !array_is_list($fields)) {
            trigger_deprecation('perplorm/perpl-bundle', '1.0', 'Passing an array of options to "%s" is deprecated, use named arguments instead.', self::class);

            $options = $fields;
            $unknown = array_diff(array_keys($options), ['fields', 'message', 'messageFieldSeparator', 'errorPath', 'groups', 'payload']);
            if ($unknown) {
                throw new ConstraintDefinitionException(sprintf('The options "%s" do not exist in constraint "%s".', implode('", "', $unknown), self::class));
            }

            $fields = $options['fields'] ?? [];
            $message = $options['message'] ?? $message;
            $messageFieldSeparator = $options['messageFieldSeparator'] ?? $messageFieldSeparator;
            $errorPath = $options['errorPath'] ?? $errorPath;
            $groups = $options['groups'] ?? $groups;
            $payload = $options['payload'] ?? $payload;
        }

        parent::__construct(null, $groups, $payload);

        $this->fields = (array)$fields;
        $this->message = $message ?? $this->message;
        $this->messageFieldSeparator = $messageFieldSeparator ?? $this->messageFieldSeparator;
        $this->errorPath = $errorPath;

        if ($this->fields === []) {
            throw new ConstraintDefinitionException('At least one field must be specified.');
        }
    }

    /**
     * @return string
     */
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
