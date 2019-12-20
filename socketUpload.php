<?php
//====================================================
// FileName:socketUpload.php
// Summary: 实现HTTP协议的文件上传
// Author: ice_berg16(寻梦的稻草人)
// copyright(c)2005 ice_berg16@163.com
// http://www.icebergweb.com
//
// LastModifed:2012-2-23 By yurong
//different：允许上传远程图片，不返回返回值中的header信息
//
// ------example------
// $pf = new socketUpload( "http://host.com/test.php");
// $pf->setFile( "uploadFile", "d:/images/ice.gif" );
// $pf->sendRequest();
// echo $pf->getResponse();
//====================================================

class socketUpload
{
    var $url; //要发送文件的URL
    var $formData; //发送的表单数据
    var $fileData; //文件数据
    var $boundary; //数据分隔标识
    var $response; //保存服务器返回的信息
    var $username; //需要身份验证时的用户名
    var $pwd; //需要身份验证时的密码
    var $port; //端口号

    var $debug = false; //是否调试

    /* 函数: socketUpload
    ** 功能: Constructor
    ** 参数: $url String 要发送文件的URL
    */
    function socketUpload( $url="", $port="80")
    {
        $this->url = $url;
        $this->port = $port;
        $this->boundary = $this->createBoundary();
    }

    /* 函数: sendRequest
    ** 功能: 发送请求并保存结果
    */
    function sendRequest()
    {
        $urlArray = parse_url( $this->url );
        $fp = fsockopen( $urlArray['host'], $this->port );

        $requestData = $this->buildRequest();//$requestData=str_replace("\r\n",'<br>',$requestData);echo $requestData;exit;

        //*
        fwrite( $fp, $requestData );

        $content = "";
        while( !feof( $fp ) )
        {
            $content .= fread( $fp, 4096 );
        }
        fclose( $fp );
        //*/
        $this->response = end(explode("\r\n",$content));

        if( $this->debug )
        {
            echo "---------HTTP-REQUEST-------";
            echo "$requestData";
            echo "---------HTTP-RESPONSE------";
            echo "$content";
        }
        //*/
    }

    /* 函数: getResponse()
    ** 功能: 返回服务器端的信息
    */
    function getResponse()
    {
        return $this->response;
    }

    /* 函数: setForm( $formData )
    ** 功能: 设置表单的字段值
    ** 参数: $formData Array 字段名和值的数组
    */
    function setForm( $formData )
    {
        $this->formData = $this->buildFormData( $formData );
    }

    /* 函数: setFile( $name, $filePath )
    ** 功能: 设置要发送的文件
    ** 参数: $name 文件名，即file域的name
    ** 参数: $filePath 要发送的文件路径
    */
    function setFile( $name, $filePath )
    {
        $this->fileData = $this->buildFileData( $name, $filePath );
    }

    /* 函数: setAuthor( $user, $pwd )
    ** 功能: 设置身份验证时需要的用户名和密码
    ** 参数: $user 用户名
    ** 参数: $pwd 密码
    */
    function setAuthor( $user, $pwd )
    {
        $this->username = $user;
        $this->pwd = $pwd;
    }

    /* 函数: buildRequest()
    ** 功能: 建立请求
    */
    function buildRequest()
    {
        $urlArray = parse_url( $this->url );
        $request = array();

        $request[] = "POST {$urlArray['path']} HTTP/1.0";
        $request[] = "Host: {$urlArray['host']}";
        $request[] = "User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)";
        $request[] = "Accept: */*";
        $request[] = "Accept-Language: zh-cn";
        $request[] = "Connection: Keep-Alive";
        $request[] = "Cache-Control: no-cache";

        //需要身份验证
        if ( !empty( $this->username ) && !empty( $this->pwd ) )
        {
            $request[] = 'Authorization: BASIC ' . base64_encode( $this->username.':'.$this->pwd );
        }

        $request[] = "Content-Type: multipart/form-data; boundary={$this->boundary}";
        $request[] = "Content-Length: " . $this->getDataLength() . "\r\n";

        $requestString = join( "\r\n", $request ) . "\r\n" . $this->formData . "\r\n" . $this->fileData;


        if( $this->debug )
        {
            echo "----------- REQUEST_INFOMATION -----------";
            echo "" . $requestString . "";
        }
        return $requestString;
    }

    /* 函数: getDataLength()
    ** 功能: 返回要发送数据的长度
    */
    function getDataLength()
    {
        return strlen( $this->formData ) + strlen("\r\n") + strlen( $this->fileData );
    }

    /* 函数: buildFormData()
    ** 功能: 创建发送的数据格式
    */
    function buildFormData( $formData )
    {
        $postData = array();
        foreach( $formData as $k => $v )
        {
            $row   = array();
            $row[] = "--{$this->boundary}";
            $row[] = "Content-Disposition: form-data; name=\"$k\"\r\n";
            $row[] = "$v";

            $postData[] = join( "\r\n", $row );
        }

        return join( "\r\n", $postData );
    }

    /* 函数: buildFileData( $name, $filePath )
    ** 功能: 创建发送的文件格式
    */
    function buildFileData( $name, $filePath )
    {
        //读取文件信息
        $fname = basename( $filePath );
        if(strtolower(substr($filePath,0,7))!='http://'){        
        $fp = fopen ( $filePath, "r" );
        $data = fread ( $fp, filesize( $filePath ) );
        fclose ($fp);
        }else{
            $data = file_get_contents($filePath);
        }

        $postData = array();
        $postData[] = "--{$this->boundary}";
        $postData[] = "Content-Disposition: form-data; name=\"$name\"; filename=\"$fname\"\r\n";
        //$postData[] = "Content-Type: text/plain\r\n";设置上传文件的类型,可以写代码根据不同的文件类型写不同的值
        $postData[] = $data;
        $postData[] = "--{$this->boundary}--";

        return join( "\r\n", $postData );
    }

    /* 函数: createBoundary()
    ** 功能: 创建数据分隔标识
    */
    function createBoundary()
    {
        return "---------------------------" . substr(md5(time()), -12 );
    }
}
?>
