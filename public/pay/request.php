<?php

function request($url){

    if(!empty($_GET)) {
        $url .= (false===strpos($url, '?')?'?':'&').http_build_query($_GET);
    }

    $post     = $_SERVER['REQUEST_METHOD']=='POST'?$_POST:null;
    $parts    = parse_url($url);//分析URL

    $port     = 80;
    $protocol = '';
    $isSsl    = (isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))) || (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'] ));
    //取得请求类型 http|https(ssl)和默认端口
    if(isset($parts['scheme'])) { //如果已经设置则获取设置的
        $scheme = strtolower($parts['scheme']);
        if($scheme == 'https' || $scheme == 'ssl' || $scheme == 'tls') {
            $port      = 443;
            $protocol  = $scheme == 'tls' ? 'tls://' : 'ssl://';
        }
    }elseif($isSsl){ //没有设置获取当前请求
        $port      = 443;
        $protocol  = 'ssl://';
    }

    //取得请求的host
    if(isset($parts['host'])){
        $host = $parts['host'];
    }elseif(false === strpos($_SERVER['HTTP_HOST'], ':')){
        $host = $_SERVER['HTTP_HOST'];
    }else{
        list($host, $port) = explode(':', $_SERVER['HTTP_HOST'], 2);
    }

    //实际的请求端口
    $port  = isset($parts['port']) ? $parts['port'] : $port;
    $_port = ($protocol && $port == 443) || (!$protocol && $port == 80) ? '' : ':'.$port;

    //请求方法 GET或POST
    $method  = null === $post ? 'GET' : 'POST';
    //请求的文件和查询参数
    $path    = isset($parts['path']) ? '/'.ltrim($parts['path'], '/') : '/';
    $path   .= isset($parts['query']) ? '?'.$parts['query'] : '';
    //请求headers组装
    $string  = "$method $path HTTP/1.0\r\n";
    $string .= "Host: {$host}{$_port}\r\n";
    //组装Cookie
    if(!empty($_COOKIE)){
        $cookie  = http_build_query($_COOKIE, '', '; ');
        $string .= "Cookie: {$cookie}\r\n";
    }
    //组装Referer
    if($method == 'POST'){
        $referer = ($isSsl?'https://':'http://')
                   .$_SERVER['HTTP_HOST'].'/'
                   .ltrim($_SERVER['REQUEST_URI'], '/');
        $string .= "Referer: {$referer}\r\n";
    }else if(isset($_SERVER['HTTP_REFERER'])){
        $string .= "Referer: {$_SERVER['HTTP_REFERER']}\r\n";
    }
    //组装User-Agent
    if(isset($_SERVER['HTTP_USER_AGENT'])){
        $string .= "User-Agent: ".$_SERVER['HTTP_USER_AGENT']."\r\n";
    }
    $string .= "Connection: Close\r\n"; //关闭
    //组装post数据
    if($method == 'POST'){
        if(is_array($post)) $post = http_build_query($post);
        $string .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $string .= 'Content-Length: '.strlen($post)."\r\n\r\n";
        $string .= $post;
    }else{
        $string .= "\r\n";
    }

    //打开连接
    $fp = fsockopen($protocol.$host, $port, $errno, $errstr, 30);
    if(!$fp) exit($errstr);  //出现错误直接抛出异常
    fwrite($fp, $string); //写入
    //file_put_contents('./test.txt', "-\n".$string, FILE_APPEND | LOCK_EX);
    $result = '';
    while(!feof($fp)){
        $result .= fgets($fp, 1024);
    }
    fclose($fp); //关闭连接
    //file_put_contents('./test.txt', "-\n".$result, FILE_APPEND | LOCK_EX);
    list($header, $body) = explode("\r\n\r\n", $result, 2);
    $header = explode("\r\n", $header);
    $http = explode(' ', $header[0], 2);
    header('HTTP/1.1 ' . $http[1]);
    // 确保FastCGI模式下正常
    header('Status:' . $http[1]);
    unset($header[0]);
    foreach($header as $string) header($string);
    echo $body;
}