<?php
$META_REFRESH = false;

// Error reporting
ini_set('display_errors', 1);
ini_set('html_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php.txt');
ini_set('error_reporting', E_ALL | E_STRICT);

// Set memory and time limit
ini_set('memory_limit', '64M');
ini_set('max_execution_time', 600);
set_time_limit(600);

// Include debug functions, and the Spider class
require_once('../../debug.php');
require_once('Spider.class.php');

// =======================================================================================================
// Create database handler
// =======================================================================================================
$dbh = null;
try{
	$dbh = new PDO('mysql:host=127.0.0.1;port=3306;dbname=test', 'root', '', array(
		PDO::ATTR_PERSISTENT => false, // Persistent connection
		#PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, // Dont show errors
		PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING, // Raise E_WARNING
		#PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions
		PDO::ATTR_EMULATE_PREPARES => false, // Emulate prepared statements, or use native prepared statements
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ, // Set default fetch mode
	));
	$dbh->exec('set character set utf8;');
}
catch (PDOException $e){
	print 'Error!: '.$e->getMessage();
	exit;
}

// Truncate tables
#$dbh->query('truncate table spider_site;');
#$dbh->query('truncate table spider_site_page;');

// =======================================================================================================
// Create new instance of the spider, and set configuration
// =======================================================================================================
$spider = new Cosy_Spider($dbh, array(
	// Crawl type (all | external | internal | site | deeper)
	'crawlType' => 'all',
	// Automatically crawl next URL
	'crawlNext' => true,
	// Max pages to crawl
	'maxPages' => 10,
	// Collect META data when crawling pages (title, description, keywords)
	'collectMetaData' => false,
	// Re-crawl date-time, for re-crawling pages older than the date-time
	'recrawlDate' => date('Y-m-d H:i:s', strtotime('-1month')),
	// Which encoding should HTML source code be converted to
	'encoding' => 'HTML-ENTITIES',
));
$spider->dontCrawlSiteUrl = array(
	'http://127.0.0.1',
	'http://addedbytes.com',
	'http://amazon.co.uk',
	'http://amazon.com',
	'http://api.drupal.org',
	'http://b.dk',
	'http://beep.tv2.dk',
	'http://bit.ly',
	'http://blogger.com',
	'http://boards.4chan.org',
	'http://bullguard.com',
	'http://cakephp.org',
	'http://clk.tradedoubler.com',
	'http://code.google.com',
	'http://codeigniter.com',
	'http://codenerd.dk',
	'http://comon.dk',
	'http://dk.php.net',
	'http://dvdoo.dk',
	'http://drupal.org',
	'http://en.gravatar.com',
	'http://en.wikipedia.org',
	'http://epn.dk',
	'http://facebook.com',
	'http://filmz.dk',
	'http://forum.mamboserver.com',
	'http://forums.oscommerce.com',
	'http://google.com',
	'http://google.dk',
	'http://googleblog.blogspot.com',
	'http://googleonlinesecurity.blogspot.com',
	'http://googlewebmastercentral.blogspot.com',
	'http://gosquared.com',
	'http://gucca.dk',
	'http://imdb.com',
	'http://Ing.dk',
	'http://jquery.com',
	'http://linkedin.com',
	'http://macnation.dk',
	'http://macnation.newz.dk',
	'http://maps.google.com',
	'http://mastercard.com',
	'http://microsoft.com',
	'http://mootools.net',
	'http://newz.dk',
	'http://newzmedia.dk',
	'http://pcworld.com',
	'http://php.net',
	'http://raid1.dk',
	'http://raid1.newz.dk',
	'http://railgun.dk',
	'http://railgun.newz.dk',
	'http://support.google.com',
	'http://theoatmeal.com',
	'http://torrentfreak.com',
	'http://twitter.com',
	'http://userscripts.org',
	'http://wordpress.org',
	'http://ws.spotify.com',
	'http://xkcd.com',
	'http://youtube.com',
);

$spider->debug = false;
$spider->debugCrawler = false;
$spider->debugHarvester = false;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<title>Spider test</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
if ($META_REFRESH){
	echo '<meta http-equiv="refresh" content="2" />';
}
?>
<style type="text/css">
body{
	font:normal 13px Verdana, Geneva, sans-serif;
	color:#333; background:#FFF;
}
a,
a:link,
a:active,
a:visited,
a:hover{
	text-decoration:none;
	color:#0080ff;
}
a:visited{
	color:#8080c0;
}
a:hover{
	text-decoration:underline;
}
</style>
</head>

