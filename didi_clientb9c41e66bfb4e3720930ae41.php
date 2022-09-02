<?php
/**
 * @author seowhy
 * 本文件遵循LGPL开源协议
 * 版权保护，如需使用，请访问 https://didi.seowhy.com
 * 微信：seowhydidi
 * 电话：18705921889
 */


//支持报错
ini_set("display_errors", 0);
error_reporting(E_ALL & ~E_NOTICE);

define('DS', DIRECTORY_SEPARATOR);
define("ROOT_PATH", __DIR__ . DS);
//验证
define('SITE_ID', '3916999');
define('TOKEN', '90c97d2f10b4fd34bf858fcb64740b9ab55809a795339dc5e365fbdcb11604dfad4d3d4a238a1e9e87fdbad5723d58b3928a6ba28c12e825252baa178b2487f2');
define('VERSION', '0.0.26');
$token      = $_SERVER['HTTP_DIDI_TOKEN'];
$tokenTime  = $_SERVER['HTTP_DIDI_TOKEN_TIME'];
if (version_compare(PHP_VERSION, '5.3.0', '<')) return res(1001, '当前PHP版本为' . phpversion() . '小于5.3,请升级php版本5.3以上');
if (!class_exists('PDO', false)) return res(1003, "空间不支持pdo连接，接口文件将无法正常工作");
if (!$token) die('<div style="text-align: center;margin-top: 100px;color: green;">搜外友链-自动上链接口环境正常，如有疑问可加QQ群反馈：816424714</div>');
if (md5(SITE_ID . TOKEN . $tokenTime) != $token) return res(1001, "访问受限");


$client = new appClient();
$client->run();


class appClient
{

    private $configPath;
    private $config = array();

    public function __construct()
    {
        @set_error_handler(array(&$this, 'error_handler'), E_ALL & ~E_NOTICE & ~E_WARNING);
        @set_exception_handler(array(&$this, 'exception_handler'));
        $this->configPath = ROOT_PATH . 'didi.config.php';
        if (!file_exists($this->configPath)) {
            $this->checkProvider();
        } else {
            $this->config = include($this->configPath);
            if (empty($this->config['is_checked'])) {
                $this->checkProvider();
            }
        }
    }

    public function error_handler($type, $message, $file, $line)
    {
        $msg = $message . ' ' . $file . ' ' . $line;
        self::whetherOut($msg, $type);
    }

    public function exception_handler($error)
    {
        $msg = $error->getMessage() . ' ' . $error->getFile() . ' ' . $error->getLine() . ' ';
        self::whetherOut($msg, $error->getCode());
    }

    private static function whetherOut($str, $type = 30719)
    {
        if (intval($type) <= 30719) {
            res(-1, $str);
        }
    }

    public function run()
    {
        $action = $_POST['a'];
        if (in_array($action, array('ConfigSet', 'VisitCheck', 'UpdateSystemIp', 'GetConfig', 'UpdateVersion'))) {
            return $this->{$action}();
        }
        if (empty($this->config['is_checked'])) {
            res(1002, "接口配置异常，无法正常读取配置信息");
        } else {
            $appAction = new appAction($this->config);
            $appAction->handle();
        }
    }

    private function UpdateVersion()
    {
        $content = curl_get_contents('https://didi.seowhy.com/auto_exchange_update_version?site_id=' . SITE_ID . '&token=' . TOKEN);
        if (strlen($content) > 1024) {
            $status = file_put_contents(__FILE__, $content);
            if ($status) return resSucc('升级成功');
        }
        return resErr('升级失败');
    }

    private function GetConfig()
    {
        $config = array();
        $config['version'] = VERSION;
        return resSucc('', $config);
    }

    private function VisitCheck()
    {
        return resSucc();
    }

    private function ConfigSet()
    {
        self::checkProvider();
        if (empty($this->config['is_checked'])) {
            res(1002, "配置失败，无法正常连接数据库");
        }
        res(0, "配置成功");
    }

    private function UpdateSystemIp()
    {
        $this->systemIpSet();
        self::writeConfig();
    }


    private function checkProvider()
    {
        if (!is_array($this->config)) {
            $this->config = array();
        }

        $configData = formatParam('config', 'array');

        if ($configData) {
            $this->config = $configData;
        }

        switch ($this->config['system_type']) {
            case 'wordpress':
                self::readWordPressConfig();
                break;
            case 'dedecms':
                self::readDedecmsConfig();
                break;
            case 'phpcms':
                self::readPhpcmsConfig();
                break;
            case 'empire':
                self::readEmpireConfig();
                break;
            case 'zblog':
                self::readZblogConfig();
                break;
            case 'maccms':
                self::readMaccms();
                break;
            case 'eyoucms':
                self::readEyoucms();
                break;
            default:
                if (file_exists(ROOT_PATH . 'wp-config.php')) {                                     //wordpress
                    self::readWordPressConfig();
                } else if (file_exists(ROOT_PATH . 'data' . DS . 'common.inc.php')) {                    //dedecms
                    self::readDedecmsConfig();
                } else if (file_exists(ROOT_PATH . 'caches' . DS . 'configs' . DS . 'database.php')) {      //phpcms
                    self::readPhpcmsConfig();
                } else if (file_exists(ROOT_PATH . 'e' . DS . 'config' . DS . 'config.php')) {               //empire
                    self::readEmpireConfig();
                } else if (file_exists(ROOT_PATH . 'zb_users')) {               //zblog
                    self::readZblogConfig();
                }
        }

        if (!$this->config) return res(1004, "读取数据库配置失败");


        //检查配置的准确性
        self::checkConfig();
        return res(0, "配置写入成功", $this->config);
    }


