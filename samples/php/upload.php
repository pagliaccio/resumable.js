<?php


include_once 'resumablejs.class.php';

//check if request is GET and the requested chunk exists or not. this makes testChunks work
if ($_SERVER['REQUEST_METHOD'] === 'GET') 
	$data=$_GET;
else $data=$_POST;

$resumable=new resumableJS(array(
	'name'=>$data['resumableFilename']
	,'tmpdir'=>'temp/'.$data['resumableIdentifier']
	,'totalSize'=>$data['resumableTotalSize']
	,'chunkSize'=>$data['resumableChunkSize']
	,'log'=>'print_r'
));
$resumable->listen($data, $_SERVER['REQUEST_METHOD'],$_FILES);


?>