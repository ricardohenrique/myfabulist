<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use App\Services\Data\TaskDetailsData;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class TaskDetailsDataTest extends TestCase
{
    public function test_it_holds_the_four_replace_semantics_fields(): void
    {
        $dueDate = Carbon::parse('2026-09-01');

        $data = new TaskDetailsData(
            title: 'Buy milk',
            note: 'Whole milk',
            dueDate: $dueDate,
            isStarred: true,
        );

        $this->assertSame('Buy milk', $data->title);
        $this->assertSame('Whole milk', $data->note);
        $this->assertTrue($dueDate->equalTo($data->dueDate));
        $this->assertTrue($data->isStarred);
    }

    public function test_note_due_date_and_star_are_nullable_or_false_to_clear(): void
    {
        $data = new TaskDetailsData(
            title: 'Buy milk',
            note: null,
            dueDate: null,
            isStarred: false,
        );

        $this->assertNull($data->note);
        $this->assertNull($data->dueDate);
        $this->assertFalse($data->isStarred);
    }
}
