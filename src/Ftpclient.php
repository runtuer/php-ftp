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
// | Ftpclient.php  Version 2018/12/26
// +----------------------------------------------------------------------
namespace Ftp;

class Ftpclient
{
    public $ftp_extension = true; //FTP扩展
    public $ftpclient     = null; //FTP链接器
    public $conn          = null; //连接器对象
    public $mode          = FTP_BINARY; //连接模式

    /**
     * @Mark:构造器
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function __construct()
    {
        $this->ftp_extension = extension_loaded('ftp') ? true : false;
        if (!$this->ftp_extension) {
            $this->ftpclient = new Ftpstream();
            $this->mode      = 'MODE_BINARY';
        } else {
            $this->ftpclient = $this;
        }
    }

    /**
     * @Mark:连接FTP
     * @param $params
     * @return bool
     * @throws \Exception
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function connect($params)
    {
        $params['port']    = $params['port'] ?? 21;
        $params['timeout'] = $params['timeout'] ?? 30;
        if ($this->ftp_extension) {
            $connect    = ftp_connect($params['host'], $params['port'], $params['timeout']);
            $this->conn = $connect;
        } else {
            $connect = $this->ftpclient->connect($params['host'], $params['port'], $params['timeout']);
        }

        if (!$connect) {
            throw new \Exception('Ftp connect fail, check ftp address or port !');
        }
        if (!$this->_login($params)) {
            return false;
        }

        $this->changeDirectory($params['dir']);
        return true;
    }

    /**
     * @Mark:登录
     * @param $params
     * @return bool
     * @throws \Exception
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    private function _login($params)
    {
        if (!$params['name'] || !$params['pass']) {
            throw new \Exception('Ftp login fail, ftp username or password is empty !');
        }

        if ($this->ftp_extension) {
            $flag = @ftp_login($this->conn, $params['username'], $params['password']);
        } else {
            $flag = $this->ftpclient->login($params['username'], $params['password']);
        }

        if (!$flag) {
            throw new \Exception('Ftp login fail, check ftp username or password !');
        }
        return true;
    }

    /**
     * @Mark:檢查FTP配置
     * @param $params
     * @return bool
     * @throws \Exception
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function check($params)
    {
        $params['timeout'] = 5;//5秒連接失敗則檢查不通過
        if (!$this->connect($params)) {
            trigger_error('Ftp check error!', E_USER_ERROR);
            return false;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'ftp_test');
        file_put_contents($tmpFile, 'This is test file');
        $params['remote'] = 'ftp_test';
        $params['local']  = $tmpFile;
        $params['resume'] = 0;
        //檢查上傳
        if (!$this->push($params)) {
            trigger_error('Ftp check push error!', E_USER_ERROR);
            return false;
        }

        //檢查下載檔案
        if (!$this->pull($params)) {
            trigger_error('Ftp check download error!', E_USER_ERROR);
            return false;
        }
        return true;
    }

    /**
     * @Mark:更改目录
     * @param null $dir
     * @return bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function changeDirectory($dir = null)
    {
        if ($this->ftp_extension) {
            @ftp_chdir($this->conn, $dir); //目錄錯誤會返回警告，屏蔽
        } else {
            $this->ftpclient->changeDirectory($dir);
        }
        return true;
    }

    /**
     * @Mark:上传
     * @param $params
     * @return bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function push($params)
    {
        if ($this->ftp_extension) {
            try {
                $ret = ftp_nb_put($this->conn, $params['remote'], $params['local'], $this->mode, $params['resume']);
                while ($ret == FTP_MOREDATA) {
                    $ret = ftp_nb_continue($this->conn);
                }
            } catch (\Exception $e) {
                $ret = $e->getMessage();
            }
        } else {
            $ret = $this->ftpclient->upload($params['local'], $params['remote'], $this->mode, $params['resume']);
        }

        if ($ret == FTP_FAILED || !$ret) {
            throw new \Exception('Ftp upload fail error contents : ' . var_export($ret, 1));
        }

        return true;
    }

    /**
     * @Mark:下载
     * @param $params
     * @return bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function pull($params)
    {
        if ($this->ftp_extension) {
            $ret = ftp_nb_get($this->conn, $params['local'], $params['remote'], $this->mode, $params['resume']);
            while ($ret == FTP_MOREDATA) {
                $ret = ftp_nb_continue($this->conn);
            }
        } else {
            $ret = $this->ftpclient->download($params['remote'], $params['local'], $this->mode, $params['resume']);
        }

        if ($ret == FTP_FAILED || $ret === false) {
            throw new \Exception('Ftp download fail !');
        }

        return true;
    }

    /**
     * @Mark:返回文件大小
     * @param $filename
     * @return int
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function size($filename)
    {
        if ($this->ftp_extension) {
            return ftp_size($this->conn, $filename);
        } else {
            return $this->ftpclient->getFileSize($filename);
        }
    }

    /**
     * @Mark:删除文件
     * @param $filename
     * @return bool
     * @Author: theseaer <theseaer@qq.com>
     * @Version 2018/12/26
     */
    public function delete($filename)
    {
        if ($this->ftp_extension) {
            $size = $this->size($filename);
            if (!$size || $size == -1)
                return true;
            return ftp_delete($this->conn, $filename);
        } else {
            return $this->ftpclient->removeFile($filename);
        }
    }

}