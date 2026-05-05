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

//    OLD WAY
//    public static function decodeMessageRanges($rangeAsString)
//    {
//
//        try {
//
//            $uidArray = array();
//            $rangeArray = explode(",", $rangeAsString);
//
//            foreach ($rangeArray as $rangeItem) {
//                if (preg_match('/^[0-9]+$/', $rangeItem) > 0) {
//                    $uidArray[] = $rangeItem;
//                } else {
//                    if (!empty($rangeItem)) {
//
//                        $rangeStartAndEnd = explode(':', $rangeItem);
//                        $rangeStart = intval($rangeStartAndEnd[0]);
//                        $rangeEnd = intval($rangeStartAndEnd[1]);
//                        $range = $rangeEnd - $rangeStart;
//
//                        var_dump(sprintf("Messages range to process: %d - %d, total: %d" . PHP_EOL, $rangeStart, $rangeEnd, $range));
//                        if ($range > 1500000) {
//                            echo "Messages range more 1500000, Skip." . PHP_EOL;
//                            //return [];
//                            $rangeEnd = $rangeStart + 1500000;
//                        }
//
//                        if (preg_match('/^[0-9]+$/', $rangeStart) > 0 and preg_match('/^[0-9]+$/', $rangeEnd) > 0) {
//                            $i = $rangeStart;
//                            while ($i <= $rangeEnd) {
//                                $uidArray[] = $i;
//                                $i++;
//                            }
//                        }
//                    }
//
//                }
//
//            }
//
//            return $uidArray;
//
//        } finally {
//            unset($uidArray);
//            gc_enable();
//            gc_collect_cycles();
//        }
//    }

    /**
     * Decodes a message range string into a Generator that yields individual UIDs
     * This method is memory-efficient as it generates UIDs on-demand rather than loading all into an array
     *
     * @param string $rangeAsString Message range string (e.g., "1:10,15,20:30")
     * @return \Generator<int> Generator that yields individual UIDs
     */
    public static function decodeMessageRanges(string $rangeAsString): \Generator
    {
        foreach (explode(',', $rangeAsString) as $rangeItem) {
            $rangeItem = trim($rangeItem);
            if ($rangeItem === '') continue;

            if (preg_match('/^\d+$/', $rangeItem)) {
                yield (int)$rangeItem;
                continue;
            }

            if (!str_contains($rangeItem, ':')) continue;

            [$startStr, $endStr] = explode(':', $rangeItem, 2);
            $rangeStart = (int)$startStr;
            $rangeEnd   = (int)$endStr;

            if ($rangeStart <= 0 || $rangeEnd <= 0) continue;

            if (($rangeEnd - $rangeStart) > 1_500_000) {
                error_log(sprintf(
                    '[decodeMessageRanges] Large VANISHED range detected: %d:%d (total: %d), possible sync issue.',
                    $rangeStart, $rangeEnd, $rangeEnd - $rangeStart
                ));
            }

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

        foreach (explode(',', $rangeAsString) as $rangeItem) {
            $rangeItem = trim($rangeItem);
            if ($rangeItem === '') continue;

            if (preg_match('/^\d+$/', $rangeItem)) {
                $total++;
                continue;
            }

            if (!str_contains($rangeItem, ':')) continue;

            [$startStr, $endStr] = explode(':', $rangeItem, 2);
            $rangeStart = (int)$startStr;
            $rangeEnd   = (int)$endStr;

            if ($rangeStart <= 0 || $rangeEnd <= 0) continue;

            if (($rangeEnd - $rangeStart) > 1_500_000) {
                error_log(sprintf(
                    '[countMessageRanges] Large VANISHED range detected: %d:%d (total: %d), possible sync issue.',
                    $rangeStart, $rangeEnd, $rangeEnd - $rangeStart
                ));
            }

            $total += ($rangeEnd - $rangeStart + 1);
        }

        return $total;
    }


}