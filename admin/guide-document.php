<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$id=max(1,(int)($_GET['id']??0));$type=(string)($_GET['type']??'');
$columns=['license'=>'license_document_path','identity'=>'identity_document_path','insurance'=>'insurance_document_path'];
if(!isset($columns[$type])){http_response_code(404);exit('Document not found');}
$stmt=db()->prepare('SELECT '.$columns[$type].' FROM guides WHERE id=?');$stmt->execute([$id]);$relative=(string)($stmt->fetchColumn()?:'');
if(!preg_match('#^storage/guide-documents/[a-f0-9]{32}\.(pdf|jpg|png)$#',$relative)){http_response_code(404);exit('Document not found');}
$file=dirname(__DIR__).'/'.$relative;if(!is_file($file)){http_response_code(404);exit('Document not found');}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file)?:'application/octet-stream';
header('Content-Type: '.$mime);header('Content-Length: '.(string)filesize($file));header('Content-Disposition: inline; filename="guide-'.$id.'-'.$type.'.'.pathinfo($file,PATHINFO_EXTENSION).'"');header('X-Content-Type-Options: nosniff');readfile($file);exit;
