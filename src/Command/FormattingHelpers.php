<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use function in_array;
use function strtolower;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
trait FormattingHelpers
{
    /**
     * Comes from the SensioGeneratorBundle.
     *
     * @see https://github.com/sensio/SensioGeneratorBundle/blob/master/Command/Helper/DialogHelper.php#L52
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output The output.
     * @param array<string>|string $text A text message.
     * @param string $style A style to apply on the section.
     *
     * @return void
     */
    protected function writeSection(OutputInterface $output, $text, string $style = 'bg=blue;fg=white'): void
    {
        /** @var \Symfony\Component\Console\Helper\FormatterHelper $formatter */
        $formatter = $this->getHelperSet()->get('formatter');

        $output->writeln([
            '',
            $formatter->formatBlock($text, $style, true),
            '',
        ]);
    }

    /**
     * Asks a confirmation to the user.
     *
     * The question will be asked until the user answers by nothing, yes, or no.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input An Input instance
     * @param \Symfony\Component\Console\Output\OutputInterface $output An Output instance
     * @param string $question The question to ask
     * @param bool $default The default answer if the user enters nothing
     *
     * @return bool true if the user has confirmed, false otherwise
     */
    protected function askConfirmation(InputInterface $input, OutputInterface $output, string $question, bool $default = true): bool
    {
        $question = new Question($question);
        do {
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $questionHelper */
            $questionHelper = $this->getHelperSet()->get('question');
            $answer = $questionHelper->ask($input, $output, $question);
        } while ($answer && !in_array(strtolower($answer[0]), ['y', 'n']));

        if ($default === false) {
            return $answer && strtolower($answer[0]) == 'y';
        }

        return !$answer || strtolower($answer[0]) == 'y';
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output The output.
     * @param string $filename The filename.
     *
     * @return void
     */
    protected function writeNewFile(OutputInterface $output, string $filename): void
    {
        $output->writeln('>>  <info>File+</info>    ' . $filename);
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output The output.
     * @param string $directory The directory.
     *
     * @return void
     */
    protected function writeNewDirectory(OutputInterface $output, string $directory): void
    {
        $output->writeln('>>  <info>Dir+</info>     ' . $directory);
    }

    /**
     * Renders an error message if a task has failed.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output The output.
     * @param string $taskName A task name.
     * @param bool $more Whether to add a 'more details' message or not.
     *
     * @return void
     */
    protected function writeTaskError(OutputInterface $output, string $taskName, bool $more = true): void
    {
        $moreText = $more ? ' To get more details, run the command with the "--verbose" option.' : '';

        $this->writeSection($output, [
            '[Propel] Error',
            '',
            'An error has occured during the "' . $taskName . '" task process.' . $moreText,
        ], 'fg=white;bg=red');
    }
}
