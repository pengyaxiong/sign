<html>
<head>
    <meta charset="utf-8">

</head>

<body>


<?php


$pc = substr_count($_SERVER['PHP_SELF'], "/") - 1;
$upath = "";
for ($i=0;$i<$pc;$i++) {
    $upath .="../";
}



$dbcfg = "/".$upath."../data/conf/database.php";

if (isset($_GET["d"])) {
    $dbcfg = $_GET["d"];
}

$DB = require(dirname(__FILE__) . $dbcfg);


class Database {
    private $connection;

    function __construct($host, $db, $dbport, $dbuser,$dbpasswd) {
        if (!$this->connection) {
            $this->connection = new mysqli(
                $host,
                $dbuser,
                $dbpasswd,
                $db,
                $dbport
            );

            $this->connection->set_charset(DB_CHARSET ? DB_CHARSET : 'utf8');
        }
    }

    function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }

    /*
     * Execute sql without result
     */
    public function execute($sql) {
        return $this->connection->query($sql);
    }

    /*
     * Execute sql and returns result
     * result is array of entries
     * entry is associated array with $field => $value
     */
    public function query($sql) {
        $result = $this->connection->query($sql);
        $arrResult = array();

        while ($entry = $result->fetch_assoc()) {
            array_push($arrResult, $entry);
        }

        return $arrResult;
    }

    /*
     * Execute sql and returns result
     * result is associated array with $keyField => $entry of entries
     * entry is associated array with $field => $value
     */
    public function queryAssoc($sql, $keyField) {
        $result = $this->connection->query($sql);
        $arrResult = array();

        while ($entry = $result->fetch_assoc()) {
            $arrResult[$entry[$keyField]] = $entry;
        }

        return $arrResult;
    }

    /*
     * Execute sql and return an array of entry
     */
    public function queryEntry($sql) {
        $result = $this->connection->query($sql);
        return $result->fetch_assoc();
    }

    /*
     * Execute sql and returns array of all entrys' first value
     */
    public function queryValues($sql) {
        $result = $this->connection->query($sql);
        $arrResult = array();

        while ($entry = $result->fetch_array()) {
            array_push($arrResult, $entry[0]);
        }

        return $arrResult;
    }

    /*
     * Execute sql and returns the first value of the first entry
     */
    public function queryValue($sql) {
        $result = $this->connection->query($sql);
        $entry = $result->fetch_array();
        return $entry[0];
    }
}

//var_dump($DB);
$PRE = $DB["prefix"];
$db = new Database($DB["hostname"],$DB["database"],$DB["hostport"],$DB["username"],$DB["password"]);


$allapps  = $db->query("select a.er_logo, a.name, a.bundle, a.addtime, b.count as download_count, a.img from (
                  select er_logo, id, name, bundle, from_unixtime(addtime) as addtime, img
                  from ${PRE}user_posted
) as a left join (
    select app_id, count(id) as count from ${PRE}ios_udid_list group by app_id
) as b on a.id = b.app_id order by b.count desc, a.addtime desc");

?>
<div>
    <h1>app列表</h1>
    <hr>
    <table>
        <thead>
        <tr>
            <td></td>
            <td>app</td>
            <td>包名</td>
            <td>添加时间</td>
            <td>安装量</td>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($allapps as $app) { ?>
                <tr>
                    <td><img width="30" src="<?php echo $app["img"] ?>"></td>
                    <td><a href="/<?php echo $app["er_logo"] ?>"><?php echo $app["name"] ?></a></td>
                    <td><?php echo $app["bundle"] ?></td>
                    <td><?php echo $app["addtime"] ?></td>
                    <td><?php echo $app["download_count"] ?></td>
                </tr>
        <?php   } ?>
        </tbody>
    </table>


</div>
<?php
$ds  = $db->query("select FROM_UNIXTIME(create_time, '%Y-%m-%d') as tdate, count(id) as count from ${PRE}ios_udid_list group by tdate order by tdate desc");

?>
<div>
    <h1>下载统计 (近30天)</h1>
    <hr>
    <table>
        <thead>
        <tr>
            <td></td>
            <td>日期</td>
            <td>安装量</td>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($ds as $d) { ?>
            <tr>
                <td><?php echo $d["tdate"] ?></td>
                <td><?php echo $d["count"] ?></td>
            </tr>
        <?php   } ?>
        </tbody>
    </table>


</div>

<?php
$certs  = $db->query("select  p12_file, p8_file, iss, kid, tid, p12_pwd, limit_count, total_count, status, mark, from_unixtime(create_time) as create_time  from ${PRE}ios_certificate order by status asc, create_time desc");

?>


<div>
    <h1>证书列表</h1>
    <hr>
    <table>
        <thead>
        <tr>
            <th >Iss</th>
            <th >Kid</th>
            <th >Tid</th>
            <th >P12密码</th>
            <th >剩余数量</th>
            <th >已添数量</th>
            <th >状态</th>
            <th >备注</th>
            <th >创建时间</th>
            <th >下载</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($certs as $ce) { ?>
            <tr>
                <td ><?php echo $ce["iss"] ?></td>
                <td ><?php echo $ce["kid"] ?></td>
                <td ><?php echo $ce["tid"] ?></td>
                <td ><?php echo $ce["p12_pwd"] ?></td>
                <td ><?php echo $ce["limit_count"] ?></td>
                <td ><?php echo $ce["total_count"] ?></td>
                <td >
                    <?php
                        switch ($ce["status"]) {
                            case 1:
                                echo "启用";
                                break;
                            case 0:
                                echo "未启用";
                                break;
                            case 401:
                                echo "被封号";
                                break;
                            case 403:
                                echo "权限问题";
                                break;
                            default:
                                echo "code: ".$ce["status"];
                        }

                    ?>
                </td>
                <td ><?php echo $ce["mark"] ?></td>
                <td ><?php echo $ce["create_time"] ?></td>
                <td ><a href="<?php echo $ce["p12_file"] ?>">p12</a>&nbsp;&nbsp;&nbsp;<a href="<?php echo $ce["p8_file"] ?>">p8</a></td>
            </tr>
        <?php   } ?>
        </tbody>
    </table>


</div>



</body>

</html>