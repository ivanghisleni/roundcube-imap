<?php

namespace bjc\roundcubeimap;

class utils {
   
    public static function decodeAddresslist($addresslist) {
        
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
    
    public static function decodeAddress($addressinput) {
        
        $addressarray = \rcube_mime::decode_address_list($addressinput);
        
        $returnarray = array();
        
        $address_item = reset($addressarray);
        $name = $address_item["name"];
        $address = $address_item["mailto"];
            
        $emailaddress = new \bjc\roundcubeimap\emailaddress($address, null, null, $name);
            
        return $emailaddress;
        
    }

    public static function decodeMessageRanges($rangeAsString)
    {

        try {

            $uidArray = array();
            $rangeArray = explode(",", $rangeAsString);

            foreach ($rangeArray as $rangeItem) {
                if (preg_match('/^[0-9]+$/', $rangeItem) > 0) {
                    $uidArray[] = $rangeItem;
                } else {
                    if (!empty($rangeItem)) {

                        $rangeStartAndEnd = explode(':', $rangeItem);
                        $rangeStart = $rangeStartAndEnd[0];
                        $rangeEnd = $rangeStartAndEnd[1];

                        if (preg_match('/^[0-9]+$/', $rangeStart) > 0 and preg_match('/^[0-9]+$/', $rangeEnd) > 0) {

                            $i = $rangeStart;

                            while ($i <= $rangeEnd) {
                                $uidArray[] = $i;
                                $i++;
                            }

                        }
                    }

                }

            }

            return $uidArray;

        } finally {
            unset($uidArray);
            gc_enable();
            gc_collect_cycles();
        }
    }
    
    
    
    
}