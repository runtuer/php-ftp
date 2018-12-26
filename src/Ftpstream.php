<?php
// +----------------------------------------------------------------------
// | ETshop 多用户进口跨境商城
// +----------------------------------------------------------------------
// | 版权所有 2017-2027 深圳市润土信息技术有限公司，并保留所有权利。
// +----------------------------------------------------------------------
// | 未经本公司授权您只能在不用于商业目的的前提下对程序代码进行修改和使用，
// +---------------------------------------------------------------------
// | 不允许对程序代码以任何形式任何目的的再发布
// +----------------------------------------------------------------------
// | 官网地址:http://www.runtuer.com
// +----------------------------------------------------------------------
// | Author: theseaer <theseaer@qq.com>
// +----------------------------------------------------------------------
// | Ftpstream.php  Version 2018/12/26
// +----------------------------------------------------------------------
namespace Ftp;

class Ftpstream
{
    protected $timeout      = 90;
    protected $transferMode = null;//暫時只支援被動模式
    protected $system       = null;
    protected $features     = null;
    protected $connection   = null;

    /**
     * 連接到FTP服務器
     *
     * @params string $host FTP服務器地址
     * @params int    $port FTP服務器端口
     * @params int    $timeout FTP服務器端口
     *
     * @return bool
     */
    public function connect($host, $port, $timeout = 90)
    {
        $this->connection = @fsockopen($host, $port, $errorCode, $errorMessage, $timeout);
        if (is_resource($this->connection) === false) {
            return false;
        }
        stream_set_blocking($this->connection, true);
        stream_set_timeout($this->connection, $this->timeout);
        $response = $this->_getResponse();
        if ($response['code'] !== 220) {
            return false;
        }
        return true;
    }

    /**
     *  登入到FTP服務器
     *
     * @param string $username 會員名
     * @param string $password 密碼
     * @return bool
     */
    public function login($username, $password)
    {
        $response = $this->_request(sprintf('USER %s', $username));
        if ($response['code'] !== 331) {
            return false;
        }
        $response = $this->_request(sprintf('PASS %s', $password));
        if ($response['code'] !== 230) {
            return false;
        }
        return true;
    }