    private function readWordPressConfig($configUrl = null)
    {
        if (!$configUrl) {
            $configUrl = ROOT_PATH . 'wp-config.php';
        }

        if (file_exists($configUrl)) {
            include $configUrl;
            $this->config['database'] = array();
            $hostArr = explode(":", DB_HOST);
            $this->config['database']['host'] = $hostArr[0];
            $this->config['database']['port'] = $hostArr[1] ? $hostArr[1] : '3306';
            $this->config['database']['user'] = DB_USER;
            $this->config['database']['password'] = DB_PASSWORD;
            $this->config['database']['database'] = DB_NAME;
            $this->config['database']['charset'] = DB_CHARSET;
            $this->config['database']['prefix'] = $table_prefix;
        }
        $this->config['system_type'] = 'wordpress';
        $this->config['friendLink']['table'] = $this->config['database']['prefix'] .'links';
        $this->config['friendLink']['urlField'] = 'link_url';
        $this->config['friendLink']['titleField'] = 'link_name';
        $this->config['friendLink']['idField'] = 'link_id';
        $this->config['friendLink']['statusField'] = array();
        $this->config['friendLink']['statusField']['hideValue'] = 'N';
        $this->config['friendLink']['statusField']['showValue'] = 'Y';
        $this->config['friendLink']['statusField']['field'] = 'link_visible';
        $this->config['friendLink']['typeField'] = array();
        $this->config['friendLink']['typeField']['value'] = 1;
        $this->config['friendLink']['typeField']['field'] = 'link_owner';


        $this->config['friendLinkCategoryRelation']['table']                        =   $this->config['database']['prefix'] .'term_relationships';'term_relationships';
        $this->config['friendLinkCategoryRelation']['objIdField']['field']          =   'object_id';
        $this->config['friendLinkCategoryRelation']['categoryField']['field']       =   'term_taxonomy_id';
        $this->config['friendLinkCategoryRelation']['categoryField']['value']       =   0;
        $this->config['friendLinkCategoryRelation']['status'] = false;


    }

    private function readDedecmsConfig($configUrl = null)
    {

        if ($configUrl === null) $configUrl = ROOT_PATH . 'data' . DS . 'common.inc.php';

        if (file_exists($configUrl)) {
            include $configUrl;
            $this->config['database'] = array();
            $hostArr = explode(":", $cfg_dbhost);
            $this->config['database']['host'] = $hostArr[0];
            $this->config['database']['port'] = $hostArr[1] ? $hostArr[1] : '3306';
            $this->config['database']['user'] = $cfg_dbuser;
            $this->config['database']['password'] = $cfg_dbpwd;
            $this->config['database']['database'] = $cfg_dbname;
            $this->config['database']['charset'] = $cfg_db_language;
            $this->config['database']['prefix'] = $cfg_dbprefix;
        }

        $this->config['system_type'] = 'dedecms';
        $this->config['friendLink']['table'] = $this->config['database']['prefix'] .'flink';
        $this->config['friendLink']['emailField'] = 'email';
        $this->config['friendLink']['addTimeField'] = 'dtime';
        $this->config['friendLink']['urlField'] = 'url';
        $this->config['friendLink']['titleField'] = 'webname';
        $this->config['friendLink']['statusField'] = array();
        $this->config['friendLink']['statusField']['hideValue'] = 0;
        $this->config['friendLink']['statusField']['showValue'] = 2;
        $this->config['friendLink']['statusField']['field'] = 'ischeck';
        $this->config['friendLink']['typeField'] = array();
        $this->config['friendLink']['typeField']['value'] = 1;
        $this->config['friendLink']['typeField']['field'] = 'typeid';

    }

    private function readPhpcmsConfig($configUrl = null)
    {
        if (!$configUrl) {
            $configUrl = ROOT_PATH . 'caches' . DS . 'configs' . DS . 'database.php';
        }

        if (file_exists($configUrl)) {
            $fileConfig = @require($configUrl);
            $this->config['database'] = array();
            $this->config['database']['host'] = $fileConfig['default']['hostname'];
            $this->config['database']['port'] = $fileConfig['default']['port'];
            $this->config['database']['user'] = $fileConfig['default']['username'];
            $this->config['database']['password'] = $fileConfig['default']['password'];
            $this->config['database']['database'] = $fileConfig['default']['database'];
            $this->config['database']['charset'] = $fileConfig['default']['charset'];
            $this->config['database']['prefix'] = $fileConfig['default']['tablepre'];
        }


        $this->config['friendLink']['table'] = $this->config['database']['prefix'] .'link';
        $this->config['friendLink']['addTimeField']     = 'addtime';
        $this->config['friendLink']['urlField']         = 'url';
        $this->config['friendLink']['titleField']       = 'name';
        $this->config['friendLink']['statusField']      = array();
        $this->config['friendLink']['statusField']['hideValue'] = 0;
        $this->config['friendLink']['statusField']['showValue'] = 1;
        $this->config['friendLink']['statusField']['field'] = 'passed';
        $this->config['friendLink']['typeField'] = array();
        $this->config['friendLink']['typeField']['value'] = 0;
        $this->config['friendLink']['typeField']['field'] = 'linktype';
        $this->config['friendLink']['siteField']['field'] = 'siteid';
        $this->config['friendLink']['siteField']['value'] = 1;
        $this->config['system_type'] = 'phpcms';

    }

    private function readEmpireConfig($configUrl = null)
    {
        if (!$configUrl) {
            $configUrl = ROOT_PATH . 'e' . DS . 'config' . DS . 'config.php';
        }

        if (file_exists($configUrl)) {
            define("InEmpireCMS", "1");
            include $configUrl;
            $this->config['database'] = array();
            $this->config['database']['host'] = $ecms_config['db']['dbserver'];
            $this->config['database']['port'] = $ecms_config['db']['dbport'];
            $this->config['database']['user'] = $ecms_config['db']['dbusername'];
            $this->config['database']['password'] = $ecms_config['db']['dbpassword'];
            $this->config['database']['database'] = $ecms_config['db']['dbname'];
            $this->config['database']['charset'] = $ecms_config['db']['setchar'];
            $this->config['database']['prefix'] = $ecms_config['db']['dbtbpre'];
        }

        $this->config['system_type'] = 'empire';
        $this->config['friendLink']['table'] = $this->config['database']['prefix'] .'enewslink';       //友链表
        $this->config['friendLink']['titleField'] = 'lname';          //分类标题表
        $this->config['friendLink']['urlField'] = 'lurl';
        $this->config['friendLink']['statusField'] = array();
        $this->config['friendLink']['statusField']['hideValue'] = 0;
        $this->config['friendLink']['statusField']['showValue'] = 1;
        $this->config['friendLink']['statusField']['field'] = 'checked';
        $this->config['friendLink']['typeField'] = array();
        $this->config['friendLink']['typeField']['value'] = 0;
        $this->config['friendLink']['typeField']['field'] = 'ltype';

    }

