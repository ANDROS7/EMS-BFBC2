<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Framework
 *
 * @author Sinthetix
 */
class Util {

    public static function getSSLContext() {
        global $rootPath;
        $pemPassphrase = 'passphrase';
        $pemfile = $rootPath . '/include/server.pem';
        if (!is_file($pemfile)) {
            self::generateSSL($pemfile, $pemPassphrase);
        }

        $context = stream_context_create();

        // local_cert must be in PEM format
        stream_context_set_option($context, 'ssl', 'local_cert', $pemfile);
        // Pass Phrase (password) of private key
        stream_context_set_option($context, 'ssl', 'passphrase', $pemPassphrase);

        stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
        stream_context_set_option($context, 'ssl', 'verify_peer', false);
        return $context;
    }

    private static function generateSSL($pemfile, $pemPassphrase) {
        $dn = array(
            "countryName" => "UK",
            "stateOrProvinceName" => "Somerset",
            "localityName" => "Glastonbury",
            "organizationName" => "The Brain Room Limited",
            "organizationalUnitName" => "PHP Documentation Team",
            "commonName" => "Wez Furlong",
            "emailAddress" => "wez@example.com"
        );

        $privkey = openssl_pkey_new();

        $cert = openssl_csr_new($dn, $privkey);
        $cert = openssl_csr_sign($cert, null, $privkey, 365);

        // Generate PEM file
        # Optionally change the passphrase from 'comet' to whatever you want, or leave it empty for no passphrase        
        $pem = array();
        openssl_x509_export($cert, $pem[0]);
        openssl_pkey_export($privkey, $pem[1], $pemPassphrase);
        $pem = implode($pem);

        // Save PEM file        
        file_put_contents($pemfile, $pem);
    }

    /**
     * 
     * @param type $data
     * @param type $bytes
     * @return type
     */
    public static function Decode($data, $bytes) {
       
        $num = 0;
        $i = 0;
        for ($i = 0; $i < $bytes; $i++) {
            //num |= (data[i] << (i << 3)); // little
            $byte = unpack('C', ($data[$i]));
            $num |= ($byte[1] << (($bytes - 1 - $i) << 3)); // big
        }
        $num = dechex($num);
        //$num2 = sprintf("%u", hexdec($num2) & 0xffffffff);        
        //$num = sprintf("%u", $num & 0xffffffff);
        return $num;
    }

    public static function Putxx($num, $bytes) {
        $data = "";
        $num = (double) $num;
        for ($i = 0; $i < $bytes; $i++) {
            //data[i] = num >> (i << 3);    // little
            $val = $num >> (($bytes - 1 - $i) << 3); // big
            $val = pack('I', $val);
            $val = unpack('C', $val);
            $data .= chr($val[1]);
        }
        
        return $data;
    }

    //generates random string (e.g. for lkey)
    public static function randomString($len) {
        $chars = "0123456789abcdef";
        $randstring = '';
        $charsLen = strlen($chars) - 1;
        for ($i = 0; $i < $len; $i++) {
            $randstring .= $chars[rand(0, $charsLen)];
        }
        //76cbc00e-4243-421b-ad57-cd73cdaaa61c
        //$str = substr($randstring, 0,8)."-";
        //$str.= substr($randstring, 8,4)."-";
        //$str.= substr($randstring, 12,4)."-";
        //$str.= substr($randstring, 16,4)."-";        
        //$str.= substr($randstring, 20);
        return $randstring;
    }
    public static function randomNumber($len) {
        $chars = "0123456789";
        $randstring = '';
        $charsLen = strlen($chars) - 1;
        for ($i = 0; $i < $len; $i++) {
            $randstring .= $chars[rand(0, $charsLen)];
        }
        return $randstring;
    }
  
    public static function getRankVars($data) {
        preg_match_all("/(.*)=(.*)\n/", $data, $m);
        $vars = array();
        $i = 0;
        while (isset($m[1][$i])) {
            $vars[$m[1][$i]] = array(
                'key'=>$m[1][$i],
                'value'=>$m[2][$i]
            );
            $i++;
        }
        return $vars;
    }
    
    public static function getRankByScore($score){
        $score = (int)$score;
        if($score < 6500){
            return 0;
        }
        $rnkArray = array(
                11000, //2
                18500,
                28000,
                40000,
                53000,
                68000,
                84000,
                100000,
                120000, //10
                138000,
                158000,
                179000,
                200000,
                224000,
                247000,
                272000,
                297000,
                323000,
                350000,//20
                377000,
                405000,
                437000,
                472000,
                537000,
                620000,
                720000,
                832000,
                956000,
                1090000,//30
                1240000,
                1400000,
                1550000,
                1730000,
                1900000,
                2100000,
                2300000,
                2500000,
                2700000,
                2900000,//40
                3140000,
                3370000,
                3600000,
                3800000,
                4010000,
                4300000,
                4600000,
                4900000,
                5100000,
                5400000 //50           
                );
        $i = 0;
        while(isset($rnkArray[$i])){            
            if($score < $rnkArray[$i]){
                return $i+1;                
            }
            $i++;
        }        
        return 50;
    }

}