    /**
     * @Mark:返回系统名
     * @return string|bool If error returns FALSE
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function getSystem()
    {
        /*if ($this->system === null) {
            $this->system = $this->_getSystem();
        }*/
        return $this->system === null ? $this->_getSystem() : $this->system;
    }

    /**
     * @Mark:返回功能
     * @return array|bool|null
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function getFeatures()
    {
        /*if ($this->features === null) {
            $this->features = $this->_getFeatures();
        }

        return $this->features;*/
        return $this->features === null ? $this->_getFeatures() : $this->system;
    }

    /**
     * @Mark:关闭连接
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function disconnect()
    {
        $this->_request('QUIT');
        $this->connection = null;
    }

    /**
     * @Mark:取得目前路徑地址
     * @return bool|string
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function getCurrentDirectory()
    {
        $response = $this->_request('PWD');

        if ($response['code'] !== 257) {
            return false;
        }

        $from             = strpos($response['message'], '"') + 1;
        $to               = strrpos($response['message'], '"') - $from;
        $currentDirectory = substr($response['message'], $from, $to);
        return $currentDirectory;
    }

    /**
     * @Mark:改变目录
     * @param $directory
     * @return bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function changeDirectory($directory)
    {
        $response = $this->_request(sprintf('CWD %s', $directory));
        return ($response['code'] === 250);
    }

    /**
     * @Mark:删除目录
     * @param $directory
     * @return bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function removeDirectory($directory)
    {
        $response = $this->_request(sprintf('RMD %s', $directory));
        return ($response['code'] === 250);
    }

    /**
     * @Mark:新建目录
     * @param $directory
     * @return bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version now
     */
    public function createDirectory($directory)
    {
        $response = $this->_request(sprintf('MKD %s', $directory));
        return ($response['code'] === 257);
    }

    /**
     * @Mark:重命名目录或者文件
     * @param $oldName
     * @param $newName
     * @return bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function rename($oldName, $newName)
    {
        $response = $this->_request(sprintf('RNFR %s', $oldName));
        if ($response['code'] !== 350) {
            return false;
        }

        $response = $this->_request(sprintf('RNTO %s', $newName));
        if ($response['code'] !== 250) {
            return false;
        }
        return true;
    }

    /**
     * @Mark:刪除FTP文件
     * @param $filename 文件外
     * @return bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function removeFile($filename)
    {
        $response = $this->_request(sprintf('DELE %s', $filename));
        return ($response['code'] === 250);
    }

    /**
     * 通过FTP设置文件的权限。
     * @param string $filename
     * @param int $mode The new permissions, given as an octal value.
     * @return bool If success return TRUE, fail return FALSE.
     * @throws InvalidArgumentException
     */
    public function setPermission($filename, $mode)
    {
        if (is_integer($mode) === false or $mode < 0 or 0777 < $mode) {
            throw new \InvalidArgumentException(sprintf('Invalid permission "%o" was given.', $mode));
        }

        $response = $this->_request(sprintf('SITE CHMOD %o %s', $mode, $filename));
        return ($response['code'] === 200);
    }

    /**
     * 返回指定目录文件.
     * @param string $directory
     * @return array|bool If error, returns FALSE.
     */
    public function getList($directory)
    {
        $dataConnection = $this->_openPassiveDataConnection();

        if ($dataConnection === false) {
            return false;
        }

        $response = $this->_request(sprintf('NLST %s', $directory));
        if ($response['code'] !== 150) {
            return false;
        }

        $list = '';
        while (feof($dataConnection) === false) {
            $list .= fread($dataConnection, 1024);
        }

        $list = trim($list);
        $list = preg_split("/[\n\r]+/", $list);

        return $list;
    }

    /**
     * 获取文件大小.
     * @abstract
     * @param string $filename
     * @return int|bool If failed to get file size, returns FALSE
     * @note Not all servers support this feature!
     */
    public function getFileSize($filename)
    {
        if ($this->_supports('SIZE') === false) {
            return false;
        }

        $response = $this->_request(sprintf('SIZE %s', $filename));
        if ($response['code'] !== 213) {
            return false;
        }

        if (!preg_match('/^[0-9]{3} (?P<size>[0-9]+)$/', trim($response['message']), $matches)) {
            return false;
        }

        return intval($matches['size']);
    }

    /**
     * 返回给定文件的最后修改时间.
     * @param string $filename
     * @return int|bool Returns the last modified time as a Unix timestamp on success, or FALSE on error.
     * @note Not all servers support this feature!
     */
    public function getModifiedDateTime($filename)
    {
        if ($this->_supports('MDTM') === false) {
            return false;
        }

        $response = $this->_request(sprintf('MDTM %s', $filename));
        if ($response['code'] !== 213) {
            return false;
        }

        if (!preg_match('/^[0-9]{3} (?P<datetime>[0-9]{14})$/', trim($response['message']), $matches)) {
            return false;
        }

        return strtotime($matches['datetime'] . ' UTC');
    }

    /**
     * 从FTP服务器下载文件.
     * @param string $remoteFilename
     * @param string $localFilename
     * @param int $mode self::MODE_ASCII or self::MODE_BINARY
     * @return bool If success return TRUE, fail return FALSE.
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function download($remoteFilename, $localFilename, $mode, $resume)
    {
        $modes = array(
            'MODE_ASCII'  => 'A',
            'MODE_BINARY' => 'I',
        );

        if (array_key_exists($mode, $modes) === false) {
            return false;
        }

        $localFilePointer = fopen($localFilename, 'wb');
        if (is_resource($localFilePointer) === false) {
            return false;
        }

        $typeresponse = $this->_request(sprintf('TYPE %s', $modes[$mode]));
        if ($typeresponse['code'] !== 200) {
            return false;
        }

        $downloaddataConnection = $this->_openPassiveDataConnection();
        if ($downloaddataConnection === false) {
            return false;
        }

        $retrresponse = $this->_request(sprintf('RETR %s', $remoteFilename));
        if ($retrresponse['code'] !== 150) {
            return false;
        }

        while (feof($downloaddataConnection) === false) {
            fwrite($localFilePointer, fread($downloaddataConnection, 10240), 10240);
        }

        return true;
    }

    /**
     * 上传
     * @param string $localFilename 本地檔案路徑
     * @param string $remoteFilename FTP服務器檔案路徑
     * @param string $mode MODE_ASCII or MODE_BINARY 預設使用二進制模式上傳
     * @param int $resume 上傳本地檔案指針位置
     * @return bool If success return true else return false
     */
    public function upload($localFilename, $remoteFilename, $mode, $resume)
    {
        $modes = array(
            'MODE_ASCII'  => 'A',
            'MODE_BINARY' => 'I',
        );

        $localFilePointer = fopen($localFilename, 'rb');
        if (is_resource($localFilePointer) === false) {
            return false;
        }

        $typeresponse = $this->_request(sprintf('TYPE %s', $modes[$mode]));
        if ($typeresponse['code'] !== 200) {
            return false;
        }

        $dataConnection = $this->_openPassiveDataConnection();
        if ($dataConnection === false) {
            return false;
        }

        $apperesponse = $this->_request(sprintf('APPE %s', $remoteFilename));
        if ($apperesponse['code'] !== 150) {
            return false;
        }

        fseek($localFilePointer, $resume);
        while (feof($localFilePointer) === false) {
            fwrite($dataConnection, fread($localFilePointer, 10240), 10240);
        }
        return true;
    }

    /**
     * @Mark:被動模式打開數據連接
     * @return bool|resource
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    protected function _openPassiveDataConnection()
    {
        $response = $this->_request('PASV');

        if ($response['code'] !== 227) {
            return false;
        }

        $serverInfo = $this->_parsePassiveServerInfo($response['message']);

        if ($serverInfo === false) {
            return false;
        }

        $dataConnection = fsockopen($serverInfo['host'], $serverInfo['port'], $errorNumber, $errorString, $this->timeout);

        if (is_resource($dataConnection) === false) {
            return false;
        }
        stream_set_blocking($dataConnection, true);
        stream_set_timeout($dataConnection, $this->timeout);
        return $dataConnection;
    }

    /**
     * @Mark:解析返回內容 返回FTP連接地址和端口
     * @param $message
     * @return array|bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    protected function _parsePassiveServerInfo($message)
    {
        if (!preg_match('/\((?P<host>[0-9,]+),(?P<port1>[0-9]+),(?P<port2>[0-9]+)\)/', $message, $matches)) {
            return false;
        }

        $host = strtr($matches['host'], ',', '.');
        $port = ($matches['port1'] * 256) + $matches['port2']; // low bit * 256 + high bit

        return array(
            'host' => $host,
            'port' => $port,
        );
    }

    /**
     * @Mark:发送请求
     * @param $request
     * @return array
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    protected function _request($request)
    {
        $request = $request . "\r\n";
        fputs($this->connection, $request);
        return $this->_getResponse();
    }

    /**
     * @Mark:格式化返回参数
     * @return array
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    protected function _getResponse()
    {
        $response = array(
            'code'    => 0,
            'message' => '',
        );

        while (true) {
            $line                = fgets($this->connection, 8129);
            $response['message'] .= $line;

            //如果是SSH連接 則跳出
            if (stripos($line, 'SSH') !== false) {
                break;
            }

            if (preg_match('/^[0-9]{3} /', $line)) {
                break;
            }
        }

        $response['code'] = intval(substr(ltrim($response['message']), 0, 3));

        return $response;
    }

    /**
     * @Mark:返回系统名
     * @return bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    protected function _getSystem()
    {
        $response = $this->_request('SYST');

        if ($response['code'] !== 215) {
            return false;
        }

        $tokens = explode(' ', $response['message']);
        return $tokens[1];
    }

    /**
     * @Mark:返回功能列表
     * @return array|bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    protected function _getFeatures()
    {
        $response = $this->_request('FEAT');

        if ($response['code'] !== 211) {
            return false;
        }

        $lines = explode("\n", $response['message']);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines);

        if (count($lines) < 2) {
            return false;
        }

        $lines    = array_slice($lines, 1, count($lines) - 2);
        $features = array();

        foreach ($lines as $line) {
            $tokens             = explode(' ', $line);
            $feature            = $tokens[0];
            $features[$feature] = $line;
        }

        return $features;
    }

    /**
     * 确定是否支持特定命令.
     * @param string $command
     * @return bool
     */
    protected function _supports($command)
    {
        $features = $this->getFeatures();
        return array_key_exists($command, $features);
    }
}