    private function readZblogConfig()
    {
        if (empty($this->config['database'])) {
            $configFile = ROOT_PATH . "zb_users/c_option.php";
            if (file_exists($configFile)) {

                $fileConfig = include($configFile);

                $this->config['database'] = array();
                $this->config['database']['host'] = $fileConfig['ZC_MYSQL_SERVER'];
                $this->config['database']['port'] = $fileConfig['ZC_MYSQL_PORT'];
                $this->config['database']['user'] = $fileConfig['ZC_MYSQL_USERNAME'];
                $this->config['database']['password'] = $fileConfig['ZC_MYSQL_PASSWORD'];
                $this->config['database']['database'] = $fileConfig['ZC_MYSQL_NAME'];
                $this->config['database']['charset'] = $fileConfig['ZC_MYSQL_CHARSET'];
                $this->config['database']['prefix'] = $fileConfig['ZC_MYSQL_PRE'];
                $this->config['system_type'] = 'zblog';
                $this->config['friendLink']['table'] = $this->config['database']['prefix'] . 'module';

            }

        }

    }
    private function readTpNoPublic()
    {
        if (empty($this->config['database'])) {
            $configFile = ROOT_PATH . "application/database.php";
            if (file_exists($configFile)) {

                $fileConfig = include($configFile);

                $this->config['database'] = array();
                $this->config['database']['host'] = $fileConfig['hostname'];
                $this->config['database']['port'] = $fileConfig['hostport'];
                $this->config['database']['user'] = $fileConfig['username'];
                $this->config['database']['password'] = $fileConfig['password'];
                $this->config['database']['database'] = $fileConfig['database'];
                $this->config['database']['charset'] = $fileConfig['charset'];
                $this->config['database']['prefix'] = $fileConfig['prefix'];

            }
        }
    }
    private function readMaccms(){

        self::readTpNoPublic();
        self::readMaccmsField();

    }
    private function readEyoucms(){
        self::readTpNoPublic();
        self::readEyoucmsField();
    }

    //加载 maccms 系统的字段
    private function readMaccmsField()
    {
        $this->config['friendLink']['table']                = $this->config['database']['prefix'] . 'link';
        $this->config['friendLink']['titleField']           = 'link_name';          //分类标题表
        $this->config['friendLink']['urlField']             = 'link_url';
        $this->config['friendLink']['typeField']            = array();
        $this->config['friendLink']['typeField']['value']   = 0;
        $this->config['friendLink']['typeField']['field']   = 'link_type';
        $this->config['friendLink']['addTimeField']         = 'link_add_time';
    }

    private function readEyoucmsField()
    {
        $this->config['friendLink']['table']                    = $this->config['database']['prefix'] . 'links';
        $this->config['friendLink']['titleField']               = 'title';          //分类标题表
        $this->config['friendLink']['urlField']                 = 'url';
        $this->config['friendLink']['typeField']                = array();
        $this->config['friendLink']['typeField']['value']       = 1;
        $this->config['friendLink']['typeField']['field']       = 'typeid';
        $this->config['friendLink']['addTimeField']             = 'add_time';
        $this->config['friendLink']['statusField']              = array();
        $this->config['friendLink']['statusField']['hideValue'] = 0;
        $this->config['friendLink']['statusField']['showValue'] = 1;
        $this->config['friendLink']['statusField']['field']     = 'status';
        $this->config['friendLink']['emailField']               = 'email';
        $this->config['friendLink']['groupField'] = array();
        $this->config['friendLink']['groupField']['value'] = 1;
        $this->config['friendLink']['groupField']['field'] = 'groupid';
    }

