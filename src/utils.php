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
    
    public static function decodeMessageRanges($rangestring) {
        
        $uidarray = array();
        
        $rangearray = explode(",", $rangestring);
        
        foreach ($rangearray as $rangeitem) {
            if (preg_match('/^[0-9]+$/', $rangeitem) > 0) {
                $uidarray[] = $rangeitem;
            } else {
                if(!empty($rangeitem)) {

                    $rangestartandend = explode(':', $rangeitem);
                    $rangestart = $rangestartandend[0];
                    $rangeend = $rangestartandend[1];

                    if (preg_match('/^[0-9]+$/', $rangestart) > 0 and preg_match('/^[0-9]+$/', $rangeend) > 0) {

                        $i = $rangestart;

                        while ($i <= $rangeend) {
                            $uidarray[] = $i;

                            $i++;
                        }

                    }
                }
                
            }
            
        }
        
        return $uidarray;
    }
    
    
    
    
}