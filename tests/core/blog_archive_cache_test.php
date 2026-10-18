<?php
$root=dirname(__DIR__,2);define('APP_ROOT',sys_get_temp_dir().'/musedock-archive-test-'.bin2hex(random_bytes(6)));require $root.'/vendor/autoload.php';
use Screenart\Musedock\Cache\{HtmlCache,HtmlCacheMiddleware};
function assertArchive(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$paths=[];
try{
 foreach([['32','one.example','/category/blog'],['32','alias.example','/category/blog'],['32','one.example','/tag/news'],['33','other.example','/category/blog'],['32','one.example','/an-article']]as[$tenant,$host,$uri]){
  $path=APP_ROOT.'/storage/html-cache/_tenant_'.$tenant.'/@'.$host.$uri.'.html';@mkdir(dirname($path),0700,true);file_put_contents($path,'old');file_put_contents($path.'.meta.json',json_encode(['uri'=>$uri,'tags'=>['content']]));$paths[]=$path;
 }
 HtmlCache::invalidateByTag('blog-list',32);
 assertArchive(!is_file($paths[0])&&!is_file($paths[1])&&!is_file($paths[2]),'legacy category and tag copies invalidated across aliases');
 assertArchive(is_file($paths[3])&&is_file($paths[4]),'unrelated tenant and article copies preserved');
 $method=new ReflectionMethod(HtmlCacheMiddleware::class,'guessTags');$method->setAccessible(true);
 foreach(['/category/blog/','/tag/news','/blog/category/blog','/en/blog/category/blog']as$uri){$tags=$method->invoke(null,$uri,32);assertArchive(in_array('blog-list',$tags,true)&&in_array('blog',$tags,true),'archive receives blog invalidation tags');}
 echo "PASS: category/tag cache invalidation, legacy copies, aliases and tenant isolation.\n";
}finally{if(is_dir(APP_ROOT)){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as$file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir(APP_ROOT);}}