    private function checkConfig()
    {
        if (!is_array($this->config) || !$this->config['database']) {
            return res(-1, '配置错误');
        }

        //尝试连接数据库
        $mysql = new pdoMysql($this->config['database']);
        //检查表
        $tables = $mysql->list_tables();

        if (in_array($this->config['system_type'], array('diy'))) {

            $friendLink = array();
            $friendLinkCheckField = array(
                'titleField' => array('title', 'name', 'lname')
            , 'urlField' => array('url', 'link_url')
            );

            //先完全匹配 再模糊匹配
            foreach ($tables as $table) {
                if (!$friendLink['table'] && preg_match('/^[^_]*_'.$this->config['friendLink']['table'].'$/',$table)) {
                    $friendLink['table'] = $table;
                    //检查字段
                    $fields = $mysql->get_fields($table);
                    foreach ($fields as $field => $type) {
                        foreach ($friendLinkCheckField as $needField => $choiceFields) {
                            if ($this->config['friendLink'][$needField] && $field == $this->config['friendLink'][$needField]) {
                                $friendLink[$needField] = $field;
                            } else if (!$friendLink[$needField]) {
                                foreach ($choiceFields as $choiceField) {
                                    if (strpos($field, $choiceField) !== false) {
                                        $friendLink[$needField] = $choiceField;
                                        continue 2;
                                    }
                                }
                            }
                        }
                    }
                }
            }


            if (!$friendLink['table'] || !$friendLink['titleField'] || !$friendLink['urlField']) {
                foreach ($tables as $table) {
                    if (!$friendLink['table'] && strpos($table, $this->config['friendLink']['table']) !== false) {
                        $friendLink['table'] = $table;
                        //检查字段
                        $fields = $mysql->get_fields($table);
                        foreach ($fields as $field => $type) {
                            foreach ($friendLinkCheckField as $needField => $choiceFields) {
                                if ($this->config['friendLink'][$needField] && $field == $this->config['friendLink'][$needField]) {
                                    $friendLink[$needField] = $field;
                                } else if (!$friendLink[$needField]) {
                                    foreach ($choiceFields as $choiceField) {
                                        if (strpos($field, $choiceField) !== false) {
                                            $friendLink[$needField] = $choiceField;
                                            continue 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (!$friendLink['table'] || !$friendLink['titleField'] || !$friendLink['urlField']) {
                return res(1002, '友情链接表解析错误');
            }
            //储存表前缀
            preg_match("/[^_]*/", $friendLink['table'], $db_prefix);
            $this->config['friendLink'] = array_merge($this->config['friendLink'], $friendLink);
            $this->config['database']['db_prefix'] = $db_prefix[0] . '_';

        } else {
            //检测是否存在友链表
            if( !in_array($this->config['friendLink']['table'],$tables) ) return res(1004, "不存在友链表");
        }

        $this->config['is_checked'] = true;
        #$this->systemIpSet();
        self::writeConfig();

    }

    private function writeConfig()
    {
        $config_str = "<?php\n\n//didi_client配置文件\nreturn " . var_export($this->config, true) . ";";
        $result = @file_put_contents($this->configPath, $config_str);
        if (!$result) {
            return res(1002, "配置写入失败，没有写入权限");
        }

    }

    private function systemIpSet()
    {
        $data = json_decode(curl_get_contents('https://didi.seowhy.com/www/index/GetServerIp'), !0);
        if (!$data) return res(-1, '通讯搜外友链服务器错误，请重试');
        $data = $data['data'];
        $this->config['systemIp'] = $data['china'];
        $this->config['systemIpHK'] = $data['hk'];
        $this->config['systemIpUSA'] = $data['usa'];
    }


}

class appAction
{
    private $config;
    private $mysql;
    private $table;
    private $titleField;
    private $addTimeField;
    private $statusField;
    private $statusShowValue;
    private $urlField;
    private $emailField;
    private $groupField;
    private $idField;
    private $categoryField;
    private $qqField;
    private $typeField;
    private $siteField;
    private $fcrTable;         //友链关联分类表名
    private $fcrObjIdField;         //友链关联分类表 对象id字段
    private $fcrCategoryField;      //友链关联分类表 分类字段
    private $fcrStatus = false;      //是否友情链接有分类
    private $isDede         = false;
    private $isWordpress    = false;
    private $isEmpire       = false;
    private $isZblog        = false;
    private $isPhpcms       = false;
    private $isMaccms       = false;
    private $isEyoucms      = false;
    private $isTpNoPublic   = false;

    function __construct($config)
    {
        // if (!in_array(getIp(), array($config['systemIp'], $config['systemIpHK'], $config['systemIpUSA']))) return resErr('来源异常');
        $this->config = $config;
        $this->mysql = new pdoMysql($this->config['database']);
    }

    function FriendLinkAddAction()
    {
        $this->setFriendLinkTable();

        $title      = formatParam('title');
        $url        = formatParam('url');
        $qq         = formatParam('qq', 'intval');
        $email      = formatParam('email');

        $other_list = formatParam('other_list', 'array');
        if (!$other_list) {
            array_push($other_list, array('title' => $title, 'url' => $url, 'qq' => $qq, 'email' => $email));
        }
        if (!$other_list) return resErr();
        $status = false;
        foreach ($other_list as $k => $otherInfo) {
            $this->FriendLinkDelAction($url);
            if ($this->isZblog) {
                $this->zblogFriendLinkAdd($otherInfo);
                continue;
            }
            $insertData = array();
            if(isset($this->emailField))$insertData[$this->emailField]      = $otherInfo['email'];
            if(isset($this->qqField))$insertData[$this->qqField]            = $otherInfo['qq'];
            if(isset($this->titleField))$insertData[$this->titleField]      = $otherInfo['title'];
            if(isset($this->urlField))$insertData[$this->urlField]          = $otherInfo['url'];
            if(isset($this->addTimeField))$insertData[$this->addTimeField]  = time();
            if(isset($this->statusField))$insertData[$this->statusField]    = $this->statusShowValue;
            if(isset($this->siteField))$insertData[$this->siteField]        = $this->config['friendLink']['siteField']['value'];
            if(isset($this->typeField))$insertData[$this->typeField]        = $this->config['friendLink']['typeField']['value'];
            if(isset($this->groupField))$insertData[$this->groupField]      = $this->config['friendLink']['groupField']['value'];

            $status = $this->mysql->insert($insertData, $this->table, true);


            if($status && $this->fcrStatus){
                    //wordpress 有一些人是有分类
                    $this->friendLinkCategoryAdd($status);
            }

        }


        if (!$status) {
            return resErr('插入失败');
        }

        return resSucc();
    }

    function FriendLinkDelAction($urlParma='')
    {
        $this->setFriendLinkTable();
        $url = $urlParma!=''?$urlParma:formatParam('url');
        $url = preg_replace('/^http[s]*:\/\//', '', $url);
        $url = rtrim($url, '/');

        if ($this->isZblog) {
            return $this->zblogFriendLinkDel($url);
        }

        $delWhere = "{$this->urlField} in ('http://{$url}','https://{$url}','http://{$url}/','https://{$url}/')";

        if($this->fcrStatus){
            $needDellist = $this->mysql->select($this->idField,$this->table,$delWhere);
            foreach($needDellist as $k=>$needDelInfo){
                $this->friendLinkCategoryDel($needDelInfo[$this->idField]);
            }
        }

        $status = $this->mysql->delete($this->table, $delWhere);

        if($urlParma!='')return $status;

        if (!$status) {
            return resErr('删除失败');
        }
        return resSucc();
    }

    private function zblogFriendLinkAdd($otherInfo)
    {

        $linkInfo = $this->mysql->get_one('*', $this->table, "mod_FileName='link'");
        $meta = unserialize($linkInfo['mod_Meta']);
        $list = json_decode($meta['LM_json'], !0);
        if( !$list ) $list = array();
        $list[$otherInfo['title']] = array(
            'href' => $otherInfo['url']
        ,'ico' =>''
        , 'title' => $otherInfo['title']
        , 'target' => ''
        , 'text' => $otherInfo['title']
        , 'subs' => array()
        , 'issub' => 0);


        $html = $this->zblogObjectToHtml($list);

        $meta['LM_json'] = json_encode($list);
        $upData = array();
        $upData['mod_Meta'] = addslashes(serialize($meta));
        $upData['mod_Content'] = $html;
        $this->mysql->update($upData, $this->table, "mod_FileName='link'");
        return resSucc();
    }

    private function zblogFriendLinkDel($url)
    {
        $linkInfo = $this->mysql->get_one('*', $this->table, "mod_FileName='link'");
        $meta = unserialize($linkInfo['mod_Meta']);
        $list = json_decode($meta['LM_json'], !0);
        if( !$list ) $list = array();
        foreach ($list as $k => $v) {
            $seem = array('http://' . $url, 'https://' .$url , 'http://' . $url . '/', 'https://' . $url . '/');
            if (in_array($v['href'], $seem)) {
                unset($list[$k]);
            }
        }

        $html = $this->zblogObjectToHtml($list);
        $meta['LM_json'] = json_encode($list);
        $upData = array();
        $upData['mod_Meta'] = addslashes(serialize($meta));
        $upData['mod_Content'] = $html;
        $this->mysql->update($upData, $this->table,"mod_FileName='link'");
        return resSucc();
    }

    private function zblogObjectToHtml($list)
    {
        $html = '';
        foreach ($list as $k => $v) {
            $html .= $this->zblogObjectToHtml_child($v);
        }
        return $html;
    }

    private function zblogObjectToHtml_child($info)
    {
        $html = '<li class="link-item"><a href="'.$info['href'].'" target="'.$info['target'].'" title="'.$info['title'].'">'.($info['ico']?'<i class="'.$info['ico'].'"></i>':'').$info['text'].'</a>';
        if ($info['subs']) {
            $html .= '<ul>';
            $html .= $this->zblogObjectToHtml_child($info['subs']);
            $html .= '</ul>';
        }
        $html .= "</li>";
        return $html;
    }

    private function setFriendLinkTable()
    {
        $this->table = $this->config['friendLink']['table'];
        if (!$this->table) {
            return res(1002, "友情链接表解析错误");
        }

        switch ($this->config['system_type']) {
            case 'wordpress' :
                $this->initWordpressField();
                break;
            case 'dedecms' :
                $this->initDedeField();
                break;
            case 'empire':
                $this->initEmpireField();
                break;
            case 'zblog':
                $this->initZblogField();
                break;
            case 'phpcms':
                $this->initPhpcmsField();
                break;
            case 'maccms':
                $this->initMaccmsField();
                break;
            case 'eyoucms':
                $this->initEyoucmsField();
                break;
            default:
                return res(1002, '配置错误');
        }
    }

    private function friendLinkCategoryAdd($id){
        $insertData = array();
        $insertData[$this->fcrObjIdField] = $id;
        $insertData[$this->fcrCategoryField] = $this->config['friendLinkCategoryRelation']['categoryField']['value'];
        $this->mysql->insert($insertData, $this->fcrTable, true);
    }

    private function friendLinkCategoryDel($id){
        $delWhere = sprintf('%s=%d AND %s=%d',$this->fcrObjIdField,$id,$this->fcrCategoryField,$this->config['friendLinkCategoryRelation']['categoryField']['value']);
        $this->mysql->delete($this->fcrTable, $delWhere);
    }

    private function initField(){
        if(isset($this->config['friendLink']['titleField']))                $this->titleField = $this->config['friendLink']['titleField'];
        if(isset($this->config['friendLink']['addTimeField']))              $this->addTimeField = $this->config['friendLink']['addTimeField'];
        if(isset($this->config['friendLink']['statusField']['field']))      $this->statusField = $this->config['friendLink']['statusField']['field'];
        if(isset($this->config['friendLink']['statusField']['showValue']))  $this->statusShowValue = $this->config['friendLink']['statusField']['showValue'];
        if(isset($this->config['friendLink']['urlField']))                  $this->urlField = $this->config['friendLink']['urlField'];
        if(isset($this->config['friendLink']['idField']))                   $this->idField = $this->config['friendLink']['idField'];
        if(isset($this->config['friendLink']['typeField']['field']))        $this->typeField = $this->config['friendLink']['typeField']['field'];
        if(isset($this->config['friendLink']['siteField']['field']))        $this->siteField = $this->config['friendLink']['siteField']['field'];
        if(isset($this->config['friendLink']['table']))                     $this->table = $this->config['friendLink']['table'];
        if(isset($this->config['friendLink']['emailField']))                $this->emailField = $this->config['friendLink']['emailField'];
        if(isset($this->config['friendLink']['groupField']['field']))       $this->groupField = $this->config['friendLink']['groupField']['field'];
        if(isset($this->config['friendLinkCategoryRelation']['table']))    $this->fcrTable = $this->config['friendLinkCategoryRelation']['table'];
        if(isset($this->config['friendLinkCategoryRelation']['objIdField']['field']))    $this->fcrObjIdField = $this->config['friendLinkCategoryRelation']['objIdField']['field'];
        if(isset($this->config['friendLinkCategoryRelation']['categoryField']['field']))    $this->fcrCategoryField = $this->config['friendLinkCategoryRelation']['categoryField']['field'];
        if(isset($this->config['friendLinkCategoryRelation']['status']))    $this->fcrStatus = $this->config['friendLinkCategoryRelation']['status'];
    }
    private function initDedeField()
    {
        self::initField();
        $this->isDede = true;
    }
    private function initWordpressField()
    {
        self::initField();
        $this->isWordpress = true;
    }
    private function initEmpireField()
    {
        self::initField();
        $this->isEmpire = true;
    }
    private function initZblogField()
    {
        self::initField();
        $this->isZblog = true;
    }
    private function initPhpcmsField()
    {
        self::initField();
        $this->isPhpcms = true;

    }
    private function initMaccmsField(){
        self::initField();
        $this->isMaccms = true;
        $this->isTpNoPublic = true;
    }
    private function initEyoucmsField()
    {
        self::initField();
        $this->isTpNoPublic = true;
        $this->isEyoucms = true;
    }

    public function handle()
    {
        $funcName = $_POST['a'] . "Action";
        if (method_exists($this, $funcName)) {
            $this->{$funcName}();
        } else {
            return res(-1, '错误的入口');
        }
    }

    function __destruct()
    {
        //先清除缓存
        $this->clearCache();
    }

    private function clearCache()
    {
        if ($this->isDede) {
            $this->clearCacheDedecms();
        } else if ($this->isEmpire) {
            $this->clearCacheEmpire();
        }else if( $this->isTpNoPublic ){
            $this->clearCacheTpNoPublic();
        }
    }

    private function clearCacheDedecms()
    {
        @unlink(ROOT_PATH . 'index.html');
    }

    private function clearCacheEmpire()
    {
        define('EmpireCMSAdmin', '1');
        define('EmpireCMSConfig', '1');
        define("InEmpireCMS", "1");
        define('EmpireCMSNFPage', '1');
        global $empire, $ecms_config, $dbtbpre, $public_r, $link, $class_r, $class_zr, $fun_r, $navinfor, $etable_r, $eyh_r, $navclassid, $level_r, $emod_r;
        require(ROOT_PATH . 'e' . DS . 'class' . DS . 'connect.php');
        require(ROOT_PATH . 'e' . DS . 'class' . DS . 'db_sql.php');
        require(ROOT_PATH . 'e' . DS . 'class' . DS . 'functions.php');
        require(ROOT_PATH . 'e' . DS . 'class' . DS . 't_functions.php');
        require(ROOT_PATH . 'e' . DS . 'data' . DS . 'language' . DS . $ecms_config['sets']['elang'] . DS . 'pub' . DS . 'fun.php');
        require(ROOT_PATH . 'e' . DS . 'data' . DS . 'dbcache' . DS . 'class.php');
        require(ROOT_PATH . 'e' . DS . 'data' . DS . 'dbcache' . DS . 'MemberLevel.php');
        require(ROOT_PATH . 'e' . DS . 'class' . DS . 'chtmlfun.php');

        $link = db_connect();
        $empire = new mysqlquery();
        ReIndex();
    }

    private function clearCacheTpNoPublic()
    {
        deldir(ROOT_PATH.'data'.DS.'runtime'.DS.'cache'.DS);
        deldir(ROOT_PATH.'data'.DS.'runtime'.DS.'html'.DS);
    }
}


function res($code, $msg = null, $data = null)
{
    echo json_encode(array(
        "code" => $code,
        "msg" => $msg,
        "data" => $data
    ));
    die();
}

function resSucc($msg = '', $data = null, $code = 0)
{
    return res($code, $msg, $data);
}

function resErr($msg = '', $code = -1)
{
    return res($code, $msg);
}


/**
 * 数据库操作类
 */
class pdoMysql
{
    private $config = null;
    /** @var PDO */
    public $link = null;
    /** @var PDOStatement|int */
    public $lastqueryid = null;
    public $querycount = 0;
    public $max_cache_time = 300; // 最大的缓存时间，以秒为单位
    public $cache_data_dir = 'query_caches';

    public function __construct($config)
    {
        if (!$config['port']) {
            $config['port'] = 3306;//默认端口
        }

        $this->config = $config;
        $this->config['dsn'] = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'];
        $this->connect();
    }

    public function connect()
    {
        try {
            $this->link = new PDO($this->config['dsn'], $this->config['user'], $this->config['password'], array(
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
        } catch (Exception $e) {
            res(-1, $e->getMessage());
        }

        //重置sql_mode,防止datetime,group by 出错
        $this->link->exec("set sql_mode=''");
        //强制UTF8
        $this->link->exec("SET NAMES utf8");
        return $this->link;
    }

    private function execute($sql)
    {
        if (!is_object($this->link)) {
            $this->connect();
        }

        $this->lastqueryid = $this->link->exec($sql);
        $this->querycount++;
        return $this->lastqueryid;
    }

    public function query($sql = null)
    {
        if (!is_object($this->link)) {
            $this->connect();
        }

        $this->lastqueryid = $this->link->query($sql) or $this->halt("", $sql);
        $this->querycount++;
        return $this->lastqueryid;
    }

    public function select($data, $table, $where = '', $limit = '', $order = '', $group = '', $key = '')
    {
        $where = $where == '' ? '' : ' WHERE ' . $where;
        $order = $order == '' ? '' : ' ORDER BY ' . $order;
        $group = $group == '' ? '' : ' GROUP BY ' . $group;
        $limit = $limit == '' ? '' : ' LIMIT ' . $limit;
        $field = explode(',', $data);
        array_walk($field, array($this, 'add_special_char'));
        $data = implode(',', $field);
        $result = array();

        $sql = 'SELECT ' . $data . ' FROM `' . $this->config['database'] . '`.`' . $table . '`' . $where . $group . $order . $limit;

        $this->query($sql);
        if (!is_object($this->lastqueryid)) {
            return $this->lastqueryid;
        }

        $datalist = $this->lastqueryid->fetchAll();
        if ($key) {
            $datalist_new = array();
            foreach ($datalist as $i => $item) {
                $datalist_new[$item[$key]] = $item;
            }
            $datalist = $datalist_new;
            unset($datalist_new);
        }

        $this->free_result();

        return $datalist;
    }

    public function get_one($data, $table, $where = '', $order = '', $group = '')
    {
        $where = $where == '' ? '' : ' WHERE ' . $where;
        $order = $order == '' ? '' : ' ORDER BY ' . $order;
        $group = $group == '' ? '' : ' GROUP BY ' . $group;
        $limit = ' LIMIT 1';
        $field = explode(',', $data);
        array_walk($field, array($this, 'add_special_char'));
        $data = implode(',', $field);

        $sql = 'SELECT ' . $data . ' FROM `' . $this->config['database'] . '`.`' . $table . '`' . $where . $group . $order . $limit;

        $this->query($sql);
        $res = $this->lastqueryid->fetch();
        $this->free_result();

        return $res;
    }

    public function get_onecol($data, $table, $where = '', $order = '', $group = '')
    {
        $where = $where == '' ? '' : ' WHERE ' . $where;
        $order = $order == '' ? '' : ' ORDER BY ' . $order;
        $group = $group == '' ? '' : ' GROUP BY ' . $group;
        $limit = ' LIMIT 1';
        $field = explode(',', $data);
        array_walk($field, array($this, 'add_special_char'));
        $data = implode(',', $field);
        $fieldname = str_replace('`', '', $data);
        $sql = 'SELECT ' . $data . ' FROM `' . $this->config['database'] . '`.`' . $table . '`' . $where . $group . $order . $limit;

        $this->query($sql);
        $res = $this->lastqueryid->fetch();
        $this->free_result();

        return $res[$fieldname] ? $res[$fieldname] : false;
    }

    public function insert($data, $table, $return_insert_id = false, $replace = false)
    {
        if (!is_array($data) || $table == '' || count($data) == 0) {
            return false;
        }

        $fielddata = array_keys($data);
        $valuedata = array_values($data);
        array_walk($fielddata, array($this, 'add_special_char'));
        array_walk($valuedata, array($this, 'escape_string'));

        $field = implode(',', $fielddata);
        $value = implode(',', $valuedata);

        $cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
        $sql = $cmd . ' `' . $this->config['database'] . '`.`' . $table . '`(' . $field . ') VALUES (' . $value . ')';
        $return = $this->execute($sql);

        return $return_insert_id ? $this->insert_id() : $return;
    }

    /**
     * update 不支持order by
     * @param $data
     * @param $table
     * @param string $where
     * @param string $order
     * @param string $limit
     * @return bool|int|PDOStatement
     */
    public function update($data, $table, $where = '', $order = "", $limit = "")
    {
        if ($table == '' or $where == '') {
            return false;
        }

        $where = ' WHERE ' . $where;
        if (is_string($data) && $data != '') {
            $field = $data;
        } elseif (is_array($data) && count($data) > 0) {
            $fields = array();
            foreach ($data as $k => $v) {
                switch (substr($v, 0, 2)) {
                    case '+=':
                        $v = substr($v, 2);
                        if (is_numeric($v)) {
                            $fields[] = $this->add_special_char($k) . '=' . $this->add_special_char($k) . '+' . $this->escape_string($v, '', false);
                        }
                        break;
                    case '-=':
                        $v = substr($v, 2);
                        if (is_numeric($v)) {
                            $fields[] = $this->add_special_char($k) . '=' . $this->add_special_char($k) . '-' . $this->escape_string($v, '', false);
                        }
                        break;
                    default:
                        //对自增自减字段的特殊处理
                        if (preg_match('/^`[a-z_0-9]+`\s*[\+\-]\s*[0-9]+$/', $v)) {
                            $fields[] = $this->add_special_char($k) . '=' . $v;
                        } else {
                            $fields[] = $this->add_special_char($k) . '=' . $this->escape_string($v);
                        }
                }
            }
            $field = implode(',', $fields);
        } else {
            return false;
        }

        $order = !empty($order) ? " ORDER BY " . $order : "";
        $limit = !empty($limit) ? " LIMIT " . $limit : "";

        $sql = 'UPDATE `' . $this->config['database'] . '`.`' . $table . '` SET ' . $field . $where . $order . $limit;
        return $this->execute($sql);
    }

    public function delete($table, $where)
    {
        if ($table == '' || $where == '') {
            return false;
        }

        $where = ' WHERE ' . $where;
        $sql = 'DELETE FROM `' . $this->config['database'] . '`.`' . $table . '`' . $where;
        return $this->execute($sql);
    }

    public function fetchAll($res = null)
    {
        $type = PDO::FETCH_ASSOC;
        if ($res) {
            $res_query = $res;
        } else {
            $res_query = $this->lastqueryid;
        }

        return $res_query->fetchAll($type);
    }

    public function affected_rows()
    {
        return is_numeric($this->lastqueryid) ? $this->lastqueryid : 0;
    }

    public function get_primary($table)
    {
        $this->query("SHOW COLUMNS FROM $table");
        while ($r = $this->lastqueryid->fetch()) {
            if ($r['Key'] == 'PRI') break;
        }

        return $r['Field'];
    }

    public function get_fields($table)
    {
        $fields = array();
        $this->query("SHOW COLUMNS FROM $table");
        while ($r = $this->lastqueryid->fetch()) {
            $fields[$r['Field']] = $r['Type'];
        }

        return $fields;
    }

    public function check_fields($table, $array)
    {
        $fields = $this->get_fields($table);
        $nofields = array();
        foreach ($array as $v) {
            if (!array_key_exists($v, $fields)) {
                $nofields[] = $v;
            }
        }

        return $nofields;
    }

    public function table_exists($table)
    {
        $tables = $this->list_tables();
        return in_array($table, $tables) ? 1 : 0;
    }

    public function list_tables()
    {
        $tables = array();
        $this->query("SHOW TABLES");
        while ($r = $this->lastqueryid->fetch()) {
            $tables[] = $r['Tables_in_' . $this->config['database']];
        }

        return $tables;
    }

    public function field_exists($table, $field)
    {
        $fields = $this->get_fields($table);
        return array_key_exists($field, $fields);
    }

    public function num_rows($sql)
    {
        $this->query($sql);
        return $this->lastqueryid->rowCount();
    }

    public function num_fields($sql)
    {
        $this->query($sql);
        return $this->lastqueryid->columnCount();
    }

    public function result($sql, $row)
    {
        $this->query($sql);
        return $this->lastqueryid->fetchColumn($row);
    }

    public function error()
    {
        return $this->link->errorInfo();
    }

    public function errno()
    {
        return intval($this->link->errorCode());
    }

    public function insert_id()
    {
        return $this->link->lastInsertId();
    }

    public function free_result()
    {
        if (is_object($this->lastqueryid)) {
            $this->lastqueryid = null;
        }
    }

    public function close()
    {
        if (is_object($this->link)) {
            unset($this->link);
        }
    }

    /**
     * 对字段两边加反引号，以保证数据库安全
     * @param $value 数组值
     * @return mixed|string|数组值
     */
    public function add_special_char(&$value)
    {
        if ('*' == $value || false !== strpos($value, '(') || false !== strpos($value, '.') || false !== strpos($value, '`') || false !== strpos(strtolower($value), 'as')) {
            //不处理包含* 或者 使用了sql方法, 使用了别名。
        } else {
            $value = '`' . trim($value) . '`';
        }

        if (preg_match("/\b(select|insert|update|delete)\b/i", $value)) {
            $value = preg_replace("/\b(select|insert|update|delete)\b/i", '', $value);
        }

        return $value;
    }

    /**
     * 对字段值两边加引号，以保证数据库安全
     * @param              $value 数组值
     * @param string|数组key $key 数组key
     * @param int $quotation
     * @return string|数组值
     */
    public function escape_string(&$value, $key = '', $quotation = 1)
    {
        if ($quotation) {
            $q = '\'';
        } else {
            $q = '';
        }

        $value = $q . $value . $q;
        return $value;
    }

    public function halt($message = '', $sql = '')
    {
        $this->errormsg = "<b>MySQL Query : </b> $sql <br /><b> MySQL Error : </b>" . implode("<br>", $this->link->errorInfo()) . " <br /> <b>MySQL Errno : </b>" . $this->link->errorCode() . " <br /><b> Message : </b> $message <br />";
    }

    public function errorInfo()
    {
        return $this->link->errorInfo();
    }
}


//打印函数
function log_debug_data($data, $filename = 'didi')
{
    file_put_contents(ROOT_PATH . $filename . '.txt', var_export($data, true) . "\r\n", FILE_APPEND);
}


/**
 * array_filter_recursive 清除多维数组里面的空值
 * @param array $array
 * @return array
 * @author   liuml
 * @DateTime 2018/12/3  11:27
 */
function array_filter_recursive(array &$arr)
{
    foreach ($arr as $k => $v) {
        if (is_array($v) && count($v) > 1) {
            $arr[$k] = array_filter_recursive($v);
        }
        if (is_null($arr[$k]) || $arr[$k] == '') {
            unset($arr[$k]);
        }
    }

    if (count($arr) < 1) {
        return null;
    }

    return $arr;
}


function formatParam($key, $type = 'trim', $default = NULL)
{

    if (isset($_POST[$key]) === false) {
        switch ($type) {
            case 'array':
            case 'json':
                return array();
                break;
            case 'idStr':
            case 'intval':
                return ($default === NULL) ? 0 : (int)$default;
                break;

            case 'floatval':
                return ($default === NULL) ? 0.0 : (float)$default;
                break;

            case 'trim':
                /* 无默认值的情况 */
                if ($default === NULL) {
                    return '';
                } /* 仅有默认值的情况 */
                elseif (strpos($default, '|') === false) {
                    return (string)$default;
                } /* 枚举的情况 */
                else {
                    $_value = explode('|', $default);

                    return (string)$_value[0];
                }
                break;
        }
    } else {
        $value = diyhtmlspecialchars($_POST[$key], ENT_QUOTES);
        if (is_array($value) && $type <> 'array') {
            return false;
        }

        switch ($type) {
            case 'array':
                $value = (array)$value;
                return $value;
                break;
            case 'json':
                $value = json_decode(dstripslashes($value), true);
                return $value;
                break;

            case 'intval':
                return (int)$value;
                break;

            case 'floatval':
                return (float)$value;
                break;

            case 'trim':
                $value = trim($value);
                $value = (string)$value;
                if ($value == 'null') {
                    $value = null;
                }

                /* 枚举的情况 */
                if ($default !== NULL && strpos($default, '|') !== false) {
                    $_value = explode('|', $default);
                    if ($value === '' || in_array($value, $_value) === false) {
                        $value = (string)$_value[0];
                    }
                } elseif (empty($value) && $default !== NULL) {
                    $value = $default;
                }


                return $value;

                break;
            case 'idStr':
                $value = explode(",", $value);
                foreach ($value as &$v) {
                    $v = intval($v);
                }
                return implode(",", $value);
                break;
        }
    }
}

function getIp()
{
    $ip = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) AND preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
        foreach ($matches[0] AS $xip) {
            if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                $ip = $xip;
                break;
            }
        }
    }
    if ($_SERVER['HTTP_REQUEST_IP']) {
        $ip = $_SERVER['HTTP_REQUEST_IP'];
    }

    return $ip;
}

//转义特殊字符 数组字符串都可以
function diyhtmlspecialchars($string, $flags = null)
{
    if (is_array($string)) {
        foreach ($string as $key => $val) {
            $string[$key] = diyhtmlspecialchars($val, $flags);
        }
    } else {
        $string = htmlspecialchars($string, $flags, 'UTF-8');
    }

    return $string;
}

function curl_get_contents($url, $referer = '', $returnType = 0, $timeout = 5)
{

    $url = strtolower($url);

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)');

    curl_setopt($curl, CURLOPT_URL, $url);
    if ($referer) {
        curl_setopt($curl, CURLOPT_REFERER, $referer);
    }
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);                   //https请求 不验证证书
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);                 //https请求 不验证hosts
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_HEADER, TRUE);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);  //避免302无法跳转
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
    curl_setopt($curl, CURLOPT_COOKIEJAR, 'cookie.txt');    //存cookie的文件名，
    curl_setopt($curl, CURLOPT_COOKIEFILE, 'cookie.txt');  //发送
    $result = curl_exec($curl);
    $ret = $result;
    list($header, $data) = explode("\r\n\r\n", $result, 2);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    $last_url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    if ($http_code == 301 || $http_code == 302 || $http_code == 307) {
        $matches = array();
        preg_match('/Location:(.*?)\n/', $header, $matches);
        $url = @parse_url(trim(array_pop($matches)));
        if (!$url) {
            return $data;
        }
        $new_url = $url['scheme'] . '://' . $url['host'] . $url['path']
            . (isset($url['query']) ? '?' . $url['query'] : '');
        $new_url = stripslashes($new_url);
        return curl_get_contents($new_url, $last_url);
    } else {

        list($header, $data) = explode("\r\n\r\n", $ret, 2);
        if ($returnType == 1) {
            return array($data, $header, $http_code);
        } else {
            return $data;
        }
    }
}


function deldir($path){
    //如果是目录则继续
    if(is_dir($path)){
        //扫描一个文件夹内的所有文件夹和文件并返回数组
        $p = scandir($path);
        foreach($p as $val){
            //排除目录中的.和..
            if(!in_array($val,array(".",".."))){
                //如果是目录则递归子目录，继续操作
                if(is_dir($path.$val)){
                    //子目录中操作删除文件夹和文件
                    deldir($path.$val.DS);
                    //目录清空后删除空文件夹
                    @rmdir($path.$val.DS);
                }else{
                    //如果是文件直接删除
                    unlink($path.$val);
                }
            }
        }
    }
}


if (!function_exists('array_column')) {
    //兼容低版本
    function array_column($rows, $column_key, $index_key = null)
    {
        if (!$rows) return array();
        $data = array();
        if (empty($index_key)) {
            foreach ($rows as $row) {
                $data[] = $row[$column_key];
            }
        } else {
            foreach ($rows as $row) {
                $data[$row[$index_key]] = $row;
            }
        }
        return $data;
    }
}

//错误提示：
$errorCodes = array(
    0 => '正常',
    -1 => '普通错误',
    1001 => '访问受限',
    1002 => '没有配置或配置错误',
    1003 => '空间不支持',
    1004 => '读取数据库配置失败',
);