<?php

namespace bjc\roundcubeimap\tests;

use bjc\roundcubeimap\utils;
use PHPUnit\Framework\TestCase;

class utilsTest extends TestCase {

    public function testDecodeMessageRangesYieldsEveryUid(): void {
        $this->assertSame([1, 2, 3, 15, 20, 21], iterator_to_array(utils::decodeMessageRanges('1:3,15,20:21'), false));
    }

    // RFC 3501: "3000:2000" is the same range as "2000:3000"; it used to yield nothing
    public function testDecodeMessageRangesReadsDescendingRanges(): void {
        $this->assertSame([5, 6, 7], iterator_to_array(utils::decodeMessageRanges('7:5'), false));
    }

    public function testCountMessageRangesCountsEveryUid(): void {
        $this->assertSame(6, utils::countMessageRanges('1:3,15,20:21'));
    }

    // A descending range used to count as a negative number and lower the total
    public function testCountMessageRangesCountsDescendingRanges(): void {
        $this->assertSame(1001 + 10001, utils::countMessageRanges('3000:2000,10000:20000'));
    }

    public function testRangeHelpersSkipItemsThatAreNotUids(): void {
        $range = ' ,0,abc,5:*,0:3,4, 8:9 ';

        $this->assertSame([4, 8, 9], iterator_to_array(utils::decodeMessageRanges($range), false));
        $this->assertSame(3, utils::countMessageRanges($range));
        $this->assertSame([4, 9], utils::intersectMessageRanges($range, [1, 4, 9]));
    }

    public function testIntersectMessageRangesKeepsOnlyTheUidsInTheRanges(): void {
        $this->assertSame([3, 9864, 9870], utils::intersectMessageRanges('1:9864,9870', [9870, '3', 9865, 9864, 3]));
    }

    public function testIntersectMessageRangesReadsDescendingAndOverlappingRanges(): void {
        $this->assertSame([2500, 2999], utils::intersectMessageRanges('3000:2000,2500:2600', [1999, 2500, 2999, 3001]));
    }

    // Expanding this range would take four billion iterations: the test only finishes if the UIDs
    // are matched against the bounds
    public function testIntersectMessageRangesDoesNotExpandTheRanges(): void {
        $errorLog = ini_set('error_log', '/dev/null');
        try {
            $this->assertSame([7, 9], utils::intersectMessageRanges('2:4294967295', [1, 7, 9]));
        } finally {
            ini_set('error_log', (string) $errorLog);
        }
    }

    public function testIntersectMessageRangesWithNothingToMatch(): void {
        $this->assertSame([], utils::intersectMessageRanges('', [1, 2]));
        $this->assertSame([], utils::intersectMessageRanges('1:10', []));
    }
}
