<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Database\Seeders\DemoContent;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DemoContentTest extends TestCase
{
    public function test_folder_names_returns_exactly_n_unique_values(): void
    {
        $names = DemoContent::folderNames(5);

        $this->assertCount(5, $names);
        $this->assertCount(5, array_unique($names));
    }

    public function test_list_names_returns_exactly_n_unique_values(): void
    {
        $names = DemoContent::listNames(10);

        $this->assertCount(10, $names);
        $this->assertCount(10, array_unique($names));
    }

    public function test_task_titles_returns_exactly_n_unique_values(): void
    {
        $titles = DemoContent::taskTitles(20);

        $this->assertCount(20, $titles);
        $this->assertCount(20, array_unique($titles));
    }

    public function test_repeated_calls_vary(): void
    {
        $samples = array_map(
            fn () => implode('|', DemoContent::listNames(5)),
            range(1, 20),
        );

        $this->assertGreaterThan(1, count(array_unique($samples)), 'Expected shuffled samples to vary across calls.');
    }

    public function test_requesting_more_than_the_folder_pool_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DemoContent::folderNames(9);
    }

    public function test_requesting_more_than_the_list_pool_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DemoContent::listNames(31);
    }

    public function test_requesting_more_than_the_task_title_pool_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DemoContent::taskTitles(1_000);
    }

    public function test_requesting_zero_returns_an_empty_array(): void
    {
        $this->assertSame([], DemoContent::folderNames(0));
        $this->assertSame([], DemoContent::listNames(0));
        $this->assertSame([], DemoContent::taskTitles(0));
    }

    public function test_note_returns_a_non_empty_string(): void
    {
        $this->assertNotSame('', DemoContent::note());
    }
}