<body>

<?php
#$CRAWL_URL = 'http://newz.dk/forum/tagwall/peecee-traaden-113756';
#$CRAWL_URL = 'http://jan-ebsen.dk/author/admin/';
$CRAWL_URL = 'http://jan-ebsen.dk';

// Set URL and start crawling
#$spider->setCurrentUrl($CRAWL_URL);
#$spider->crawl();
#$spider->crawlNext();

/*
// Site statistics
$sql = '
select id, url, status, crawl_time,
	if (status = "i", 1,
		if (status = "c", 2, 3)
	) as sort_order
from spider_site
order by sort_order asc, url asc;';
$stmt = $dbh->prepare($sql);
$stmt->execute();
if ($rows = $stmt->fetchAll(PDO::FETCH_OBJ)){
	$countTotal = 0;
	$countPending = 0;
	$countCrawled = 0;
	$countIncomplete = 0;
	
	$htmlLinkList = '';
	
	ob_start();
	foreach ($rows as $row){
		$attr = '';
		$countTotal++;
		
		if ($row->status == 'p'){
			$attr.= ' title="Pending" style="color:grey;"';
			$countPending++;
			
			continue;
		}
		elseif ($row->status == 'c'){
			$attr.= ' title="Crawled" style="color:green;"';
			$countCrawled++;
		}
		else{
			$attr.= ' title="Incomplete" style="color:red;"';
			$countIncomplete++;
		}
		
		echo '<a href="'.$row->url.'" target="_blank"'.$attr.'>';
		echo $row->url;
		echo '</a>';
		echo '<br />';
	}
	$htmlLinkList = ob_get_clean();
	
	echo '<br />';
	echo '<strong>'.$countTotal.' site\'s found ('.$countPending.' pending, '.$countCrawled.' crawled, '.$countIncomplete.' incomplete):</strong>';
	echo '<hr />';
	echo $htmlLinkList;
}
#*/

/*
// Site page statistics
$sql = '
select url, status
from spider_site_page
#where status != "p"
order by url asc;';
$stmt = $dbh->prepare($sql);
$stmt->execute();
if ($rows = $stmt->fetchAll(PDO::FETCH_OBJ)){
	$countTotal = 0;
	$countPending = 0;
	$countCrawled = 0;
	$countFailed = 0;
	
	$htmlLinkList = '';
	ob_start();
	foreach ($rows as $row){
		$url = $row->url;
		$label = (!empty($row->meta_title) ? $row->meta_title : $url);
		$attr = '';
		
		$countTotal++;
		
		if ($row->status == 'p'){
			$attr.= ' title="Pending" style="color:grey;"';
			$countPending++;
			
			continue;
		}
		elseif ($row->status == 'c'){
			$attr.= ' title="Crawled"';
			$countCrawled++;
		}
		else{
			$attr.= ' title="Failed" style="color:red;"';
			$countFailed++;
		}
		
		echo '<a href="'.$url.'" target="_blank"'.$attr.'>';
		echo $url;
		echo '</a>';
		echo '<br />';
	}
	$htmlLinkList = ob_get_clean();
	
	echo '<br />';
	echo '<strong>'.$countTotal.' site page\'s found ('.$countPending.' pending, '.$countCrawled.' crawled, '.$countFailed.' failed):</strong>';
	echo '<hr />';
	echo $htmlLinkList;
}
else{
	echo 'No URL\'s found...';
}
#*/

/*
// Site map ?...
$sql = '
select spider_site_page.url,
	spider_site_page.status,
	spider_site_page.crawl_time
from spider_site
join spider_site_page on spider_site_page.spider_site_id = spider_site.id
where spider_site.url = "'.$CRAWL_URL.'"
order by spider_site_page.url asc;';
$stmt = $dbh->prepare($sql);
$stmt->execute();
if ($rows = $stmt->fetchAll(PDO::FETCH_OBJ)){
	foreach ($rows as $row){
		$row->url = rtrim($row->url, '/');
		$level = (substr_count($row->url, '/') - 2);
		
		echo str_repeat('&nbsp;', ($level * 4));
		echo '<a href="'.$row->url.'">';
		echo $row->url;
		echo '</a>';
		echo '<br />';
	}
}
#*/

// Close database connection
$dbh = null;
?>

</body>

</html>