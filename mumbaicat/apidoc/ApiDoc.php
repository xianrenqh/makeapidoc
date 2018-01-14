<?php
namespace mumbaicat\apidoc;

class ApiDoc
{

    private $mainRegex = '/(\/\*\*.*?\*\sapi.*?\*\/\s*(public|private|protected)?\s*function\s+.*?\s*?\()/s';
    protected $documentPath;
    protected $savePath;
    protected $name = 'api';

    public function __construct($documentPath,$savePath=null)
    {
        $this->documentPath = $documentPath;
        if($savePath == null){
            $this->savePath = getcwd().DIRECTORY_SEPARATOR;
        }else{
            $this->savePath = $savePath;
        }
    }
    
    /**
     * 设置项目名称
     * @param string $name 项目名称
     */
    public function setName($name){
        $this->name = $name;
    }

    /**
     * 递归法获取文件夹下文件
     * @param string $path 路径
     * @param array $fileList 结果保存的变量
     * @param bool $all 可选,true全部,false当前路径下,默认true.
     */
    private function getFileList($path, &$fileList = [], $all = true)
    {
        if (!is_dir($path)) {
            $fileList = [];
            return;
        }
        $data = scandir($path);
        foreach ($data as $one) {
            if ($one == '.' or $one == '..') {
                continue;
            }
            $onePath = $path . '\\' . $one;
            $isDir = is_dir($onePath);
            $extName = substr($one, -4, 4);
            if ($isDir == false and $extName == '.php') {
                $fileList[] = $onePath;
            } elseif ($isDir == true and $all == true) {
                $this->getFileList($onePath, $fileList, $all);
            }
        }
    }

    /**
     * 获取代码文件中所有可以生成api的注释
     * @param string $data 代码文件内容
     */
    private function catchEvery($data)
    {
        preg_match_all($this->mainRegex, $data, $matches);
        if (empty($matches[1])) {
            return [];
        } else {
            return $matches[1];
        }
    }

    /**
     * 解析每一条可以生成API文档的注释成数组
     * @param string $data 注释文本 catchEvery返回的每个元素
     * @return array
     */
    private function parse($data)
    {
        $return = [];
        preg_match_all('/(public|private|protected)?\s*function\s+(.*?)\(/', $data, $matches);
        $return['funcName'] = !empty($matches[2][0]) ? $matches[2][0] : '[null]';
        preg_match_all('/\/\*\*\s+\*\s+(.*?)\s+\*\s+api\s+/s', $data, $matches);
        $return['methodName'] = !empty($matches[1][0]) ? $matches[1][0] : '[null]';
        preg_match_all('/\s+\*\s+api\s+(.*?)\s+(.*?)\s+(\s+\*\s+@)?.*/', $data, $matches);
        $return['requestName'] = !empty($matches[1][0]) ? $matches[1][0] : '[null]';
        $return['requestUrl'] = !empty($matches[2][0]) ? $matches[2][0] : '[null]';
        preg_match_all('/\s+\*\s+@param\s+(.*?)\s+(.*?)\s+(.*?)\s/', $data, $matches);
        if(empty($matches[1])){
            $return['param'] = [];
        }else{
            for($i=0;$i<count($matches[1]);$i++){
                $type = !empty($matches[1][$i]) ? $matches[1][$i] : '[null]';
                $var = !empty($matches[2][$i]) ? $matches[2][$i] : '[null]';
                $about = !empty($matches[3][$i]) ? $matches[3][$i] : '[null]';
                $return['param'][] = [
                    'type' => $type,
                    'var' => $var,
                    'about' => $about,
                ];
            }
        }
        preg_match_all('/\s+\*\s+@return\s+(.*?)\s+(.*?)\s+(.*?)\s/', $data, $matches);
        if(empty($matches[1])){
            $return['return'] = [];
        }else{
            for($i=0;$i<count($matches[1]);$i++){
                $type = !empty($matches[1][$i]) ? $matches[1][$i] : '[null]';
                $var = !empty($matches[2][$i]) ? $matches[2][$i] : '[null]';
                $about = !empty($matches[3][$i]) ? $matches[3][$i] : '[null]';
                if(strpos($about,'*/') !== false){
                    $about = $var;
                    $var = '';
                }
                $return['return'][] = [
                    'type' => $type,
                    'var' => $var,
                    'about' => $about,
                ];
            }
        }
        return $return;
    }

    /**
     * 每个API生成表格
     * @param array $data 每个API的信息 由parse返回的
     * @return string html代码
     */
    private function makeTable($data){
        $return = '<div id="api.php/index/index/list" class="api-main">
        <div class="title">'.$data['methodName'].'</div>
        <div class="body">
            <table class="layui-table">
                <thead>
                    <tr>
                        <th>
                        '.$data['requestName'].'
                        </th>
                        <th rowspan="3">
                        '.$data['requestUrl'].'
                        </th>
                    </tr>
                </thead>
            </table>
        </div>';
        if(count($data['param'])!=0){
            $return .= '                    <div class="body">
            <table class="layui-table">
                <thead>
                    <tr>
                        <th>
                            请求名称
                        </th>
                        <th>
                            请求类型
                        </th>
                        <th>
                            请求说明
                        </th>
                    </tr>
                </thead>
                <tbody>';
            foreach($data['param'] as $param){
                $return .= '<tr>
                <td>
                    '.$param['var'].'
                </td>
                <td>
                '.$param['type'].'
                </td>
                <td>
                '.$param['about'].'
                </td>
            </tr>';
            }
            $return .= '</tbody>
            </table>
        </div>';
        }
        if(count($data['return'])!=0){
            $return .= '<div class="body">
            <table class="layui-table">
                <thead>
                    <tr>
                        <th>
                            返回名称
                        </th>
                        <th>
                            返回类型
                        </th>
                        <th>
                            返回说明
                        </th>
                    </tr>
                </thead>
                <tbody>';
            foreach($data['return'] as $param){
                $return .= '<tr>
                <td>
                    '.$param['var'].'
                </td>
                <td>
                '.$param['type'].'
                </td>
                <td>
                '.$param['about'].'
                </td>
            </tr>';
            }
            $return .= '</tbody>
            </table>
        </div>';
        }

        $return .= ' <hr>
        </div>';

        return $return;
    }

    /**
     * 生成侧边栏
     * @param array $rightList 侧边列表数组
     * @return string html代码
     */
    private function makeRight($rightList){
        $return = '';
        foreach($rightList as $d => $file){
            $return .= '<blockquote class="layui-elem-quote layui-quote-nm right-item-title">'.$d.'</blockquote>
            <ul class="right-item">';
            foreach($file as $one){
                $return .= '<li><a href="#'.$one['requestUrl'].'"><cite>'.$one['methodName'].'</cite><em>'.$one['requestUrl'].'</em></a></li>';
            }
            $return .= '</ul>';
        }

        return $return;
    }

