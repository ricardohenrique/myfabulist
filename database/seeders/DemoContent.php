<?php

declare(strict_types=1);

namespace Database\Seeders;

use InvalidArgumentException;

/**
 * Realistic vocabulary for the demo dataset (D5). Deliberately kept out of
 * the factories — those stay `fake()->words()` for the test suite — and out
 * of `App\`, so it touches no database and is trivially unit-testable.
 *
 * Every picker returns distinct values sampled from its pool and throws
 * when asked for more than the pool holds, rather than silently returning
 * duplicates or fewer than requested.
 */
final class DemoContent
{
    /**
     * @var array<int, non-empty-string>
     */
    private const FOLDER_NAMES = [
        'Work',
        'Personal',
        'Home',
        'Travel',
        'Finance',
        'Health',
        'Learning',
        'Side Projects',
    ];

    /**
     * @var array<int, non-empty-string>
     */
    private const LIST_NAMES = [
        'Website launch',
        'Q3 roadmap',
        'Client onboarding',
        'Bug backlog',
        'Team offsite',
        'Product launch checklist',
        'Weekly errands',
        'Groceries',
        'Meal planning',
        'Home renovation',
        'Garden projects',
        'Spring cleaning',
        'Summer trip',
        'Japan itinerary',
        'Weekend getaway',
        'Packing list',
        'Monthly budget',
        'Tax prep',
        'Investment research',
        'Subscriptions to cancel',
        'Gym routine',
        'Meal prep',
        'Doctor appointments',
        'Sleep tracker',
        'Spanish lessons',
        'Reading list',
        'Online course notes',
        'Certification prep',
        'Portfolio site',
        'Open source contributions',
    ];

    /**
     * @var array<int, non-empty-string>
     */
    private const TASK_TITLES = [
        'Draft the homepage copy',
        'Choose a hosting provider',
        'Configure the domain DNS',
        'Set up the project repository',
        'Write the launch announcement',
        'Review pull requests',
        'Update the style guide',
        'Fix the mobile navigation bug',
        'Schedule the kickoff meeting',
        'Prepare the sprint demo',
        'Send the client proposal',
        'Follow up with the vendor',
        'Book the conference room',
        'Order more office supplies',
        'Renew the software licenses',
        'Buy milk and eggs',
        'Pick up dry cleaning',
        'Call the plumber',
        'Water the plants',
        'Take the car for an oil change',
        'Book flights',
        'Reserve the hotel',
        'Pack the suitcase',
        'Print the boarding passes',
        'Exchange currency',
        'Pay the electricity bill',
        'Review the monthly budget',
        'Cancel the unused subscription',
        'File the tax documents',
        'Transfer money to savings',
        'Go for a run',
        'Schedule the dentist appointment',
        'Refill the prescription',
        'Plan next week\'s meals',
        'Try the new yoga class',
        'Finish chapter three',
        'Practice Spanish vocabulary',
        'Watch the course video',
        'Submit the assignment',
        'Review flashcards',
        'Update the resume',
        'Apply for the certification exam',
        'Clean out the garage',
        'Donate old clothes',
        'Organize the bookshelf',
        'Vacuum the living room',
        'Mow the lawn',
        'Plant the tomatoes',
        'Repaint the fence',
        'Fix the leaky faucet',
        'Back up the laptop',
        'Update the phone software',
        'Clean up the inbox',
        'Unsubscribe from newsletters',
        'Write the weekly status report',
        'Prepare the invoice',
        'Renew the passport',
        'Research flight deals',
        'Compare insurance plans',
        'Set up the new laptop',
        'Archive last year\'s files',
    ];

    /**
     * @var array<int, non-empty-string>
     */
    private const NOTES = [
        'Check with the team before finalizing.',
        'Waiting on a reply before this can move forward.',
        'Double-check the numbers before submitting.',
        'Remember to bring the receipt.',
        'Ask for a discount if possible.',
        'Needs approval from the manager first.',
        'Low priority, but do not forget it.',
        'Coordinate the timing with everyone involved.',
        'Keep the confirmation email for reference.',
        'Might need a follow-up call.',
        'Check the return policy just in case.',
        'This has slipped a couple of times already.',
        'Bring a backup in case the first option falls through.',
        'Confirm the address before heading out.',
        'Set a reminder a day before the deadline.',
    ];

    /**
     * @return array<int, non-empty-string>
     */
    public static function folderNames(int $count): array
    {
        return self::pickUnique(self::FOLDER_NAMES, $count);
    }

    /**
     * @return array<int, non-empty-string>
     */
    public static function listNames(int $count): array
    {
        return self::pickUnique(self::LIST_NAMES, $count);
    }

    /**
     * @return array<int, non-empty-string>
     */
    public static function taskTitles(int $count): array
    {
        return self::pickUnique(self::TASK_TITLES, $count);
    }

    public static function note(): string
    {
        return self::NOTES[array_rand(self::NOTES)];
    }

    /**
     * Sample `$count` distinct, shuffled values from `$pool`.
     *
     * @param  array<int, non-empty-string>  $pool
     * @return array<int, non-empty-string>
     */
    private static function pickUnique(array $pool, int $count): array
    {
        if ($count < 0) {
            throw new InvalidArgumentException("Cannot pick a negative count ({$count}).");
        }

        if ($count > count($pool)) {
            throw new InvalidArgumentException(
                "Cannot pick {$count} unique values from a pool of ".count($pool).'.',
            );
        }

        if ($count === 0) {
            return [];
        }

        $shuffled = $pool;
        shuffle($shuffled);

        return array_slice($shuffled, 0, $count);
    }
}
