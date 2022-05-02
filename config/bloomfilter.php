<?php
include_once(app_path() . '\Http\Controllers\bloomclass.php');
$num = 20;
$parameters = array(
    'entries_max' => $num,
);
$bloom = new Bloom($parameters);
$ser = serialize($bloom);
return [
    'bloom' => $ser,
    'num' => $num
];