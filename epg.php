<?php
/*Rev:26.09.18r0*/

class E3223A8ad822526d8F69418863b6E8B5
{
    public $validEpg = false;
    public $epgSource;
    public $from_cache = false;
    function __construct($F3803fa85b38b65447e6d438f8e9176a, $F7b03a1f7467c01c6ea18452d9a5202f = false)
    {
        $this->eCe97C9Fe9A866e5B522e80E43B30997($F3803fa85b38b65447e6d438f8e9176a, $F7b03a1f7467c01c6ea18452d9a5202f);
    }
    public function a53d17AB9BD15890715e7947C1766953()
    {
        $output = array();
        foreach ($this->epgSource->channel as $d76067cf9572f7a6691c85c12faf2a29) {
            $e818ebc908da0ee69f4f99daba6a1a18 = trim((string) $d76067cf9572f7a6691c85c12faf2a29->attributes()->id);
            $cfd246a8499e5bb4a9d89e37c524322a = !empty($d76067cf9572f7a6691c85c12faf2a29->{'display-name'}) ? trim((string) $d76067cf9572f7a6691c85c12faf2a29->{'display-name'}) : '';
            if (array_key_exists($e818ebc908da0ee69f4f99daba6a1a18, $output)) {
                continue;
            }
            $output[$e818ebc908da0ee69f4f99daba6a1a18] = array();
            $output[$e818ebc908da0ee69f4f99daba6a1a18]['display_name'] = $cfd246a8499e5bb4a9d89e37c524322a;
            $output[$e818ebc908da0ee69f4f99daba6a1a18]['langs'] = array();
            //Cd7ed0f389db780d6e6cf7cf5fdd8204:
        }
        foreach ($this->epgSource->programme as $d76067cf9572f7a6691c85c12faf2a29) {
            $e818ebc908da0ee69f4f99daba6a1a18 = trim((string) $d76067cf9572f7a6691c85c12faf2a29->attributes()->channel);
            if (!array_key_exists($e818ebc908da0ee69f4f99daba6a1a18, $output)) {
                continue;
            }
            $b798ef834bcdc73cfeb4e4e0309db68d = $d76067cf9572f7a6691c85c12faf2a29->title;
            foreach ($b798ef834bcdc73cfeb4e4e0309db68d as $E4416ae8f96620daee43ac43f9515200) {
                $lang = (string) $E4416ae8f96620daee43ac43f9515200->attributes()->lang;
                if (!in_array($lang, $output[$e818ebc908da0ee69f4f99daba6a1a18]['langs'])) {
                    $output[$e818ebc908da0ee69f4f99daba6a1a18]['langs'][] = $lang;
                }
            }
            //A5a8cb203ee80bed6d3d557012a3bfee:
        }
        return $output;
    }
    public function a0b90401c3241088846A84F33c2B50fF($E2b08d0d6a74fb4e054587ee7c572a9f, $dfc6b62ce4c2bd11aeb45ae2e9441819)
    {
        global $f566700a43ee8e1f0412fe10fbdf03df;
        $f8f0da104ec866e0d96947b27214d28a = array();
        foreach ($this->epgSource->programme as $d76067cf9572f7a6691c85c12faf2a29) {
            $e818ebc908da0ee69f4f99daba6a1a18 = (string) $d76067cf9572f7a6691c85c12faf2a29->attributes()->channel;
            if (!array_key_exists($e818ebc908da0ee69f4f99daba6a1a18, $dfc6b62ce4c2bd11aeb45ae2e9441819)) {
                continue;
            }
            $ff153ef1378baba89ae1f33db3ad14bf = $Fe7c1055293ad23ed4b69b91fd845cac = '';
            $start = strtotime(strval($d76067cf9572f7a6691c85c12faf2a29->attributes()->start));
            $stop = strtotime(strval($d76067cf9572f7a6691c85c12faf2a29->attributes()->stop));
            if (empty($d76067cf9572f7a6691c85c12faf2a29->title)) {
                continue;
            }
            $b798ef834bcdc73cfeb4e4e0309db68d = $d76067cf9572f7a6691c85c12faf2a29->title;
            if (is_object($b798ef834bcdc73cfeb4e4e0309db68d)) {
                $A2b796e1bb70296d4bed8ce34ce5691b = false;
                foreach ($b798ef834bcdc73cfeb4e4e0309db68d as $E4416ae8f96620daee43ac43f9515200) {
                    if ($E4416ae8f96620daee43ac43f9515200->attributes()->lang == $dfc6b62ce4c2bd11aeb45ae2e9441819[$e818ebc908da0ee69f4f99daba6a1a18]['epg_lang']) {
                        $A2b796e1bb70296d4bed8ce34ce5691b = true;
                        $ff153ef1378baba89ae1f33db3ad14bf = base64_encode($E4416ae8f96620daee43ac43f9515200);
                        break;
                    }
                }
                if (!$A2b796e1bb70296d4bed8ce34ce5691b) {
                    $ff153ef1378baba89ae1f33db3ad14bf = base64_encode($b798ef834bcdc73cfeb4e4e0309db68d[0]);
                }
            } else {
                $ff153ef1378baba89ae1f33db3ad14bf = base64_encode($b798ef834bcdc73cfeb4e4e0309db68d);
            }
            if (!empty($d76067cf9572f7a6691c85c12faf2a29->desc)) {
                $d1294148eb5638fe195478093cd6b93b = $d76067cf9572f7a6691c85c12faf2a29->desc;
                if (is_object($d1294148eb5638fe195478093cd6b93b)) {
                    $A2b796e1bb70296d4bed8ce34ce5691b = false;
                    foreach ($d1294148eb5638fe195478093cd6b93b as $d4c3c80b508f5d00d05316e7aa0858de) {
                        if ($d4c3c80b508f5d00d05316e7aa0858de->attributes()->lang == $dfc6b62ce4c2bd11aeb45ae2e9441819[$e818ebc908da0ee69f4f99daba6a1a18]['epg_lang']) {
                            $A2b796e1bb70296d4bed8ce34ce5691b = true;
                            $Fe7c1055293ad23ed4b69b91fd845cac = base64_encode($d4c3c80b508f5d00d05316e7aa0858de);
                            break;
                        }
                    }
                    if (!$A2b796e1bb70296d4bed8ce34ce5691b) {
                        $Fe7c1055293ad23ed4b69b91fd845cac = base64_encode($d1294148eb5638fe195478093cd6b93b[0]);
                    }
                } else {
                    $Fe7c1055293ad23ed4b69b91fd845cac = base64_encode($d76067cf9572f7a6691c85c12faf2a29->desc);
                }
            }
            $e818ebc908da0ee69f4f99daba6a1a18 = addslashes($e818ebc908da0ee69f4f99daba6a1a18);
            $dfc6b62ce4c2bd11aeb45ae2e9441819[$e818ebc908da0ee69f4f99daba6a1a18]['epg_lang'] = addslashes($dfc6b62ce4c2bd11aeb45ae2e9441819[$e818ebc908da0ee69f4f99daba6a1a18]['epg_lang']);
            $A73d5129dfb465fd94f3e09e9b179de0 = date('Y-m-d H:i:s', $start);
            $cdd6af41b10abec2ff03fe043f3df1cf = date('Y-m-d H:i:s', $stop);
            $f8f0da104ec866e0d96947b27214d28a[] = '(\'' . $f566700a43ee8e1f0412fe10fbdf03df->escape($E2b08d0d6a74fb4e054587ee7c572a9f) . '\', \'' . $f566700a43ee8e1f0412fe10fbdf03df->escape($e818ebc908da0ee69f4f99daba6a1a18) . '\', \'' . $f566700a43ee8e1f0412fe10fbdf03df->escape($A73d5129dfb465fd94f3e09e9b179de0) . '\', \'' . $f566700a43ee8e1f0412fe10fbdf03df->escape($cdd6af41b10abec2ff03fe043f3df1cf) . '\', \'' . $f566700a43ee8e1f0412fe10fbdf03df->escape($dfc6b62ce4c2bd11aeb45ae2e9441819[$e818ebc908da0ee69f4f99daba6a1a18]['epg_lang']) . '\', \'' . $f566700a43ee8e1f0412fe10fbdf03df->escape($ff153ef1378baba89ae1f33db3ad14bf) . '\', \'' . $f566700a43ee8e1f0412fe10fbdf03df->escape($Fe7c1055293ad23ed4b69b91fd845cac) . '\')';
            //E45bcbb8d283399c92d22750351d1ab6:
        }
        return !empty($f8f0da104ec866e0d96947b27214d28a) ? $f8f0da104ec866e0d96947b27214d28a : false;
    }
    public function ece97c9FE9a866e5B522E80e43b30997($F3803fa85b38b65447e6d438f8e9176a, $F7b03a1f7467c01c6ea18452d9a5202f)
    {
        $F1350a5569e4b73d2f9cb26483f2a0c1 = pathinfo($F3803fa85b38b65447e6d438f8e9176a, PATHINFO_EXTENSION);
        if (($F1350a5569e4b73d2f9cb26483f2a0c1 == 'gz')) {
            $d31de515789f8101b06d8ca646ef5e24 = file_get_contents($F3803fa85b38b65447e6d438f8e9176a);
            $a41f6a5b2ce6655f27b7747349ad1f33 = simplexml_load_string($d31de515789f8101b06d8ca646ef5e24, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_PARSEHUGE);
            //f53efc9e57dac86f544eb9ab87dc3c09:
            $d31de515789f8101b06d8ca646ef5e24 = gzdecode(file_get_contents($F3803fa85b38b65447e6d438f8e9176a));
            $a41f6a5b2ce6655f27b7747349ad1f33 = simplexml_load_string($d31de515789f8101b06d8ca646ef5e24, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_PARSEHUGE);
            //goto a36ffe2c42f7fe1e92fe8e9145a803a6;
        }
        else if ($F1350a5569e4b73d2f9cb26483f2a0c1 == 'xz') {
            $d31de515789f8101b06d8ca646ef5e24 = shell_exec("wget -qO- \"{$F3803fa85b38b65447e6d438f8e9176a}\" | unxz -c");
            $a41f6a5b2ce6655f27b7747349ad1f33 = simplexml_load_string($d31de515789f8101b06d8ca646ef5e24, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_PARSEHUGE);
        } 
        if ($a41f6a5b2ce6655f27b7747349ad1f33 !== false) {
            $this->epgSource = $a41f6a5b2ce6655f27b7747349ad1f33;
            if (empty($this->epgSource->programme)) {
                A78bf8D35765BE2408C50712cE7a43aD::E501281ad19aF8A4BBbf9BED91Ee9299('Not A Valid EPG Source Specified or EPG Crashed: ' . $F3803fa85b38b65447e6d438f8e9176a);
            } else {
                $this->validEpg = true;
            }
        } else {
            a78bF8D35765Be2408C50712cE7a43aD::e501281AD19AF8a4BBbF9BED91EE9299('No XML Found At: ' . $F3803fa85b38b65447e6d438f8e9176a);
        }
        $a41f6a5b2ce6655f27b7747349ad1f33 = $d31de515789f8101b06d8ca646ef5e24 = null; 
    }
}
?>