    /**
     * 开始执行生成
     */
    public function make()
    {
        $fileList = array();
        $this->getFileList($this->documentPath,$fileList);
        $inputData = ''; // 主体部分表格
        $rightList = array(); // 侧边栏列表
        foreach($fileList as $fileName){
            $fileData = file_get_contents($fileName);
            $data = $this->catchEvery($fileData);
            foreach ($data as $one) {
                $infoData = $this->parse($one);
                $rightList[basename($fileName)][] = [
                    'methodName' => $infoData['methodName'],
                    'requestUrl' => $infoData['requestUrl'],
                ];
                $inputData .= $this->makeTable($infoData);
            }
        }
        $tempData = <<< 'EOL'
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{name} - API文档</title>
</head>
<style>
/** layui-v2.2.45 MIT License By http://www.layui.com */
 .layui-inline,img{display:inline-block;vertical-align:middle}h1,h2,h3,h4,h5,h6{font-weight:400}.layui-edge,.layui-header,.layui-inline,.layui-main{position:relative}.layui-btn,.layui-edge,.layui-inline,img{vertical-align:middle}blockquote,body,button,dd,div,dl,dt,form,h1,h2,h3,h4,h5,h6,input,li,ol,p,pre,td,textarea,th,ul{margin:0;padding:0;-webkit-tap-highlight-color:rgba(0,0,0,0)}a:active,a:hover{outline:0}img{border:none}li{list-style:none}table{border-collapse:collapse;border-spacing:0}h4,h5,h6{font-size:100%}button,input,optgroup,option,select,textarea{font-family:inherit;font-size:inherit;font-style:inherit;font-weight:inherit;outline:0}pre{white-space:pre-wrap;white-space:-moz-pre-wrap;white-space:-pre-wrap;white-space:-o-pre-wrap;word-wrap:break-word}body{line-height:24px;font:14px Helvetica Neue,Helvetica,PingFang SC,\5FAE\8F6F\96C5\9ED1,Tahoma,Arial,sans-serif}hr{height:1px;margin:10px 0;border:0;clear:both}a{color:#333;text-decoration:none}a:hover{color:#777}a cite{font-style:normal;*cursor:pointer}.layui-border-box,.layui-border-box *{box-sizing:border-box}.layui-box,.layui-box *{box-sizing:content-box}.layui-clear{clear:both;*zoom:1}.layui-clear:after{content:'\20';clear:both;*zoom:1;display:block;height:0}.layui-inline{*display:inline;*zoom:1}.layui-edge{display:inline-block;width:0;height:0;border-width:6px;border-style:dashed;border-color:transparent;overflow:hidden}.layui-edge-top{top:-4px;border-bottom-color:#999;border-bottom-style:solid}.layui-edge-right{border-left-color:#999;border-left-style:solid}.layui-edge-bottom{top:2px;border-top-color:#999;border-top-style:solid}.layui-edge-left{border-right-color:#999;border-right-style:solid}.layui-elip{text-overflow:ellipsis;overflow:hidden;white-space:nowrap}.layui-disabled,.layui-icon,.layui-unselect{-moz-user-select:none;-webkit-user-select:none;-ms-user-select:none}.layui-disabled,.layui-disabled:hover{color:#d2d2d2!important;cursor:not-allowed!important}.layui-circle{border-radius:100%}.layui-show{display:block!important}.layui-hide{display:none!important}@font-face{font-family:layui-icon;src:url(../font/iconfont.eot?v=220);src:url(../font/iconfont.eot?v=220#iefix) format('embedded-opentype'),url(../font/iconfont.svg?v=220#iconfont) format('svg'),url(../font/iconfont.woff?v=220) format('woff'),url(../font/iconfont.ttf?v=220) format('truetype')}.layui-icon{font-family:layui-icon!important;font-size:16px;font-style:normal;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}.layui-main{width:1140px;margin:0 auto}.layui-header{z-index:1000;height:60px}.layui-header a:hover{transition:all .5s;-webkit-transition:all .5s}.layui-side{position:fixed;top:0;bottom:0;z-index:999;width:200px;overflow-x:hidden}.layui-side-scroll{width:220px;height:100%;overflow-x:hidden}.layui-body{position:absolute;left:200px;right:0;top:0;bottom:0;z-index:998;width:auto;overflow:hidden;overflow-y:auto;box-sizing:border-box}.layui-layout-body{overflow:hidden}.layui-layout-admin .layui-header{background-color:#23262E}.layui-layout-admin .layui-side{top:60px;width:200px;overflow-x:hidden}.layui-layout-admin .layui-body{top:60px;bottom:44px}.layui-layout-admin .layui-main{width:auto;margin:0 15px}.layui-layout-admin .layui-footer{position:fixed;left:200px;right:0;bottom:0;height:44px;line-height:44px;padding:0 15px;background-color:#eee}.layui-layout-admin .layui-logo{position:absolute;left:0;top:0;width:200px;height:100%;line-height:60px;text-align:center;color:#009688;font-size:16px}.layui-layout-admin .layui-header .layui-nav{background:0 0}.layui-layout-left{position:absolute!important;left:200px;top:0}.layui-layout-right{position:absolute!important;right:0;top:0}.layui-container{position:relative;margin:0 auto;padding:0 15px;box-sizing:border-box}.layui-fluid{position:relative;margin:0 auto;padding:0 15px}.layui-row:after,.layui-row:before{content:'';display:block;clear:both}.layui-col-lg1,.layui-col-lg10,.layui-col-lg11,.layui-col-lg12,.layui-col-lg2,.layui-col-lg3,.layui-col-lg4,.layui-col-lg5,.layui-col-lg6,.layui-col-lg7,.layui-col-lg8,.layui-col-lg9,.layui-col-md1,.layui-col-md10,.layui-col-md11,.layui-col-md12,.layui-col-md2,.layui-col-md3,.layui-col-md4,.layui-col-md5,.layui-col-md6,.layui-col-md7,.layui-col-md8,.layui-col-md9,.layui-col-sm1,.layui-col-sm10,.layui-col-sm11,.layui-col-sm12,.layui-col-sm2,.layui-col-sm3,.layui-col-sm4,.layui-col-sm5,.layui-col-sm6,.layui-col-sm7,.layui-col-sm8,.layui-col-sm9,.layui-col-xs1,.layui-col-xs10,.layui-col-xs11,.layui-col-xs12,.layui-col-xs2,.layui-col-xs3,.layui-col-xs4,.layui-col-xs5,.layui-col-xs6,.layui-col-xs7,.layui-col-xs8,.layui-col-xs9{position:relative;display:block;box-sizing:border-box}.layui-col-xs1,.layui-col-xs10,.layui-col-xs11,.layui-col-xs12,.layui-col-xs2,.layui-col-xs3,.layui-col-xs4,.layui-col-xs5,.layui-col-xs6,.layui-col-xs7,.layui-col-xs8,.layui-col-xs9{float:left}.layui-col-xs1{width:8.33333333%}.layui-col-xs2{width:16.66666667%}.layui-col-xs3{width:25%}.layui-col-xs4{width:33.33333333%}.layui-col-xs5{width:41.66666667%}.layui-col-xs6{width:50%}.layui-col-xs7{width:58.33333333%}.layui-col-xs8{width:66.66666667%}.layui-col-xs9{width:75%}.layui-col-xs10{width:83.33333333%}.layui-col-xs11{width:91.66666667%}.layui-col-xs12{width:100%}.layui-col-xs-offset1{margin-left:8.33333333%}.layui-col-xs-offset2{margin-left:16.66666667%}.layui-col-xs-offset3{margin-left:25%}.layui-col-xs-offset4{margin-left:33.33333333%}.layui-col-xs-offset5{margin-left:41.66666667%}.layui-col-xs-offset6{margin-left:50%}.layui-col-xs-offset7{margin-left:58.33333333%}.layui-col-xs-offset8{margin-left:66.66666667%}.layui-col-xs-offset9{margin-left:75%}.layui-col-xs-offset10{margin-left:83.33333333%}.layui-col-xs-offset11{margin-left:91.66666667%}.layui-col-xs-offset12{margin-left:100%}@media screen and (max-width:768px){.layui-hide-xs{display:none!important}.layui-show-xs-block{display:block!important}.layui-show-xs-inline{display:inline!important}.layui-show-xs-inline-block{display:inline-block!important}}@media screen and (min-width:768px){.layui-container{width:750px}.layui-hide-sm{display:none!important}.layui-show-sm-block{display:block!important}.layui-show-sm-inline{display:inline!important}.layui-show-sm-inline-block{display:inline-block!important}.layui-col-sm1,.layui-col-sm10,.layui-col-sm11,.layui-col-sm12,.layui-col-sm2,.layui-col-sm3,.layui-col-sm4,.layui-col-sm5,.layui-col-sm6,.layui-col-sm7,.layui-col-sm8,.layui-col-sm9{float:left}.layui-col-sm1{width:8.33333333%}.layui-col-sm2{width:16.66666667%}.layui-col-sm3{width:25%}.layui-col-sm4{width:33.33333333%}.layui-col-sm5{width:41.66666667%}.layui-col-sm6{width:50%}.layui-col-sm7{width:58.33333333%}.layui-col-sm8{width:66.66666667%}.layui-col-sm9{width:75%}.layui-col-sm10{width:83.33333333%}.layui-col-sm11{width:91.66666667%}.layui-col-sm12{width:100%}.layui-col-sm-offset1{margin-left:8.33333333%}.layui-col-sm-offset2{margin-left:16.66666667%}.layui-col-sm-offset3{margin-left:25%}.layui-col-sm-offset4{margin-left:33.33333333%}.layui-col-sm-offset5{margin-left:41.66666667%}.layui-col-sm-offset6{margin-left:50%}.layui-col-sm-offset7{margin-left:58.33333333%}.layui-col-sm-offset8{margin-left:66.66666667%}.layui-col-sm-offset9{margin-left:75%}.layui-col-sm-offset10{margin-left:83.33333333%}.layui-col-sm-offset11{margin-left:91.66666667%}.layui-col-sm-offset12{margin-left:100%}}@media screen and (min-width:992px){.layui-container{width:970px}.layui-hide-md{display:none!important}.layui-show-md-block{display:block!important}.layui-show-md-inline{display:inline!important}.layui-show-md-inline-block{display:inline-block!important}.layui-col-md1,.layui-col-md10,.layui-col-md11,.layui-col-md12,.layui-col-md2,.layui-col-md3,.layui-col-md4,.layui-col-md5,.layui-col-md6,.layui-col-md7,.layui-col-md8,.layui-col-md9{float:left}.layui-col-md1{width:8.33333333%}.layui-col-md2{width:16.66666667%}.layui-col-md3{width:25%}.layui-col-md4{width:33.33333333%}.layui-col-md5{width:41.66666667%}.layui-col-md6{width:50%}.layui-col-md7{width:58.33333333%}.layui-col-md8{width:66.66666667%}.layui-col-md9{width:75%}.layui-col-md10{width:83.33333333%}.layui-col-md11{width:91.66666667%}.layui-col-md12{width:100%}.layui-col-md-offset1{margin-left:8.33333333%}.layui-col-md-offset2{margin-left:16.66666667%}.layui-col-md-offset3{margin-left:25%}.layui-col-md-offset4{margin-left:33.33333333%}.layui-col-md-offset5{margin-left:41.66666667%}.layui-col-md-offset6{margin-left:50%}.layui-col-md-offset7{margin-left:58.33333333%}.layui-col-md-offset8{margin-left:66.66666667%}.layui-col-md-offset9{margin-left:75%}.layui-col-md-offset10{margin-left:83.33333333%}.layui-col-md-offset11{margin-left:91.66666667%}.layui-col-md-offset12{margin-left:100%}}@media screen and (min-width:1200px){.layui-container{width:1170px}.layui-hide-lg{display:none!important}.layui-show-lg-block{display:block!important}.layui-show-lg-inline{display:inline!important}.layui-show-lg-inline-block{display:inline-block!important}.layui-col-lg1,.layui-col-lg10,.layui-col-lg11,.layui-col-lg12,.layui-col-lg2,.layui-col-lg3,.layui-col-lg4,.layui-col-lg5,.layui-col-lg6,.layui-col-lg7,.layui-col-lg8,.layui-col-lg9{float:left}.layui-col-lg1{width:8.33333333%}.layui-col-lg2{width:16.66666667%}.layui-col-lg3{width:25%}.layui-col-lg4{width:33.33333333%}.layui-col-lg5{width:41.66666667%}.layui-col-lg6{width:50%}.layui-col-lg7{width:58.33333333%}.layui-col-lg8{width:66.66666667%}.layui-col-lg9{width:75%}.layui-col-lg10{width:83.33333333%}.layui-col-lg11{width:91.66666667%}.layui-col-lg12{width:100%}.layui-col-lg-offset1{margin-left:8.33333333%}.layui-col-lg-offset2{margin-left:16.66666667%}.layui-col-lg-offset3{margin-left:25%}.layui-col-lg-offset4{margin-left:33.33333333%}.layui-col-lg-offset5{margin-left:41.66666667%}.layui-col-lg-offset6{margin-left:50%}.layui-col-lg-offset7{margin-left:58.33333333%}.layui-col-lg-offset8{margin-left:66.66666667%}.layui-col-lg-offset9{margin-left:75%}.layui-col-lg-offset10{margin-left:83.33333333%}.layui-col-lg-offset11{margin-left:91.66666667%}.layui-col-lg-offset12{margin-left:100%}}.layui-col-space1{margin:-.5px}.layui-col-space1>*{padding:.5px}.layui-col-space3{margin:-1.5px}.layui-col-space3>*{padding:1.5px}.layui-col-space5{margin:-2.5px}.layui-col-space5>*{padding:2.5px}.layui-col-space8{margin:-3.5px}.layui-col-space8>*{padding:3.5px}.layui-col-space10{margin:-5px}.layui-col-space10>*{padding:5px}.layui-col-space12{margin:-6px}.layui-col-space12>*{padding:6px}.layui-col-space15{margin:-7.5px}.layui-col-space15>*{padding:7.5px}.layui-col-space18{margin:-9px}.layui-col-space18>*{padding:9px}.layui-col-space20{margin:-10px}.layui-col-space20>*{padding:10px}.layui-col-space22{margin:-11px}.layui-col-space22>*{padding:11px}.layui-col-space25{margin:-12.5px}.layui-col-space25>*{padding:12.5px}.layui-col-space30{margin:-15px}.layui-col-space30>*{padding:15px}.layui-btn,.layui-input,.layui-select,.layui-textarea,.layui-upload-button{outline:0;-webkit-appearance:none;transition:all .3s;-webkit-transition:all .3s;box-sizing:border-box}.layui-elem-quote{margin-bottom:10px;padding:15px;line-height:22px;border-left:5px solid #009688;border-radius:0 2px 2px 0;background-color:#f2f2f2}.layui-quote-nm{border-style:solid;border-width:1px 1px 1px 5px;background:0 0}.layui-elem-field{margin-bottom:10px;padding:0;border-width:1px;border-style:solid}.layui-elem-field legend{margin-left:20px;padding:0 10px;font-size:20px;font-weight:300}.layui-field-title{margin:10px 0 20px;border-width:1px 0 0}.layui-field-box{padding:10px 15px}.layui-field-title .layui-field-box{padding:10px 0}.layui-progress{position:relative;height:6px;border-radius:20px;background-color:#e2e2e2}.layui-progress-bar{position:absolute;left:0;top:0;width:0;max-width:100%;height:6px;border-radius:20px;text-align:right;background-color:#5FB878;transition:all .3s;-webkit-transition:all .3s}.layui-progress-big,.layui-progress-big .layui-progress-bar{height:18px;line-height:18px}.layui-progress-text{position:relative;top:-18px;line-height:18px;font-size:12px;color:#666}.layui-progress-big .layui-progress-text{position:static;padding:0 10px;color:#fff}.layui-card-header,.layui-colla-title{position:relative;height:42px;color:#333;font-size:14px}.layui-card{margin-bottom:15px;border-radius:2px;background-color:#fff;box-shadow:0 1px 2px 0 rgba(0,0,0,.05)}.layui-card:last-child{margin-bottom:0}.layui-card-header{line-height:42px;padding:0 15px;border-bottom:1px solid #f6f6f6;border-radius:2px 2px 0 0}.layui-card-body{position:relative;padding:10px 15px;line-height:24px}.layui-card-body .layui-table{margin:5px 0}.layui-card .layui-tab{margin:0}.layui-collapse{border-width:1px;border-style:solid;border-radius:2px}.layui-colla-content,.layui-colla-item{border-top-width:1px;border-top-style:solid}.layui-colla-item:first-child{border-top:none}.layui-colla-title{line-height:42px;padding:0 15px 0 35px;background-color:#f2f2f2;cursor:pointer}.layui-colla-content{display:none;padding:10px 15px;line-height:22px;color:#666}.layui-bg-black,.layui-bg-blue,.layui-bg-cyan,.layui-bg-green,.layui-bg-orange,.layui-bg-red{color:#fff!important}.layui-colla-icon{position:absolute;left:15px;top:0;font-size:14px}.layui-bg-red{background-color:#FF5722!important}.layui-bg-orange{background-color:#FFB800!important}.layui-bg-green{background-color:#009688!important}.layui-bg-cyan{background-color:#2F4056!important}.layui-bg-blue{background-color:#1E9FFF!important}.layui-bg-black{background-color:#393D49!important}.layui-bg-gray{background-color:#eee!important;color:#666!important}.layui-badge-rim,.layui-colla-content,.layui-colla-item,.layui-collapse,.layui-elem-field,.layui-form-pane .layui-form-item[pane],.layui-form-pane .layui-form-label,.layui-input,.layui-layedit,.layui-layedit-tool,.layui-quote-nm,.layui-select,.layui-tab-bar,.layui-tab-card,.layui-tab-title,.layui-tab-title .layui-this:after,.layui-textarea{border-color:#e6e6e6}.layui-timeline-item:before,hr{background-color:#e6e6e6}.layui-text{line-height:22px;font-size:14px;color:#666}.layui-text h1,.layui-text h2,.layui-text h3{font-weight:500;color:#333}.layui-text h1{font-size:30px}.layui-text h2{font-size:24px}.layui-text h3{font-size:18px}.layui-text a:not(.layui-btn){color:#01AAED}.layui-text a:not(.layui-btn):hover{text-decoration:underline}.layui-text ul{padding:5px 0 5px 15px}.layui-text ul li{margin-top:5px;list-style-type:disc}.layui-text em,.layui-word-aux{color:#999!important;padding:0 5px!important}.layui-btn{display:inline-block;height:38px;line-height:38px;padding:0 18px;background-color:#009688;color:#fff;white-space:nowrap;text-align:center;font-size:14px;border:none;border-radius:2px;cursor:pointer;-moz-user-select:none;-webkit-user-select:none;-ms-user-select:none}.layui-btn:hover{opacity:.8;filter:alpha(opacity=80);color:#fff}.layui-btn:active{opacity:1;filter:alpha(opacity=100)}.layui-btn+.layui-btn{margin-left:10px}.layui-btn-radius{border-radius:100px}.layui-btn .layui-icon{margin-right:3px;font-size:18px;vertical-align:bottom;vertical-align:middle\9}.layui-btn-primary{border:1px solid #C9C9C9;background-color:#fff;color:#555}.layui-btn-primary:hover{border-color:#009688;color:#333}.layui-btn-normal{background-color:#1E9FFF}.layui-btn-warm{background-color:#FFB800}.layui-btn-danger{background-color:#FF5722}.layui-btn-disabled,.layui-btn-disabled:active,.layui-btn-disabled:hover{border:1px solid #e6e6e6;background-color:#FBFBFB;color:#C9C9C9;cursor:not-allowed;opacity:1}.layui-btn-lg{height:44px;line-height:44px;padding:0 25px;font-size:16px}.layui-btn-sm{height:30px;line-height:30px;padding:0 10px;font-size:12px}.layui-btn-sm i{font-size:16px!important}.layui-btn-xs{height:22px;line-height:22px;padding:0 5px;font-size:12px}.layui-btn-xs i{font-size:14px!important}.layui-btn-group{display:inline-block;vertical-align:middle;font-size:0}.layui-btn-group .layui-btn{margin-left:0!important;margin-right:0!important;border-left:1px solid rgba(255,255,255,.5);border-radius:0}.layui-btn-group .layui-btn-primary{border-left:none}.layui-btn-group .layui-btn-primary:hover{border-color:#C9C9C9;color:#009688}.layui-btn-group .layui-btn:first-child{border-left:none;border-radius:2px 0 0 2px}.layui-btn-group .layui-btn-primary:first-child{border-left:1px solid #c9c9c9}.layui-btn-group .layui-btn:last-child{border-radius:0 2px 2px 0}.layui-btn-group .layui-btn+.layui-btn{margin-left:0}.layui-btn-group+.layui-btn-group{margin-left:10px}.layui-input,.layui-select,.layui-textarea{height:38px;line-height:1.3;line-height:38px\9;border-width:1px;border-style:solid;background-color:#fff;border-radius:2px}.layui-input::-webkit-input-placeholder,.layui-select::-webkit-input-placeholder,.layui-textarea::-webkit-input-placeholder{line-height:1.3}.layui-form-label,.layui-form-mid,.layui-textarea{line-height:20px;position:relative}.layui-input,.layui-textarea{display:block;width:100%;padding-left:10px}.layui-input:hover,.layui-textarea:hover{border-color:#D2D2D2!important}.layui-input:focus,.layui-textarea:focus{border-color:#C9C9C9!important}.layui-textarea{min-height:100px;height:auto;padding:6px 10px;resize:vertical}.layui-select{padding:0 10px}.layui-form input[type=checkbox],.layui-form input[type=radio],.layui-form select{display:none}.layui-form [lay-ignore]{display:initial}.layui-form-item{margin-bottom:15px;clear:both;*zoom:1}.layui-form-item:after{content:'\20';clear:both;*zoom:1;display:block;height:0}.layui-form-label{float:left;display:block;padding:9px 15px;width:80px;font-weight:400;text-align:right}.layui-form-item .layui-inline{margin-bottom:5px;margin-right:10px}.layui-input-block,.layui-input-inline{position:relative}.layui-input-block{margin-left:110px;min-height:36px}.layui-input-inline{display:inline-block;vertical-align:middle}.layui-form-item .layui-input-inline{float:left;width:190px;margin-right:10px}.layui-form-text .layui-input-inline{width:auto}.layui-form-mid{float:left;display:block;padding:8px 0!important;margin-right:10px}.layui-form-danger+.layui-form-select .layui-input,.layui-form-danger:focus{border-color:#FF5722!important}.layui-form-select{position:relative}.layui-form-select .layui-input{padding-right:30px;cursor:pointer}.layui-form-select .layui-edge{position:absolute;right:10px;top:50%;margin-top:-3px;cursor:pointer;border-width:6px;border-top-color:#c2c2c2;border-top-style:solid;transition:all .3s;-webkit-transition:all .3s}.layui-form-select dl{display:none;position:absolute;left:0;top:42px;padding:5px 0;z-index:999;min-width:100%;border:1px solid #d2d2d2;max-height:300px;overflow-y:auto;background-color:#fff;border-radius:2px;box-shadow:0 2px 4px rgba(0,0,0,.12);box-sizing:border-box}.layui-form-select dl dd,.layui-form-select dl dt{padding:0 10px;line-height:36px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.layui-form-select dl dt{font-size:12px;color:#999}.layui-form-select dl dd{cursor:pointer}.layui-form-select dl dd:hover{background-color:#f2f2f2}.layui-form-select .layui-select-group dd{padding-left:20px}.layui-form-select dl dd.layui-select-tips{padding-left:10px!important;color:#999}.layui-form-select dl dd.layui-this{background-color:#5FB878;color:#fff}.layui-form-checkbox,.layui-form-select dl dd.layui-disabled{background-color:#fff}.layui-form-selected dl{display:block}.layui-form-checkbox,.layui-form-checkbox *,.layui-form-radio,.layui-form-radio *,.layui-form-switch{display:inline-block;vertical-align:middle}.layui-form-selected .layui-edge{margin-top:-9px;-webkit-transform:rotate(180deg);transform:rotate(180deg);margin-top:-3px\9}:root .layui-form-selected .layui-edge{margin-top:-9px\0/IE9}.layui-form-selectup dl{top:auto;bottom:42px}.layui-select-none{margin:5px 0;text-align:center;color:#999}.layui-select-disabled .layui-disabled{border-color:#eee!important}.layui-select-disabled .layui-edge{border-top-color:#d2d2d2}.layui-form-checkbox{position:relative;height:30px;line-height:28px;margin-right:10px;padding-right:30px;border:1px solid #d2d2d2;cursor:pointer;font-size:0;border-radius:2px;-webkit-transition:.1s linear;transition:.1s linear;box-sizing:border-box}.layui-form-checkbox:hover{border:1px solid #c2c2c2}.layui-form-checkbox span{padding:0 10px;height:100%;font-size:14px;background-color:#d2d2d2;color:#fff;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.layui-form-checkbox:hover span{background-color:#c2c2c2}.layui-form-checkbox i{position:absolute;right:0;width:30px;color:#fff;font-size:20px;text-align:center}.layui-form-checkbox:hover i{color:#c2c2c2}.layui-form-checked,.layui-form-checked:hover{border-color:#5FB878}.layui-form-checked span,.layui-form-checked:hover span{background-color:#5FB878}.layui-form-checked i,.layui-form-checked:hover i{color:#5FB878}.layui-form-item .layui-form-checkbox{margin-top:4px}.layui-form-checkbox[lay-skin=primary]{height:auto!important;line-height:normal!important;border:none!important;margin-right:0;padding-right:0;background:0 0}.layui-form-checkbox[lay-skin=primary] span{float:right;padding-right:15px;line-height:18px;background:0 0;color:#666}.layui-form-checkbox[lay-skin=primary] i{position:relative;top:0;width:16px;height:16px;line-height:16px;border:1px solid #d2d2d2;font-size:12px;border-radius:2px;background-color:#fff;-webkit-transition:.1s linear;transition:.1s linear}.layui-form-checkbox[lay-skin=primary]:hover i{border-color:#5FB878;color:#fff}.layui-form-checked[lay-skin=primary] i{border-color:#5FB878;background-color:#5FB878;color:#fff}.layui-checkbox-disbaled[lay-skin=primary] span{background:0 0!important;color:#c2c2c2}.layui-checkbox-disbaled[lay-skin=primary]:hover i{border-color:#d2d2d2}.layui-form-item .layui-form-checkbox[lay-skin=primary]{margin-top:10px}.layui-form-switch{position:relative;height:22px;line-height:22px;width:42px;padding:0 5px;margin-top:8px;border:1px solid #d2d2d2;border-radius:20px;cursor:pointer;background-color:#fff;-webkit-transition:.1s linear;transition:.1s linear}.layui-form-switch i{position:absolute;left:5px;top:3px;width:16px;height:16px;border-radius:20px;background-color:#d2d2d2;-webkit-transition:.1s linear;transition:.1s linear}.layui-form-switch em{position:absolute;right:5px;top:0;width:25px;padding:0!important;text-align:center!important;color:#999!important;font-style:normal!important;font-size:12px}.layui-form-onswitch{border-color:#5FB878;background-color:#5FB878}.layui-form-onswitch i{left:32px;background-color:#fff}.layui-form-onswitch em{left:5px;right:auto;color:#fff!important}.layui-checkbox-disbaled{border-color:#e2e2e2!important}.layui-checkbox-disbaled span{background-color:#e2e2e2!important}.layui-checkbox-disbaled:hover i{color:#fff!important}.layui-form-radio{line-height:28px;margin:6px 10px 0 0;padding-right:10px;cursor:pointer;font-size:0}.layui-form-radio i{margin-right:8px;font-size:22px;color:#c2c2c2}.layui-form-radio span{font-size:14px}.layui-form-radio i:hover,.layui-form-radioed i{color:#5FB878}.layui-radio-disbaled i{color:#e2e2e2!important}.layui-form-pane .layui-form-label{width:110px;padding:8px 15px;height:38px;line-height:20px;border-width:1px;border-style:solid;border-radius:2px 0 0 2px;text-align:center;background-color:#FBFBFB;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;box-sizing:border-box}.layui-form-pane .layui-input-inline{margin-left:-1px}.layui-form-pane .layui-input-block{margin-left:110px;left:-1px}.layui-form-pane .layui-input{border-radius:0 2px 2px 0}.layui-form-pane .layui-form-text .layui-form-label{float:none;width:100%;border-radius:2px;box-sizing:border-box;text-align:left}.layui-form-pane .layui-form-text .layui-input-inline{display:block;margin:0;top:-1px;clear:both}.layui-form-pane .layui-form-text .layui-input-block{margin:0;left:0;top:-1px}.layui-form-pane .layui-form-text .layui-textarea{min-height:100px;border-radius:0 0 2px 2px}.layui-form-pane .layui-form-checkbox{margin:4px 0 4px 10px}.layui-form-pane .layui-form-radio,.layui-form-pane .layui-form-switch{margin-top:6px;margin-left:10px}.layui-form-pane .layui-form-item[pane]{position:relative;border-width:1px;border-style:solid}.layui-form-pane .layui-form-item[pane] .layui-form-label{position:absolute;left:0;top:0;height:100%;border-width:0 1px 0 0}.layui-form-pane .layui-form-item[pane] .layui-input-inline{margin-left:110px}@media screen and (max-width:450px){.layui-form-item .layui-form-label{text-overflow:ellipsis;overflow:hidden;white-space:nowrap}.layui-form-item .layui-inline{display:block;margin-right:0;margin-bottom:20px;clear:both}.layui-form-item .layui-inline:after{content:'\20';clear:both;display:block;height:0}.layui-form-item .layui-input-inline{display:block;float:none;left:-3px;width:auto;margin:0 0 10px 112px}.layui-form-item .layui-input-inline+.layui-form-mid{margin-left:110px;top:-5px;padding:0}.layui-form-item .layui-form-checkbox{margin-right:5px;margin-bottom:5px}}.layui-layedit{border-width:1px;border-style:solid;border-radius:2px}.layui-layedit-tool{padding:3px 5px;border-bottom-width:1px;border-bottom-style:solid;font-size:0}.layedit-tool-fixed{position:fixed;top:0;border-top:1px solid #e2e2e2}.layui-layedit-tool .layedit-tool-mid,.layui-layedit-tool .layui-icon{display:inline-block;vertical-align:middle;text-align:center;font-size:14px}.layui-layedit-tool .layui-icon{position:relative;width:32px;height:30px;line-height:30px;margin:3px 5px;color:#777;cursor:pointer;border-radius:2px}.layui-layedit-tool .layui-icon:hover{color:#393D49}.layui-layedit-tool .layui-icon:active{color:#000}.layui-layedit-tool .layedit-tool-active{background-color:#e2e2e2;color:#000}.layui-layedit-tool .layui-disabled,.layui-layedit-tool .layui-disabled:hover{color:#d2d2d2;cursor:not-allowed}.layui-layedit-tool .layedit-tool-mid{width:1px;height:18px;margin:0 10px;background-color:#d2d2d2}.layedit-tool-html{width:50px!important;font-size:30px!important}.layedit-tool-b,.layedit-tool-code,.layedit-tool-help{font-size:16px!important}.layedit-tool-d,.layedit-tool-face,.layedit-tool-image,.layedit-tool-unlink{font-size:18px!important}.layedit-tool-image input{position:absolute;font-size:0;left:0;top:0;width:100%;height:100%;opacity:.01;filter:Alpha(opacity=1);cursor:pointer}.layui-layedit-iframe iframe{display:block;width:100%}#LAY_layedit_code{overflow:hidden}.layui-laypage{display:inline-block;*display:inline;*zoom:1;vertical-align:middle;margin:10px 0;font-size:0}.layui-laypage>a:first-child,.layui-laypage>a:first-child em{border-radius:2px 0 0 2px}.layui-laypage>a:last-child,.layui-laypage>a:last-child em{border-radius:0 2px 2px 0}.layui-laypage>:first-child{margin-left:0!important}.layui-laypage>:last-child{margin-right:0!important}.layui-laypage a,.layui-laypage button,.layui-laypage input,.layui-laypage select,.layui-laypage span{border:1px solid #e2e2e2}.layui-laypage a,.layui-laypage span{display:inline-block;*display:inline;*zoom:1;vertical-align:middle;padding:0 15px;height:28px;line-height:28px;margin:0 -1px 5px 0;background-color:#fff;color:#333;font-size:12px}.layui-laypage a:hover{color:#009688}.layui-laypage em{font-style:normal}.layui-laypage .layui-laypage-spr{color:#999;font-weight:700}.layui-laypage a{text-decoration:none}.layui-laypage .layui-laypage-curr{position:relative}.layui-laypage .layui-laypage-curr em{position:relative;color:#fff}.layui-laypage .layui-laypage-curr .layui-laypage-em{position:absolute;left:-1px;top:-1px;padding:1px;width:100%;height:100%;background-color:#009688}.layui-laypage-em{border-radius:2px}.layui-laypage-next em,.layui-laypage-prev em{font-family:Sim sun;font-size:16px}.layui-laypage .layui-laypage-count,.layui-laypage .layui-laypage-limits,.layui-laypage .layui-laypage-skip{margin-left:10px;margin-right:10px;padding:0;border:none}.layui-laypage .layui-laypage-limits{vertical-align:top}.layui-laypage select{height:22px;padding:3px;border-radius:2px;cursor:pointer}.layui-laypage .layui-laypage-skip{height:30px;line-height:30px;color:#999}.layui-laypage button,.layui-laypage input{height:30px;line-height:30px;border-radius:2px;vertical-align:top;background-color:#fff;box-sizing:border-box}.layui-laypage input{display:inline-block;width:40px;margin:0 10px;padding:0 3px;text-align:center}.layui-laypage input:focus,.layui-laypage select:focus{border-color:#009688!important}.layui-laypage button{margin-left:10px;padding:0 10px;cursor:pointer}.layui-table,.layui-table-view{margin:10px 0}.layui-flow-more{margin:10px 0;text-align:center;color:#999;font-size:14px}.layui-flow-more a{height:32px;line-height:32px}.layui-flow-more a *{display:inline-block;vertical-align:top}.layui-flow-more a cite{padding:0 20px;border-radius:3px;background-color:#eee;color:#333;font-style:normal}.layui-flow-more a cite:hover{opacity:.8}.layui-flow-more a i{font-size:30px;color:#737383}.layui-table{width:100%;background-color:#fff;color:#666}.layui-table tr{transition:all .3s;-webkit-transition:all .3s}.layui-table th{text-align:left;font-weight:400}.layui-table tbody tr:hover,.layui-table thead tr,.layui-table-click,.layui-table-header,.layui-table-hover,.layui-table-mend,.layui-table-patch,.layui-table-tool,.layui-table[lay-even] tr:nth-child(even){background-color:#f2f2f2}.layui-table td,.layui-table th,.layui-table-fixed-r,.layui-table-header,.layui-table-page,.layui-table-tips-main,.layui-table-tool,.layui-table-view,.layui-table[lay-skin=row],.layui-table[lay-skin=line]{border-width:1px;border-style:solid;border-color:#e6e6e6}.layui-table td,.layui-table th{position:relative;padding:9px 15px;min-height:20px;line-height:20px;font-size:14px}.layui-table[lay-skin=line] td,.layui-table[lay-skin=line] th{border-width:0 0 1px}.layui-table[lay-skin=row] td,.layui-table[lay-skin=row] th{border-width:0 1px 0 0}.layui-table[lay-skin=nob] td,.layui-table[lay-skin=nob] th{border:none}.layui-table img{max-width:100px}.layui-table[lay-size=lg] td,.layui-table[lay-size=lg] th{padding:15px 30px}.layui-table-view .layui-table[lay-size=lg] .layui-table-cell{height:40px;line-height:40px}.layui-table[lay-size=sm] td,.layui-table[lay-size=sm] th{font-size:12px;padding:5px 10px}.layui-table-view .layui-table[lay-size=sm] .layui-table-cell{height:20px;line-height:20px}.layui-table[lay-data]{display:none}.layui-table-box,.layui-table-view{position:relative;overflow:hidden}.layui-table-view .layui-table{position:relative;width:auto;margin:0}.layui-table-body,.layui-table-header .layui-table,.layui-table-page{margin-bottom:-1px}.layui-table-view .layui-table[lay-skin=line]{border-width:0 1px 0 0}.layui-table-view .layui-table[lay-skin=row]{border-width:0 0 1px}.layui-table-view .layui-table td,.layui-table-view .layui-table th{padding:5px 0;border-top:none;border-left:none}.layui-table-view .layui-table td{cursor:default}.layui-table-view .layui-form-checkbox[lay-skin=primary] i{width:18px;height:18px}.layui-table-header{border-width:0 0 1px;overflow:hidden}.layui-table-sort{width:10px;height:20px;margin-left:5px;cursor:pointer!important}.layui-table-sort .layui-edge{position:absolute;left:5px;border-width:5px}.layui-table-sort .layui-table-sort-asc{top:4px;border-top:none;border-bottom-style:solid;border-bottom-color:#b2b2b2}.layui-table-sort .layui-table-sort-asc:hover{border-bottom-color:#666}.layui-table-sort .layui-table-sort-desc{bottom:4px;border-bottom:none;border-top-style:solid;border-top-color:#b2b2b2}.layui-table-sort .layui-table-sort-desc:hover{border-top-color:#666}.layui-table-sort[lay-sort=asc] .layui-table-sort-asc{border-bottom-color:#000}.layui-table-sort[lay-sort=desc] .layui-table-sort-desc{border-top-color:#000}.layui-table-cell{height:28px;line-height:28px;padding:0 15px;position:relative;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;box-sizing:border-box}.layui-table-cell .layui-form-checkbox[lay-skin=primary]{top:-1px;vertical-align:middle}.layui-table-cell .layui-table-link{color:#01AAED}.laytable-cell-checkbox,.laytable-cell-numbers,.laytable-cell-space{padding:0;text-align:center}.layui-table-body{position:relative;overflow:auto;margin-right:-1px}.layui-table-body .layui-none{line-height:40px;text-align:center;color:#999}.layui-table-fixed{position:absolute;left:0;top:0}.layui-table-fixed .layui-table-body{overflow:hidden}.layui-table-fixed-l{box-shadow:0 -1px 8px rgba(0,0,0,.08)}.layui-table-fixed-r{left:auto;right:-1px;border-width:0 0 0 1px;box-shadow:-1px 0 8px rgba(0,0,0,.08)}.layui-table-fixed-r .layui-table-header{position:relative;overflow:visible}.layui-table-mend{position:absolute;right:-49px;top:0;height:100%;width:50px}.layui-table-tool{position:relative;width:100%;height:50px;line-height:30px;padding:10px 15px;border-width:0 0 1px}.layui-table-page{position:relative;width:100%;padding:7px 7px 0;border-width:1px 0 0;height:41px;font-size:12px}.layui-table-page>div{height:26px}.layui-table-page .layui-laypage{margin:0}.layui-table-page .layui-laypage a,.layui-table-page .layui-laypage span{height:26px;line-height:26px;margin-bottom:10px;border:none;background:0 0}.layui-table-page .layui-laypage a,.layui-table-page .layui-laypage span.layui-laypage-curr{padding:0 12px}.layui-table-page .layui-laypage span{margin-left:0;padding:0}.layui-table-page .layui-laypage .layui-laypage-prev{margin-left:-7px!important}.layui-table-page .layui-laypage .layui-laypage-curr .layui-laypage-em{left:0;top:0;padding:0}.layui-table-page .layui-laypage button,.layui-table-page .layui-laypage input{height:26px;line-height:26px}.layui-table-page .layui-laypage input{width:40px}.layui-table-page .layui-laypage button{padding:0 10px}.layui-table-page select{height:18px}.layui-table-view select[lay-ignore]{display:inline-block}.layui-table-patch .layui-table-cell{padding:0;width:30px}.layui-table-edit{position:absolute;left:0;top:0;width:100%;height:100%;padding:0 14px 1px;border-radius:0;box-shadow:1px 1px 20px rgba(0,0,0,.15)}.layui-table-edit:focus{border-color:#5FB878!important}select.layui-table-edit{padding:0 0 0 10px;border-color:#C9C9C9}.layui-table-view .layui-form-checkbox,.layui-table-view .layui-form-radio,.layui-table-view .layui-form-switch{top:0;margin:0;box-sizing:content-box}.layui-table-view .layui-form-checkbox{top:-1px;height:26px;line-height:26px}body .layui-table-tips .layui-layer-content{background:0 0;padding:0;box-shadow:0 1px 6px rgba(0,0,0,.1)}.layui-table-tips-main{margin:-44px 0 0 -1px;max-height:150px;padding:8px 15px;font-size:14px;overflow-y:scroll;background-color:#fff;color:#333}.layui-code,.layui-upload-list{margin:10px 0}.layui-table-tips-c{position:absolute;right:-3px;top:-12px;width:18px;height:18px;padding:3px;text-align:center;font-weight:700;border-radius:100%;font-size:14px;cursor:pointer;background-color:#666}.layui-table-tips-c:hover{background-color:#999}.layui-upload-file{display:none!important;opacity:.01;filter:Alpha(opacity=1)}.layui-upload-drag,.layui-upload-form,.layui-upload-wrap{display:inline-block}.layui-upload-choose{padding:0 10px;color:#999}.layui-upload-drag{position:relative;padding:30px;border:1px dashed #e2e2e2;background-color:#fff;text-align:center;cursor:pointer;color:#999}.layui-upload-drag .layui-icon{font-size:50px;color:#009688}.layui-upload-drag[lay-over]{border-color:#009688}.layui-upload-iframe{position:absolute;width:0;height:0;border:0;visibility:hidden}.layui-upload-wrap{position:relative;vertical-align:middle}.layui-upload-wrap .layui-upload-file{display:block!important;position:absolute;left:0;top:0;z-index:10;font-size:100px;width:100%;height:100%;opacity:.01;filter:Alpha(opacity=1);cursor:pointer}.layui-code{position:relative;padding:15px;line-height:20px;border:1px solid #ddd;border-left-width:6px;background-color:#F2F2F2;color:#333;font-family:Courier New;font-size:12px}.layui-tree{line-height:26px}.layui-tree li{text-overflow:ellipsis;overflow:hidden;white-space:nowrap}.layui-tree li .layui-tree-spread,.layui-tree li a{display:inline-block;vertical-align:top;height:26px;*display:inline;*zoom:1;cursor:pointer}.layui-tree li a{font-size:0}.layui-tree li a i{font-size:16px}.layui-tree li a cite{padding:0 6px;font-size:14px;font-style:normal}.layui-tree li i{padding-left:6px;color:#333;-moz-user-select:none}.layui-tree li .layui-tree-check{font-size:13px}.layui-tree li .layui-tree-check:hover{color:#009E94}.layui-tree li ul{display:none;margin-left:20px}.layui-tree li .layui-tree-enter{line-height:24px;border:1px dotted #000}.layui-tree-drag{display:none;position:absolute;left:-666px;top:-666px;background-color:#f2f2f2;padding:5px 10px;border:1px dotted #000;white-space:nowrap}.layui-tree-drag i{padding-right:5px}.layui-nav{position:relative;padding:0 20px;background-color:#393D49;color:#fff;border-radius:2px;font-size:0;box-sizing:border-box}.layui-nav *{font-size:14px}.layui-nav .layui-nav-item{position:relative;display:inline-block;*display:inline;*zoom:1;vertical-align:middle;line-height:60px}.layui-nav .layui-nav-item a{display:block;padding:0 20px;color:#fff;color:rgba(255,255,255,.7);transition:all .3s;-webkit-transition:all .3s}.layui-nav .layui-this:after,.layui-nav-bar,.layui-nav-tree .layui-nav-itemed:after{position:absolute;left:0;top:0;width:0;height:5px;background-color:#5FB878;transition:all .2s;-webkit-transition:all .2s}.layui-nav-bar{z-index:1000}.layui-nav .layui-nav-item a:hover,.layui-nav .layui-this a{color:#fff}.layui-nav .layui-this:after{content:'';top:auto;bottom:0;width:100%}.layui-nav-img{width:30px;height:30px;margin-right:10px;border-radius:50%}.layui-nav .layui-nav-more{content:'';width:0;height:0;border-style:solid dashed dashed;border-color:#fff transparent transparent;overflow:hidden;cursor:pointer;transition:all .2s;-webkit-transition:all .2s;position:absolute;top:50%;right:3px;margin-top:-3px;border-width:6px;border-top-color:rgba(255,255,255,.7)}.layui-nav .layui-nav-mored,.layui-nav-itemed .layui-nav-more{margin-top:-9px;border-style:dashed dashed solid;border-color:transparent transparent #fff}.layui-nav-child{display:none;position:absolute;left:0;top:65px;min-width:100%;line-height:36px;padding:5px 0;box-shadow:0 2px 4px rgba(0,0,0,.12);border:1px solid #d2d2d2;background-color:#fff;z-index:100;border-radius:2px;white-space:nowrap}.layui-nav .layui-nav-child a{color:#333}.layui-nav .layui-nav-child a:hover{background-color:#f2f2f2;color:#000}.layui-nav-child dd{position:relative}.layui-nav .layui-nav-child dd.layui-this a,.layui-nav-child dd.layui-this{background-color:#5FB878;color:#fff}.layui-nav-child dd.layui-this:after{display:none}.layui-nav-tree{width:200px;padding:0}.layui-nav-tree .layui-nav-item{display:block;width:100%;line-height:45px}.layui-nav-tree .layui-nav-item a{height:45px;line-height:45px;text-overflow:ellipsis;overflow:hidden;white-space:nowrap}.layui-nav-tree .layui-nav-item a:hover{background-color:#4E5465}.layui-nav-tree .layui-nav-bar{width:5px;height:0;background-color:#009688}.layui-nav-tree .layui-nav-child dd.layui-this,.layui-nav-tree .layui-nav-child dd.layui-this a,.layui-nav-tree .layui-this,.layui-nav-tree .layui-this>a,.layui-nav-tree .layui-this>a:hover{background-color:#009688;color:#fff}.layui-nav-tree .layui-this:after{display:none}.layui-nav-itemed>a,.layui-nav-tree .layui-nav-title a,.layui-nav-tree .layui-nav-title a:hover{color:#fff!important}.layui-nav-tree .layui-nav-child{position:relative;z-index:0;top:0;border:none;box-shadow:none}.layui-nav-tree .layui-nav-child a{height:40px;line-height:40px;color:#fff;color:rgba(255,255,255,.7)}.layui-nav-tree .layui-nav-child,.layui-nav-tree .layui-nav-child a:hover{background:0 0;color:#fff}.layui-nav-tree .layui-nav-more{top:20px;right:10px;margin:0}.layui-nav-itemed .layui-nav-more{top:14px}.layui-nav-itemed .layui-nav-child{display:block;padding:0;background-color:rgba(0,0,0,.3)!important}.layui-nav-side{position:fixed;top:0;bottom:0;left:0;overflow-x:hidden;z-index:999}.layui-bg-blue .layui-nav-bar,.layui-bg-blue .layui-nav-itemed:after,.layui-bg-blue .layui-this:after{background-color:#93D1FF}.layui-bg-blue .layui-nav-child dd.layui-this{background-color:#1E9FFF}.layui-bg-blue .layui-nav-itemed>a,.layui-nav-tree.layui-bg-blue .layui-nav-title a,.layui-nav-tree.layui-bg-blue .layui-nav-title a:hover{background-color:#007DDB!important}.layui-breadcrumb{visibility:hidden;font-size:0}.layui-breadcrumb>*{font-size:14px}.layui-breadcrumb a{color:#999!important}.layui-breadcrumb a:hover{color:#5FB878!important}.layui-breadcrumb a cite{color:#666;font-style:normal}.layui-breadcrumb span[lay-separator]{margin:0 10px;color:#999}.layui-tab{margin:10px 0;text-align:left!important}.layui-tab[overflow]>.layui-tab-title{overflow:hidden}.layui-tab-title{position:relative;left:0;height:40px;white-space:nowrap;font-size:0;border-bottom-width:1px;border-bottom-style:solid;transition:all .2s;-webkit-transition:all .2s}.layui-tab-title li{display:inline-block;*display:inline;*zoom:1;vertical-align:middle;font-size:14px;transition:all .2s;-webkit-transition:all .2s;position:relative;line-height:40px;min-width:65px;padding:0 15px;text-align:center;cursor:pointer}.layui-tab-title li a{display:block}.layui-tab-title .layui-this{color:#000}.layui-tab-title .layui-this:after{position:absolute;left:0;top:0;content:'';width:100%;height:41px;border-width:1px;border-style:solid;border-bottom-color:#fff;border-radius:2px 2px 0 0;box-sizing:border-box;pointer-events:none}.layui-tab-bar{position:absolute;right:0;top:0;z-index:10;width:30px;height:39px;line-height:39px;border-width:1px;border-style:solid;border-radius:2px;text-align:center;background-color:#fff;cursor:pointer}.layui-tab-bar .layui-icon{position:relative;display:inline-block;top:3px;transition:all .3s;-webkit-transition:all .3s}.layui-tab-item{display:none}.layui-tab-more{padding-right:30px;height:auto!important;white-space:normal!important}.layui-tab-more li.layui-this:after{border-bottom-color:#e2e2e2;border-radius:2px}.layui-tab-more .layui-tab-bar .layui-icon{top:-2px;top:3px\9;-webkit-transform:rotate(180deg);transform:rotate(180deg)}:root .layui-tab-more .layui-tab-bar .layui-icon{top:-2px\0/IE9}.layui-tab-content{padding:10px}.layui-tab-title li .layui-tab-close{position:relative;display:inline-block;width:18px;height:18px;line-height:20px;margin-left:8px;top:1px;text-align:center;font-size:14px;color:#c2c2c2;transition:all .2s;-webkit-transition:all .2s}.layui-tab-title li .layui-tab-close:hover{border-radius:2px;background-color:#FF5722;color:#fff}.layui-tab-brief>.layui-tab-title .layui-this{color:#009688}.layui-tab-brief>.layui-tab-more li.layui-this:after,.layui-tab-brief>.layui-tab-title .layui-this:after{border:none;border-radius:0;border-bottom:2px solid #5FB878}.layui-tab-brief[overflow]>.layui-tab-title .layui-this:after{top:-1px}.layui-tab-card{border-width:1px;border-style:solid;border-radius:2px;box-shadow:0 2px 5px 0 rgba(0,0,0,.1)}.layui-tab-card>.layui-tab-title{background-color:#f2f2f2}.layui-tab-card>.layui-tab-title li{margin-right:-1px;margin-left:-1px}.layui-tab-card>.layui-tab-title .layui-this{background-color:#fff}.layui-tab-card>.layui-tab-title .layui-this:after{border-top:none;border-width:1px;border-bottom-color:#fff}.layui-tab-card>.layui-tab-title .layui-tab-bar{height:40px;line-height:40px;border-radius:0;border-top:none;border-right:none}.layui-tab-card>.layui-tab-more .layui-this{background:0 0;color:#5FB878}.layui-tab-card>.layui-tab-more .layui-this:after{border:none}.layui-timeline{padding-left:5px}.layui-timeline-item{position:relative;padding-bottom:20px}.layui-timeline-axis{position:absolute;left:-5px;top:0;z-index:10;width:20px;height:20px;line-height:20px;background-color:#fff;color:#5FB878;border-radius:50%;text-align:center;cursor:pointer}.layui-timeline-axis:hover{color:#FF5722}.layui-timeline-item:before{content:'';position:absolute;left:5px;top:0;z-index:0;width:1px;height:100%}.layui-timeline-item:last-child:before{display:none}.layui-timeline-item:first-child:before{display:block}.layui-timeline-content{padding-left:25px}.layui-timeline-title{position:relative;margin-bottom:10px}.layui-badge,.layui-badge-dot,.layui-badge-rim{position:relative;display:inline-block;padding:0 6px;font-size:12px;text-align:center;background-color:#FF5722;color:#fff;border-radius:2px}.layui-badge{height:18px;line-height:18px}.layui-badge-dot{width:8px;height:8px;padding:0;border-radius:50%}.layui-badge-rim{height:18px;line-height:18px;border-width:1px;border-style:solid;background-color:#fff;color:#666}.layui-btn .layui-badge,.layui-btn .layui-badge-dot{margin-left:5px}.layui-nav .layui-badge,.layui-nav .layui-badge-dot{position:absolute;top:50%;margin:-8px 6px 0}.layui-tab-title .layui-badge,.layui-tab-title .layui-badge-dot{left:5px;top:-2px}.layui-carousel{position:relative;left:0;top:0;background-color:#f8f8f8}.layui-carousel>[carousel-item]{position:relative;width:100%;height:100%;overflow:hidden}.layui-carousel>[carousel-item]:before{position:absolute;content:'\e63d';left:50%;top:50%;width:100px;line-height:20px;margin:-10px 0 0 -50px;text-align:center;color:#c2c2c2;font-family:layui-icon!important;font-size:30px;font-style:normal;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}.layui-carousel>[carousel-item]>*{display:none;position:absolute;left:0;top:0;width:100%;height:100%;background-color:#f8f8f8;transition-duration:.3s;-webkit-transition-duration:.3s}.layui-carousel-updown>*{-webkit-transition:.3s ease-in-out up;transition:.3s ease-in-out up}.layui-carousel-arrow{display:none\9;opacity:0;position:absolute;left:10px;top:50%;margin-top:-18px;width:36px;height:36px;line-height:36px;text-align:center;font-size:20px;border:0;border-radius:50%;background-color:rgba(0,0,0,.2);color:#fff;-webkit-transition-duration:.3s;transition-duration:.3s;cursor:pointer}.layui-carousel-arrow[lay-type=add]{left:auto!important;right:10px}.layui-carousel:hover .layui-carousel-arrow[lay-type=add],.layui-carousel[lay-arrow=always] .layui-carousel-arrow[lay-type=add]{right:20px}.layui-carousel[lay-arrow=always] .layui-carousel-arrow{opacity:1;left:20px}.layui-carousel[lay-arrow=none] .layui-carousel-arrow{display:none}.layui-carousel-arrow:hover,.layui-carousel-ind ul:hover{background-color:rgba(0,0,0,.35)}.layui-carousel:hover .layui-carousel-arrow{display:block\9;opacity:1;left:20px}.layui-carousel-ind{position:relative;top:-35px;width:100%;line-height:0!important;text-align:center;font-size:0}.layui-carousel[lay-indicator=outside]{margin-bottom:30px}.layui-carousel[lay-indicator=outside] .layui-carousel-ind{top:10px}.layui-carousel[lay-indicator=outside] .layui-carousel-ind ul{background-color:rgba(0,0,0,.5)}.layui-carousel[lay-indicator=none] .layui-carousel-ind{display:none}.layui-carousel-ind ul{display:inline-block;padding:5px;background-color:rgba(0,0,0,.2);border-radius:10px;-webkit-transition-duration:.3s;transition-duration:.3s}.layui-carousel-ind li{display:inline-block;width:10px;height:10px;margin:0 3px;font-size:14px;background-color:#e2e2e2;background-color:rgba(255,255,255,.5);border-radius:50%;cursor:pointer;-webkit-transition-duration:.3s;transition-duration:.3s}.layui-carousel-ind li:hover{background-color:rgba(255,255,255,.7)}.layui-carousel-ind li.layui-this{background-color:#fff}.layui-carousel>[carousel-item]>.layui-carousel-next,.layui-carousel>[carousel-item]>.layui-carousel-prev,.layui-carousel>[carousel-item]>.layui-this{display:block}.layui-carousel>[carousel-item]>.layui-this{left:0}.layui-carousel>[carousel-item]>.layui-carousel-prev{left:-100%}.layui-carousel>[carousel-item]>.layui-carousel-next{left:100%}.layui-carousel>[carousel-item]>.layui-carousel-next.layui-carousel-left,.layui-carousel>[carousel-item]>.layui-carousel-prev.layui-carousel-right{left:0}.layui-carousel>[carousel-item]>.layui-this.layui-carousel-left{left:-100%}.layui-carousel>[carousel-item]>.layui-this.layui-carousel-right{left:100%}.layui-carousel[lay-anim=updown] .layui-carousel-arrow{left:50%!important;top:20px;margin:0 0 0 -18px}.layui-carousel[lay-anim=updown]>[carousel-item]>*,.layui-carousel[lay-anim=fade]>[carousel-item]>*{left:0!important}.layui-carousel[lay-anim=updown] .layui-carousel-arrow[lay-type=add]{top:auto!important;bottom:20px}.layui-carousel[lay-anim=updown] .layui-carousel-ind{position:absolute;top:50%;right:20px;width:auto;height:auto}.layui-carousel[lay-anim=updown] .layui-carousel-ind ul{padding:3px 5px}.layui-carousel[lay-anim=updown] .layui-carousel-ind li{display:block;margin:6px 0}.layui-carousel[lay-anim=updown]>[carousel-item]>.layui-this{top:0}.layui-carousel[lay-anim=updown]>[carousel-item]>.layui-carousel-prev{top:-100%}.layui-carousel[lay-anim=updown]>[carousel-item]>.layui-carousel-next{top:100%}.layui-carousel[lay-anim=updown]>[carousel-item]>.layui-carousel-next.layui-carousel-left,.layui-carousel[lay-anim=updown]>[carousel-item]>.layui-carousel-prev.layui-carousel-right{top:0}.layui-carousel[lay-anim=updown]>[carousel-item]>.layui-this.layui-carousel-left{top:-100%}.layui-carousel[lay-anim=updown]>[carousel-item]>.layui-this.layui-carousel-right{top:100%}.layui-carousel[lay-anim=fade]>[carousel-item]>.layui-carousel-next,.layui-carousel[lay-anim=fade]>[carousel-item]>.layui-carousel-prev{opacity:0}.layui-carousel[lay-anim=fade]>[carousel-item]>.layui-carousel-next.layui-carousel-left,.layui-carousel[lay-anim=fade]>[carousel-item]>.layui-carousel-prev.layui-carousel-right{opacity:1}.layui-carousel[lay-anim=fade]>[carousel-item]>.layui-this.layui-carousel-left,.layui-carousel[lay-anim=fade]>[carousel-item]>.layui-this.layui-carousel-right{opacity:0}.layui-fixbar{position:fixed;right:15px;bottom:15px;z-index:9999}.layui-fixbar li{width:50px;height:50px;line-height:50px;margin-bottom:1px;text-align:center;cursor:pointer;font-size:30px;background-color:#9F9F9F;color:#fff;border-radius:2px;opacity:.95}.layui-fixbar li:hover{opacity:.85}.layui-fixbar li:active{opacity:1}.layui-fixbar .layui-fixbar-top{display:none;font-size:40px}body .layui-util-face{border:none;background:0 0}body .layui-util-face .layui-layer-content{padding:0;background-color:#fff;color:#666;box-shadow:none}.layui-util-face .layui-layer-TipsG{display:none}.layui-util-face ul{position:relative;width:372px;padding:10px;border:1px solid #D9D9D9;background-color:#fff;box-shadow:0 0 20px rgba(0,0,0,.2)}.layui-util-face ul li{cursor:pointer;float:left;border:1px solid #e8e8e8;height:22px;width:26px;overflow:hidden;margin:-1px 0 0 -1px;padding:4px 2px;text-align:center}.layui-util-face ul li:hover{position:relative;z-index:2;border:1px solid #eb7350;background:#fff9ec}.layui-anim{-webkit-animation-duration:.3s;animation-duration:.3s;-webkit-animation-fill-mode:both;animation-fill-mode:both}.layui-anim.layui-icon{display:inline-block}.layui-anim-loop{-webkit-animation-iteration-count:infinite;animation-iteration-count:infinite}@-webkit-keyframes layui-rotate{from{-webkit-transform:rotate(0)}to{-webkit-transform:rotate(360deg)}}@keyframes layui-rotate{from{transform:rotate(0)}to{transform:rotate(360deg)}}.layui-anim-rotate{-webkit-animation-name:layui-rotate;animation-name:layui-rotate;-webkit-animation-duration:1s;animation-duration:1s;-webkit-animation-timing-function:linear;animation-timing-function:linear}@-webkit-keyframes layui-up{from{-webkit-transform:translate3d(0,100%,0);opacity:.3}to{-webkit-transform:translate3d(0,0,0);opacity:1}}@keyframes layui-up{from{transform:translate3d(0,100%,0);opacity:.3}to{transform:translate3d(0,0,0);opacity:1}}.layui-anim-up{-webkit-animation-name:layui-up;animation-name:layui-up}@-webkit-keyframes layui-upbit{from{-webkit-transform:translate3d(0,30px,0);opacity:.3}to{-webkit-transform:translate3d(0,0,0);opacity:1}}@keyframes layui-upbit{from{transform:translate3d(0,30px,0);opacity:.3}to{transform:translate3d(0,0,0);opacity:1}}.layui-anim-upbit{-webkit-animation-name:layui-upbit;animation-name:layui-upbit}@-webkit-keyframes layui-scale{0%{opacity:.3;-webkit-transform:scale(.5)}100%{opacity:1;-webkit-transform:scale(1)}}@keyframes layui-scale{0%{opacity:.3;-ms-transform:scale(.5);transform:scale(.5)}100%{opacity:1;-ms-transform:scale(1);transform:scale(1)}}.layui-anim-scale{-webkit-animation-name:layui-scale;animation-name:layui-scale}@-webkit-keyframes layui-scale-spring{0%{opacity:.5;-webkit-transform:scale(.5)}80%{opacity:.8;-webkit-transform:scale(1.1)}100%{opacity:1;-webkit-transform:scale(1)}}@keyframes layui-scale-spring{0%{opacity:.5;transform:scale(.5)}80%{opacity:.8;transform:scale(1.1)}100%{opacity:1;transform:scale(1)}}.layui-anim-scaleSpring{-webkit-animation-name:layui-scale-spring;animation-name:layui-scale-spring}@-webkit-keyframes layui-fadein{0%{opacity:0}100%{opacity:1}}@keyframes layui-fadein{0%{opacity:0}100%{opacity:1}}.layui-anim-fadein{-webkit-animation-name:layui-fadein;animation-name:layui-fadein}@-webkit-keyframes layui-fadeout{0%{opacity:1}100%{opacity:0}}@keyframes layui-fadeout{0%{opacity:1}100%{opacity:0}}.layui-anim-fadeout{-webkit-animation-name:layui-fadeout;animation-name:layui-fadeout}
</style>
<style>
    body{
        color:#666;
        background-color: #F2F2F2;
    }
    .fly-panel{
        margin-bottom: 15px;
        border-radius: 2px;
        background-color: #fff;
        box-shadow: 0 1px 2px 0 rgba(0,0,0,.05);
    }
    .main{
        margin: 2% auto;
        padding: 50px 50px;
        width: 870px;
    }
    .right-item-title{
        height: 10px;
        line-height: 10px;
    }
    .right-item li{
        margin-left:20px;
        margin-top:3px;
        overflow: hidden;
    }
    .right-item em{
        margin-left:5px;
        font-style: normal;
        color:#BBBBBB;
    }
    .api-main .title{
        background-color: #F8F8F8;
        height: 40px;
        line-height: 40px;
        padding: 6px;
        padding-left:20px;
        font-size: 17px;
        font-weight:480;
        color:#000;
    }
</style>
<body>
    <div class="fly-panel main">
        <p>按下 <code>CTRL + F</code> 可以进行搜索。</p>
        <hr style="margin-bottom:20px;">

        <div class="layui-row layui-col-space20">
            <div class="layui-col-md8">

                <!-- <div id="api.php/index/index/list" class="api-main">
                    <div class="title">获取所有的列表</div>
                    <div class="body">
                        <table class="layui-table">
                            <thead>
                                <tr>
                                    <th>
                                        POST
                                    </th>
                                    <th rowspan="3">
                                        api.php/index/index/list
                                    </th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <div class="body">
                        <table class="layui-table">
                            <thead>
                                <tr>
                                    <th>
                                        请求类型
                                    </th>
                                    <th>
                                        请求名称
                                    </th>
                                    <th>
                                        请求说明
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        integer
                                    </td>
                                    <td>
                                        $page
                                    </td>
                                    <td>
                                        要获取的页数
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="body">
                        <table class="layui-table">
                            <thead>
                                <tr>
                                    <th>
                                        返回类型
                                    </th>
                                    <th>
                                        返回名称
                                    </th>
                                    <th>
                                        返回说明
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        integer
                                    </td>
                                    <td>
                                        $code
                                    </td>
                                    <td>
                                        状态码
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <hr>
                </div> -->
                {main}

            </div>

            <div class="layui-col-md4">
                <!-- <blockquote class="layui-elem-quote layui-quote-nm right-item-title">Index.php</blockquote>
                <ul class="right-item">
                    <li><a href="#"><cite>获取所有列表</cite><em>api.php/index/index/list</em></a></li>
                    <li><a href="#"><cite>获取我的列表</cite><em>api.php/index/index/list</em></a></li>
                    <li><a href="#"><cite>添加一条数据</cite><em>api.php/index/index/list</em></a></li>
                    <li><a href="#"><cite>删除一条数据</cite><em>api.php/index/index/list</em></a></li>
                    <li><a href="#"><cite>修改一条数据</cite><em>api.php/index/index/list</em></a></li>
                </ul> -->
                {right}
            </div>
        </div>

        <hr style="margin-top:40px;">
        <p style="text-align:center;">
            本页面由 <a href="http://dust101.lofter.com" target="_blank">Dust</a> 的 <a href="https://github.com/mumbaicat/makeapidoc" target="_blank">ApiDoc</a> 于 {date} 生成
            <!-- 2018年1月14日16:56:57 -->
        </p>
    </div>
</body>
</html>
EOL;
        $tempData = str_replace('{name}',$this->name,$tempData);
        $tempData = str_replace('{main}',$inputData,$tempData);
        $tempData = str_replace('{right}',$this->makeRight($rightList),$tempData);
        $tempData = str_replace('{date}',date('Y-m-d H:i:s'),$tempData);
        file_put_contents($this->savePath.$this->name.'.html',$tempData);
    }
}