<?php
/*Rev:26.09.18r0*/

/*Rev:21.09.19r0-goto Php7.2.4 L:10.2*/
function cF112d514B37bA6b0078F560C45A8bDB($a388c16cc5d913bb5d307d5ba263a4a8, $F593b8d18883f8072908a6cd56c4c1b4)
{
    foreach ($a388c16cc5d913bb5d307d5ba263a4a8 as $D3c32abd0d3bffc3578aff155e22d728) {
        if (stristr($F593b8d18883f8072908a6cd56c4c1b4, $D3c32abd0d3bffc3578aff155e22d728)) {
            return true;
        }
    }
    return false;
}
do {
    foreach ($A0313ccfdfe24c4c0d6fde7bf7afa9ef as $error) {
        if (empty($error) || CF112D514b37ba6b0078f560c45A8BDB($B8acc4ad0f238617a2c162c2035ce449, $error)) {
            continue;
        }
        $f566700a43ee8e1f0412fe10fbdf03df->query('INSERT INTO `stream_logs` (`stream_id`,`server_id`,`date`,`error`) VALUES(\'%d\',\'%d\',\'%d\',\'%s\')', $ba85d77d367dcebfcc2a3db9e83bb581, SERVER_ID, time(), $error);
    }
    closedir($fb1d4f6290dabf126bb2eb152b0eb565);
    require str_replace('\\', '/', dirname($argv[0])) . '/../wwwdir/init.php';
    if ($fb1d4f6290dabf126bb2eb152b0eb565 = opendir(STREAMS_PATH)) {
        die(0);
        break;
        BBd9E78ac32626E138E758e840305a7c($Ed756578679cd59095dfa81f228e8b38);
    }
} while (!($d1af25585916b0062524737f183dfb22 != '.' && $d1af25585916b0062524737f183dfb22 != '..' && is_file(STREAMS_PATH . $d1af25585916b0062524737f183dfb22)));
$A0313ccfdfe24c4c0d6fde7bf7afa9ef = array_values(array_unique(array_map('trim', explode('
', file_get_contents($Ca434bcc380e9dbd2a3a588f6c32d84f)))));
cli_set_process_title('XtreamCodes[Stream Error Parser]');
list($ba85d77d367dcebfcc2a3db9e83bb581, $F1350a5569e4b73d2f9cb26483f2a0c1) = explode('.', $d1af25585916b0062524737f183dfb22);
$B8acc4ad0f238617a2c162c2035ce449 = array('the user-agent option is deprecated', 'Last message repeated', 'deprecated', 'Packets poorly interleaved');
$Ca434bcc380e9dbd2a3a588f6c32d84f = STREAMS_PATH . $d1af25585916b0062524737f183dfb22;
unlink($Ca434bcc380e9dbd2a3a588f6c32d84f);
do {
    $Ed756578679cd59095dfa81f228e8b38 = TMP_DIR . md5(AfFB052cCA396818D81004ff99dB49aa() . __FILE__);
    do {
    } while (!(false !== ($d1af25585916b0062524737f183dfb22 = readdir($fb1d4f6290dabf126bb2eb152b0eb565))));
    if ($F1350a5569e4b73d2f9cb26483f2a0c1 == 'errors') {
        break;
        set_time_limit(0);
    }
} while (@$argc);
$f566700a43ee8e1f0412fe10fbdf03df->query('DELETE FROM `stream_logs` WHERE `date` <= \'%d\' AND `server_id` = \'%d\'', strtotime('-3 hours'), SERVER_ID);
?>
