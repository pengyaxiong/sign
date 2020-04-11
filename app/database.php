<?php

if(file_exists(CMF_ROOT."data/conf/database.php")){
    $database=include CMF_ROOT."data/conf/database.php";
}else{
    $database=[];
}

return $database;
