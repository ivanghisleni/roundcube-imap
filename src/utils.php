<?php

namespace bjc\roundcubeimap;

class utils
{

    /**
     * Decodes a list of email addresses into an array of EmailAddress objects
     *
     * @param string $addresslist Comma-separated list of email addresses
     * @return array Array of \bjc\roundcubeimap\emailaddress objects
     */
    public static function decodeAddresslist($addresslist)
    {

        $addressarray = \rcube_mime::decode_address_list($addresslist);

        $returnarray = array();

        foreach ($addressarray as $address_key => $address_item) {
            $name = $address_item["name"];
            $address = $address_item["mailto"];

            $emailaddress = new \bjc\roundcubeimap\emailaddress($address, null, null, $name);

            $returnarray[] = $emailaddress;

        }

        return $returnarray;

    }

    /**
     * Decodes a single email address string into an EmailAddress object
     *
     * @param string $addressinput Email address string (e.g., "John Doe <john@example.com>")
     * @return \bjc\roundcubeimap\emailaddress EmailAddress object
     */
    public static function decodeAddress($addressinput)
    {

        $addressarray = \rcube_mime::decode_address_list($addressinput);

        $returnarray = array();

        $address_item = reset($addressarray);
        $name = $address_item["name"];
        $address = $address_item["mailto"];

        $emailaddress = new \bjc\roundcubeimap\emailaddress($address, null, null, $name);

        return $emailaddress;

    }

    /**
     * Decodes a message range string into a Generator that yields individual UIDs
     * This method is memory-efficient as it generates UIDs on-demand rather than loading all into an array
     *
     * @param string $rangeAsString Message range string (e.g., "1:10,15,20:30")
     * @return \Generator<int> Generator that yields individual UIDs
     */
    public static function decodeMessageRanges(string $rangeAsString): \Generator
    {
        foreach (self::parseMessageRanges($rangeAsString, 'decodeMessageRanges') as [$rangeStart, $rangeEnd]) {
            for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
                yield $i;
            }
        }
    }

    /**
     * Counts the total number of UIDs in a message range string without loading them all into memory
     *
     * @param string $rangeAsString Message range string (e.g., "1:10,15,20:30")
     * @return int Total count of UIDs in the range
     */
    public static function countMessageRanges(string $rangeAsString): int
    {
        $total = 0;

        foreach (self::parseMessageRanges($rangeAsString, 'countMessageRanges') as [$rangeStart, $rangeEnd]) {
            $total += ($rangeEnd - $rangeStart + 1);
        }

        return $total;
    }

    /**
     * Returns the given UIDs that fall within a message range string, without expanding the ranges:
     * the cost depends on the number of ranges and UIDs, not on how many UIDs the ranges span, so
     * "1:4294967295" costs as much as "1:10"
     *
     * Typical use: keep only the UIDs a client already knows out of a VANISHED (EARLIER) response,
     * which RFC 7162 allows to list UIDs the client never saw
     *
     * @param string $rangeAsString Message range string (e.g., "1:10,15,20:30")
     * @param array $uids UIDs to look up (int or numeric string)
     * @return int[] UIDs found in the ranges, ascending, without duplicates
     */
    public static function intersectMessageRanges(string $rangeAsString, array $uids): array
    {
        $ranges = iterator_to_array(self::parseMessageRanges($rangeAsString, 'intersectMessageRanges'), false);
        sort($ranges);

        $uids = array_values(array_unique(array_map('intval', $uids)));
        sort($uids);

        // Both lists ascending: advance whichever one is behind
        $found = [];
        $rangeIndex = 0;
        $uidIndex = 0;
        while ($rangeIndex < count($ranges) AND $uidIndex < count($uids)) {
            [$rangeStart, $rangeEnd] = $ranges[$rangeIndex];
            $uid = $uids[$uidIndex];
            if ($uid < $rangeStart) {
                $uidIndex++;
            } elseif ($uid > $rangeEnd) {
                $rangeIndex++;
            } else {
                $found[] = $uid;
                $uidIndex++;
            }
        }

        return $found;
    }

    /**
     * Parses a message range string into [start, end] pairs with start <= end: RFC 3501 lets a
     * sequence set write a range either way round ("3000:2000" is 2000 to 3000)
     *
     * Items that are not a positive UID or a range of two positive UIDs (e.g. "*") are skipped
     *
     * @param string $rangeAsString Message range string (e.g., "1:10,15,20:30")
     * @param string $caller Public method name, for the large-range diagnostic
     * @return \Generator<array{int, int}>
     */
    private static function parseMessageRanges(string $rangeAsString, string $caller): \Generator
    {
        foreach (explode(',', $rangeAsString) as $rangeItem) {
            $rangeItem = trim($rangeItem);
            if ($rangeItem === '') continue;

            if (preg_match('/^\d+$/', $rangeItem)) {
                if ((int)$rangeItem > 0) {
                    yield [(int)$rangeItem, (int)$rangeItem];
                }
                continue;
            }

            if (!str_contains($rangeItem, ':')) continue;

            [$startStr, $endStr] = explode(':', $rangeItem, 2);
            $rangeStart = min((int)$startStr, (int)$endStr);
            $rangeEnd   = max((int)$startStr, (int)$endStr);

            if ($rangeStart <= 0) continue;

            if (($rangeEnd - $rangeStart) > 1_500_000) {
                error_log(sprintf(
                    '[%s] Large VANISHED range detected: %d:%d (total: %d), possible sync issue.',
                    $caller, $rangeStart, $rangeEnd, $rangeEnd - $rangeStart
                ));
            }

            yield [$rangeStart, $rangeEnd];
        }
    }


}